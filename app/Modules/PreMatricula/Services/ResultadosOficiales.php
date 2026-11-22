<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\CambioHistorial;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;

/**
 * Importa resultados oficiales (notas y estados) desde un Excel.
 * Validar no guarda nada; confirmar exige la misma huella que se validó.
 */
final class ResultadosOficiales
{
    public const COLUMNAS = ['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'nota_final', 'estado'];

    public const ESTADOS = [HistorialAcademico::APROBADO, HistorialAcademico::DESAPROBADO, HistorialAcademico::EN_CURSO, HistorialAcademico::RETIRADO];

    public function __construct(
        private readonly BloqueoAcademico $bloqueo,
        private readonly RevalidacionPrematricula $revalidacion,
    ) {}

    /** @return list<array<string, mixed>> filas con su número de fila del Excel */
    public function leer(string $ruta): array
    {
        $libro = SimpleXLSX::parse($ruta);
        if ($libro === false) {
            throw new PrematriculaException('No se pudo leer el archivo. Verifica que sea un Excel .xlsx válido.');
        }

        $filas = $libro->rows();
        $encabezado = array_map(fn ($valor): string => mb_strtolower(trim((string) $valor)), array_shift($filas) ?? []);
        $faltantes = array_diff(self::COLUMNAS, $encabezado);
        if ($faltantes !== []) {
            throw new PrematriculaException('Faltan columnas en la primera fila: '.implode(', ', $faltantes).'.');
        }

        $resultado = [];
        foreach ($filas as $indice => $fila) {
            if (implode('', array_map(fn ($valor): string => trim((string) $valor), $fila)) === '') {
                continue;
            }
            $registro = ['fila' => $indice + 2];
            foreach (self::COLUMNAS as $columna) {
                $valor = $fila[array_search($columna, $encabezado, true)] ?? null;
                $registro[$columna] = is_string($valor) ? trim($valor) : $valor;
            }
            $resultado[] = $registro;
        }

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{valido: bool, huella: string, resumen: array<string, int>, errores: list<array<string, mixed>>, cambios: list<array<string, mixed>>, preparadas: list<array<string, mixed>>}
     */
    public function validar(array $filas): array
    {
        $estudiantes = DB::table('core.estudiante')->whereIn('codigo_universitario', $this->codigos($filas, 'codigo_universitario'))
            ->get(['id_estudiante', 'codigo_universitario', 'id_plan'])->keyBy('codigo_universitario');
        $periodos = DB::table('core.periodo_academico')->whereIn('codigo', $this->codigos($filas, 'codigo_periodo'))
            ->get(['id_periodo', 'codigo'])->keyBy('codigo');
        $cursosPlan = DB::table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->whereIn('c.codigo', $this->codigos($filas, 'codigo_curso'))
            ->get(['cp.id_curso_plan', 'cp.id_plan', 'c.codigo'])
            ->keyBy(fn (object $fila): string => $fila->id_plan.':'.$fila->codigo);

        $errores = [];
        $preparadas = [];
        $cambios = [];
        $vistos = [];
        $huella = [];
        $resumen = ['crear' => 0, 'actualizar' => 0, 'sin_cambios' => 0];

        foreach ($filas as $fila) {
            $numero = $fila['fila'];
            if (($fila['nota_final'] ?? '') === '') {
                $fila['nota_final'] = null;
            }
            foreach (['codigo_universitario', 'codigo_curso', 'codigo_periodo', 'estado'] as $campo) {
                if (is_int($fila[$campo] ?? null) || is_float($fila[$campo] ?? null)) {
                    $fila[$campo] = (string) $fila[$campo];
                }
            }
            if (is_string($fila['estado'] ?? null)) {
                $fila['estado'] = mb_strtoupper($fila['estado']);
            }

            $validador = Validator::make($fila, [
                'codigo_universitario' => ['required', 'string', 'max:30'],
                'codigo_curso' => ['required', 'string', 'max:50'],
                'codigo_periodo' => ['required', 'string', 'max:20'],
                'nota_final' => ['nullable', 'numeric', 'between:0,20'],
                'estado' => ['required', Rule::in(self::ESTADOS)],
            ], [], [
                'codigo_universitario' => 'código universitario', 'codigo_curso' => 'código de curso',
                'codigo_periodo' => 'código de período', 'nota_final' => 'nota final',
            ]);
            if ($validador->fails()) {
                foreach ($validador->errors()->messages() as $campo => $mensajes) {
                    $errores[] = ['fila' => $numero, 'columna' => $campo, 'mensaje' => $mensajes[0]];
                }

                continue;
            }
            if (in_array($fila['estado'], [HistorialAcademico::APROBADO, HistorialAcademico::DESAPROBADO], true) && $fila['nota_final'] === null) {
                $errores[] = ['fila' => $numero, 'columna' => 'nota_final', 'mensaje' => 'Un curso aprobado o desaprobado necesita nota.'];
            }
            if ($fila['estado'] === HistorialAcademico::RETIRADO && $fila['nota_final'] !== null) {
                $errores[] = ['fila' => $numero, 'columna' => 'nota_final', 'mensaje' => 'Un retiro no lleva nota; deja la celda vacía.'];
            }

            $estudiante = $estudiantes->get($fila['codigo_universitario']);
            $periodo = $periodos->get($fila['codigo_periodo']);
            $cursoPlan = $estudiante === null ? null : $cursosPlan->get($estudiante->id_plan.':'.$fila['codigo_curso']);
            if ($estudiante === null) {
                $errores[] = ['fila' => $numero, 'columna' => 'codigo_universitario', 'mensaje' => 'No existe un estudiante con ese código.'];
            }
            if ($periodo === null) {
                $errores[] = ['fila' => $numero, 'columna' => 'codigo_periodo', 'mensaje' => 'No existe un período con ese código.'];
            }
            if ($estudiante !== null && $cursoPlan === null) {
                $errores[] = ['fila' => $numero, 'columna' => 'codigo_curso', 'mensaje' => 'El curso no pertenece al plan de estudios del estudiante.'];
            }
            if ($estudiante === null || $periodo === null || $cursoPlan === null) {
                continue;
            }

            $clave = $estudiante->id_estudiante.':'.$cursoPlan->id_curso_plan.':'.$periodo->id_periodo;
            if (isset($vistos[$clave])) {
                $errores[] = ['fila' => $numero, 'columna' => 'codigo_curso', 'mensaje' => 'Resultado repetido para el mismo estudiante, curso y período (ver fila '.$vistos[$clave].').'];

                continue;
            }
            $vistos[$clave] = $numero;

            $existente = HistorialAcademico::query()->where('id_estudiante', $estudiante->id_estudiante)
                ->where('id_curso_plan', $cursoPlan->id_curso_plan)->where('id_periodo', $periodo->id_periodo)->first();
            $despues = ['nota_final' => $fila['nota_final'] === null ? null : number_format((float) $fila['nota_final'], 2, '.', ''), 'estado' => $fila['estado']];
            $antes = $existente === null ? null : ['nota_final' => $existente->nota_final === null ? null : number_format((float) $existente->nota_final, 2, '.', ''), 'estado' => $existente->estado];
            $accion = $antes === null ? 'crear' : ($antes === $despues ? 'sin_cambios' : 'actualizar');
            $resumen[$accion]++;

            $preparadas[] = ['id_estudiante' => (int) $estudiante->id_estudiante, 'id_curso_plan' => (int) $cursoPlan->id_curso_plan,
                'id_periodo' => (int) $periodo->id_periodo, ...$despues, 'antes' => $antes, 'accion' => $accion];
            $cambios[] = ['fila' => $numero, 'codigo_universitario' => $fila['codigo_universitario'], 'codigo_curso' => $fila['codigo_curso'],
                'codigo_periodo' => $fila['codigo_periodo'], 'accion' => $accion, 'antes' => $antes, 'despues' => $despues];
            $huella[$clave] = [$existente?->id_historial, $antes];
        }

        if ($filas === []) {
            $errores[] = ['fila' => 2, 'columna' => 'codigo_universitario', 'mensaje' => 'El archivo no contiene resultados.'];
        }
        ksort($huella, SORT_STRING);

        return [
            'valido' => $errores === [],
            'huella' => hash('sha256', json_encode($huella, JSON_THROW_ON_ERROR)),
            'resumen' => $resumen,
            'errores' => $errores,
            'cambios' => $cambios,
            'preparadas' => $preparadas,
        ];
    }

    /** @param list<array<string, mixed>> $filas */
    public function importar(array $filas, string $huella, string $sha256, string $motivo, int $idUsuario): ImportacionHistorial
    {
        return $this->bloqueo->transaccion(function () use ($filas, $huella, $sha256, $motivo, $idUsuario): ImportacionHistorial {
            $reporte = $this->validar($filas);
            if (! $reporte['valido']) {
                throw new PrematriculaException('El archivo tiene errores. Vuelve a validarlo.');
            }
            if (! hash_equals($huella, $reporte['huella'])) {
                throw new PrematriculaException('Los resultados cambiaron desde la validación. Vuelve a validar el archivo.');
            }

            $importacion = ImportacionHistorial::query()->create([
                'creado_por' => $idUsuario, 'sha256' => $sha256, 'motivo' => $motivo, 'resumen' => $reporte['resumen'],
            ]);
            $estudiantes = [];
            foreach ($reporte['preparadas'] as $fila) {
                if ($fila['accion'] === 'sin_cambios') {
                    continue;
                }
                $historial = HistorialAcademico::query()->updateOrCreate(
                    ['id_estudiante' => $fila['id_estudiante'], 'id_curso_plan' => $fila['id_curso_plan'], 'id_periodo' => $fila['id_periodo']],
                    ['nota_final' => $fila['nota_final'], 'estado' => $fila['estado'], 'actualizado_en' => now()],
                );
                CambioHistorial::query()->create([
                    'id_importacion' => $importacion->id_importacion, 'id_historial' => $historial->id_historial,
                    'antes' => $fila['antes'], 'despues' => ['nota_final' => $fila['nota_final'], 'estado' => $fila['estado']],
                ]);
                $estudiantes[] = $fila['id_estudiante'];
            }
            $this->revalidacion->paraEstudiantes(array_values(array_unique($estudiantes)));

            return $importacion;
        });
    }

    public function plantilla(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla');
        SimpleXLSXGen::fromArray([
            self::COLUMNAS,
            ["\0".'1011121314', "\0".'INF101', "\0".'2026-I', 15, 'APROBADO'],
        ])->saveAs($ruta);
        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    /** @param list<array<string, mixed>> $filas
     * @return list<string> */
    private function codigos(array $filas, string $campo): array
    {
        return collect($filas)->pluck($campo)->filter(fn ($valor): bool => is_scalar($valor) && trim((string) $valor) !== '')
            ->map(fn ($valor): string => trim((string) $valor))->unique()->values()->all();
    }
}
