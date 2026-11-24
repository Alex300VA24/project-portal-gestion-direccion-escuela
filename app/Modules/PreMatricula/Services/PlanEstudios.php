<?php

namespace App\Modules\PreMatricula\Services;

use App\Shared\Models\Core\PlanEstudios as Plan;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;

/**
 * Plan de estudios (malla) del plan predeterminado, como CurriculumCatalog,
 * CurriculumValidator, ImportCurriculum y UpdateCourse de la rama master.
 * Un curso usado en historial o prematrícula conserva sus datos académicos,
 * salvo una corrección individual confirmada por la coordinación.
 */
final class PlanEstudios
{
    public const COLUMNAS = ['codigo', 'nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio', 'prerrequisitos'];

    public const CAMPOS_ACADEMICOS = ['nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio'];

    public const ACTIVO = 'Activo';

    public const INACTIVO = 'Inactivo';

    private const BLOQUEO_CATALOGO = 82640101;

    private const MENSAJES = [
        'required' => 'La columna :attribute es obligatoria.',
        'string' => 'La columna :attribute debe contener texto.',
        'integer' => 'La columna :attribute debe contener un entero.',
        'numeric' => 'La columna :attribute debe contener un número.',
        'between' => 'La columna :attribute debe estar entre :min y :max.',
        'max' => 'La columna :attribute admite como máximo :max caracteres.',
        'distinct' => 'No se permiten prerrequisitos repetidos.',
        'regex' => 'La columna :attribute tiene un formato inválido.',
        'not_regex' => 'La columna :attribute tiene caracteres no permitidos.',
    ];

    public function __construct(
        private readonly BloqueoAcademico $bloqueo,
        private readonly RevalidacionPrematricula $revalidacion,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function reglas(): array
    {
        $codigo = ['required', 'string', 'max:30', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/'];

        return [
            'codigo' => $codigo,
            'nombre' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F]/'],
            'creditos' => ['required', 'numeric', 'integer', 'between:1,32767'],
            'ciclo' => ['required', 'numeric', 'integer', 'between:1,32767'],
            'horas_teoria' => ['required', 'numeric', 'integer', 'between:0,999'],
            'horas_practica' => ['required', 'numeric', 'integer', 'between:0,999'],
            'horas_laboratorio' => ['required', 'numeric', 'integer', 'between:0,999'],
            'prerrequisitos' => ['present', 'array', 'max:5000'],
            'prerrequisitos.*' => [...$codigo, 'distinct:strict'],
        ];
    }

    public function plan(): Plan
    {
        return Plan::query()->where('es_predeterminado', true)->orderBy('id_plan')->first()
            ?? throw new PrematriculaException('No hay un plan de estudios predeterminado. Regístralo en el portal antes de gestionar la malla.');
    }

    /**
     * Cursos del plan con sus prerrequisitos y si ya están en uso.
     *
     * @return Collection<string, object> indexados por código
     */
    public function catalogo(Plan $plan): Collection
    {
        $filas = DB::table('core.curso_plan as cp')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('cp.id_plan', $plan->id_plan)
            ->orderBy('cp.ciclo')->orderBy('c.codigo')
            ->get(['cp.id_curso_plan', 'c.id_curso', 'c.codigo', 'c.nombre', 'c.creditos', 'cp.ciclo', 'c.horas_teoria', 'c.horas_practica', 'c.horas_laboratorio', 'c.estado']);

        $ids = $filas->pluck('id_curso_plan')->all();
        $requisitos = DB::table('core.curso_prerequisito as p')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'p.id_curso_plan_requisito')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->whereIn('p.id_curso_plan', $ids)
            ->get(['p.id_curso_plan', 'c.codigo'])
            ->groupBy('id_curso_plan');
        $enUso = collect()
            ->merge(DB::table('prematricula.historial_academico')->whereIn('id_curso_plan', $ids)->distinct()->pluck('id_curso_plan'))
            ->merge(DB::table('prematricula.detalle_solicitud')->whereIn('id_curso_plan', $ids)->distinct()->pluck('id_curso_plan'))
            ->merge(DB::table('prematricula.revision_curso')->whereIn('id_curso_plan', $ids)->distinct()->pluck('id_curso_plan'))
            ->map(fn ($id): int => (int) $id)->flip();

        return $filas->mapWithKeys(fn (object $fila): array => [$fila->codigo => (object) [
            'id_curso_plan' => (int) $fila->id_curso_plan,
            'id_curso' => (int) $fila->id_curso,
            'codigo' => $fila->codigo,
            'nombre' => $fila->nombre,
            'creditos' => (int) $fila->creditos,
            'ciclo' => (int) $fila->ciclo,
            'horas_teoria' => (int) $fila->horas_teoria,
            'horas_practica' => (int) $fila->horas_practica,
            'horas_laboratorio' => (int) $fila->horas_laboratorio,
            'estado' => $fila->estado,
            'activo' => CatalogoAcademico::activo($fila->estado),
            'prerrequisitos' => ($requisitos->get($fila->id_curso_plan) ?? collect())->pluck('codigo')->sort(SORT_STRING)->values()->all(),
            'en_uso' => $enUso->has((int) $fila->id_curso_plan),
        ]]);
    }

    /** Huella del catálogo: si cambia entre validar y confirmar, se pide validar otra vez. */
    public function huella(Collection $catalogo): string
    {
        $datos = $catalogo->sortKeys(SORT_STRING)->map(fn (object $curso): array => [
            'codigo' => $curso->codigo,
            ...array_map(fn (string $campo) => $curso->{$campo}, array_combine(self::CAMPOS_ACADEMICOS, self::CAMPOS_ACADEMICOS)),
            'estado' => $curso->estado,
            'prerrequisitos' => $curso->prerrequisitos,
            'en_uso' => $curso->en_uso,
        ])->values()->all();

        return hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
    }

    /**
     * Ejecuta una operación con el catálogo bloqueado para que nadie lo cambie a la vez.
     *
     * @template T
     *
     * @param  Closure(Plan, Collection<string, object>): T  $operacion
     * @return T
     */
    public function transaccion(Closure $operacion): mixed
    {
        return $this->bloqueo->transaccion(function () use ($operacion): mixed {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(?)', [self::BLOQUEO_CATALOGO]);
            }
            $plan = $this->plan();

            return $operacion($plan, $this->catalogo($plan));
        });
    }

    /**
     * Valida filas del Excel o de una edición individual.
     *
     * @param  list<array<string, mixed>>  $crudas
     * @param  Collection<string, object>  $catalogo
     * @param  bool  $permitirProtegidos  solo para la corrección individual confirmada por la coordinación
     * @return array{valido: bool, filas: list<array<string, mixed>>, errores: list<array<string, mixed>>, cambios: list<array<string, mixed>>, huella: string, resumen: array<string, int>}
     */
    public function validar(array $crudas, Collection $catalogo, bool $permitirProtegidos = false): array
    {
        $errores = [];
        $filas = [];
        $cambios = [];
        $vistos = [];
        $grafo = $catalogo->map(fn (object $curso): array => $curso->prerrequisitos)->all();

        foreach ($crudas as $cruda) {
            $numero = $cruda['fila'];
            foreach ($cruda as $campo => $valor) {
                if (is_string($valor)) {
                    $cruda[$campo] = trim($valor);
                }
            }
            if (is_string($cruda['prerrequisitos'] ?? null)) {
                $cruda['prerrequisitos'] = $cruda['prerrequisitos'] === '' ? [] : array_map(trim(...), explode(';', $cruda['prerrequisitos']));
            }
            $cruda['prerrequisitos'] ??= [];

            $validador = Validator::make($cruda, self::reglas(), self::MENSAJES);
            if ($validador->fails()) {
                foreach ($validador->errors()->messages() as $campo => $mensajes) {
                    $errores[] = ['fila' => $numero, 'columna' => explode('.', $campo)[0], 'mensaje' => $mensajes[0]];
                }

                continue;
            }

            $fila = $validador->validated();
            $fila['fila'] = $numero;
            foreach (['creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio'] as $campo) {
                $fila[$campo] = (int) $fila[$campo];
            }
            sort($fila['prerrequisitos'], SORT_STRING);

            if (isset($vistos[$fila['codigo']])) {
                $errores[] = ['fila' => $numero, 'columna' => 'codigo', 'mensaje' => 'Código duplicado; ya aparece en la fila '.$vistos[$fila['codigo']].'.'];
            } else {
                $vistos[$fila['codigo']] = $numero;
            }
            $filas[] = $fila;
            $grafo[$fila['codigo']] = $fila['prerrequisitos'];
        }

        if ($crudas === []) {
            $errores[] = ['fila' => 2, 'columna' => 'codigo', 'mensaje' => 'El archivo no contiene cursos.'];
        }

        foreach ($filas as $fila) {
            foreach ($fila['prerrequisitos'] as $requisito) {
                if ($requisito === $fila['codigo']) {
                    $errores[] = ['fila' => $fila['fila'], 'columna' => 'prerrequisitos', 'mensaje' => 'Un curso no puede ser su propio prerrequisito.'];
                } elseif (! array_key_exists($requisito, $grafo)) {
                    $errores[] = ['fila' => $fila['fila'], 'columna' => 'prerrequisitos', 'mensaje' => 'El prerrequisito '.$requisito.' no existe en la malla ni en el archivo.'];
                }
            }

            $existente = $catalogo->get($fila['codigo']);
            $campos = [];
            foreach (self::CAMPOS_ACADEMICOS as $campo) {
                if ($existente === null || $existente->{$campo} !== $fila[$campo]) {
                    $campos[] = $campo;
                }
            }
            if ($existente === null || $existente->prerrequisitos !== $fila['prerrequisitos']) {
                $campos[] = 'prerrequisitos';
            }

            $protegido = $existente !== null && $existente->en_uso;
            if ($protegido && $campos !== [] && ! $permitirProtegidos) {
                foreach ($campos as $campo) {
                    $errores[] = ['fila' => $fila['fila'], 'columna' => $campo, 'mensaje' => 'El curso ya está utilizado en historial o prematrícula; sus datos académicos y prerrequisitos son inmutables.'];
                }
            }

            $cambios[] = [
                'fila' => $fila['fila'],
                'codigo' => $fila['codigo'],
                'accion' => $existente === null ? 'crear' : ($campos === [] ? 'sin_cambios' : 'actualizar'),
                'campos' => $campos,
                'protegido' => $protegido,
            ];
        }

        $ciclicos = $this->ciclicos($grafo);
        foreach ($filas as $fila) {
            if (isset($ciclicos[$fila['codigo']])) {
                $errores[] = ['fila' => $fila['fila'], 'columna' => 'prerrequisitos', 'mensaje' => 'El curso participa en una dependencia circular.'];
            }
        }
        if ($ciclicos !== [] && array_intersect_key($ciclicos, $vistos) === []) {
            $errores[] = ['fila' => 1, 'columna' => 'prerrequisitos', 'mensaje' => 'La malla existente contiene dependencias circulares.'];
        }

        $acciones = array_count_values(array_column($cambios, 'accion'));

        return [
            'valido' => $errores === [],
            'filas' => $filas,
            'errores' => $errores,
            'cambios' => $cambios,
            'huella' => $this->huella($catalogo),
            'resumen' => [
                'crear' => $acciones['crear'] ?? 0,
                'actualizar' => $acciones['actualizar'] ?? 0,
                'sin_cambios' => $acciones['sin_cambios'] ?? 0,
            ],
        ];
    }

    /**
     * Importa el Excel validado. Si la malla cambió desde la validación, pide validar otra vez.
     *
     * @param  list<array<string, mixed>>  $crudas
     * @return array<string, int>
     */
    public function importar(array $crudas, string $huellaEsperada): array
    {
        return $this->transaccion(function (Plan $plan, Collection $catalogo) use ($crudas, $huellaEsperada): array {
            $reporte = $this->validar($crudas, $catalogo);
            if (! $reporte['valido']) {
                throw new PrematriculaException('El archivo ya no es válido. Vuelve a validarlo.');
            }
            if (! hash_equals($huellaEsperada, $reporte['huella']) && $reporte['resumen']['crear'] + $reporte['resumen']['actualizar'] > 0) {
                throw new PrematriculaException('La malla cambió desde la validación. Vuelve a validar el archivo.');
            }

            $this->persistir($reporte['filas'], $catalogo, $plan);

            return $reporte['resumen'];
        });
    }

    /**
     * Corrige un curso. Un curso en uso solo cambia sus datos académicos con confirmación;
     * el estado siempre se puede cambiar. Las prematrículas afectadas se revalidan.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarCurso(string $codigo, array $datos): object
    {
        return $this->transaccion(function (Plan $plan, Collection $catalogo) use ($codigo, $datos): object {
            $actual = $catalogo->get($codigo) ?? abort(404);
            $confirmado = (bool) ($datos['confirmar_correccion'] ?? false);
            $estado = $datos['estado'] ?? null;
            unset($datos['confirmar_correccion'], $datos['estado']);

            $fila = array_replace(
                ['fila' => 0, 'codigo' => $actual->codigo, 'prerrequisitos' => $actual->prerrequisitos],
                array_map(fn (string $campo) => $actual->{$campo}, array_combine(self::CAMPOS_ACADEMICOS, self::CAMPOS_ACADEMICOS)),
                $datos,
            );
            $reporte = $this->validar([$fila], $catalogo, $confirmado);
            if (! $reporte['valido']) {
                $primero = $reporte['errores'][0];
                $mensaje = $actual->en_uso && ! $confirmado
                    ? 'El curso ya se usa en historial o prematrícula. Marca la confirmación de corrección para cambiar sus datos académicos.'
                    : $primero['mensaje'];

                throw new PrematriculaException($mensaje);
            }

            $academicoCambio = $reporte['cambios'][0]['campos'] !== [];
            $this->persistir($reporte['filas'], $catalogo, $plan);

            $estadoCambio = $estado !== null && $estado !== $actual->estado;
            if ($estadoCambio) {
                DB::table('core.curso')->where('id_curso', $actual->id_curso)->update(['estado' => $estado]);
            }
            if ($estadoCambio || $academicoCambio) {
                $this->revalidarSolicitudesCon($actual->id_curso_plan);
            }

            return $this->catalogo($plan)->get($codigo);
        });
    }

    /** @return list<array<string, mixed>> filas con su número de fila del Excel */
    public function leer(string $ruta): array
    {
        $libro = SimpleXLSX::parse($ruta);
        if ($libro === false) {
            throw new PrematriculaException('No se pudo leer el archivo. Verifica que sea un Excel .xlsx válido.');
        }
        if ($libro->sheetsCount() !== 1) {
            throw new PrematriculaException('El archivo debe contener exactamente una hoja de cálculo.');
        }

        $filas = $libro->rows();
        $encabezado = array_map(fn ($valor): string => mb_strtolower(trim((string) $valor)), array_shift($filas) ?? []);
        if (count(array_unique($encabezado)) !== count(self::COLUMNAS) || array_diff(self::COLUMNAS, $encabezado) !== []) {
            throw new PrematriculaException('La fila 1 debe contener una vez cada columna: '.implode(', ', self::COLUMNAS).'.');
        }

        $resultado = [];
        foreach ($filas as $indice => $fila) {
            if (implode('', array_map(fn ($valor): string => trim((string) $valor), $fila)) === '') {
                continue;
            }
            $registro = ['fila' => $indice + 2];
            foreach (self::COLUMNAS as $columna) {
                $valor = $fila[array_search($columna, $encabezado, true)] ?? '';
                $registro[$columna] = is_string($valor) ? trim($valor) : $valor;
            }
            $resultado[] = $registro;
        }

        return $resultado;
    }

    /** Excel con la malla actual; sin cursos sirve como plantilla. */
    public function excel(?Collection $catalogo = null): string
    {
        $filas = [self::COLUMNAS];
        foreach ($catalogo ?? [] as $curso) {
            $filas[] = [
                "\0".$curso->codigo, "\0".$curso->nombre, $curso->creditos, $curso->ciclo,
                $curso->horas_teoria, $curso->horas_practica, $curso->horas_laboratorio,
                "\0".implode(';', $curso->prerrequisitos),
            ];
        }

        $ruta = tempnam(sys_get_temp_dir(), 'malla');
        SimpleXLSXGen::fromArray($filas, 'Malla')->saveAs($ruta);
        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    /**
     * Crea o actualiza core.curso y core.curso_plan del plan, y sincroniza los prerrequisitos.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  Collection<string, object>  $catalogo
     */
    private function persistir(array $filas, Collection $catalogo, Plan $plan): void
    {
        $idsPlan = $catalogo->map(fn (object $curso): int => $curso->id_curso_plan)->all();

        foreach ($filas as $fila) {
            $datosCurso = [
                'nombre' => $fila['nombre'],
                'creditos' => $fila['creditos'],
                'horas_teoria' => $fila['horas_teoria'],
                'horas_practica' => $fila['horas_practica'],
                'horas_laboratorio' => $fila['horas_laboratorio'],
            ];
            $existente = $catalogo->get($fila['codigo']);

            if ($existente !== null) {
                DB::table('core.curso')->where('id_curso', $existente->id_curso)->update($datosCurso);
                DB::table('core.curso_plan')->where('id_curso_plan', $existente->id_curso_plan)->update(['ciclo' => $fila['ciclo']]);

                continue;
            }

            $idCurso = DB::table('core.curso')->where('codigo', $fila['codigo'])->value('id_curso');
            if ($idCurso === null) {
                $idCurso = DB::table('core.curso')->insertGetId($datosCurso + ['codigo' => $fila['codigo'], 'estado' => self::ACTIVO], 'id_curso');
            } else {
                DB::table('core.curso')->where('id_curso', $idCurso)->update($datosCurso);
            }
            $idsPlan[$fila['codigo']] = (int) DB::table('core.curso_plan')->insertGetId([
                'id_plan' => $plan->id_plan,
                'id_curso' => $idCurso,
                'ciclo' => $fila['ciclo'],
                'tipo' => 'Obligatorio',
                'id_area' => $this->areaPorDefecto(),
            ], 'id_curso_plan');
        }

        foreach ($filas as $fila) {
            $actuales = $catalogo->get($fila['codigo'])?->prerrequisitos ?? [];
            if ($actuales === $fila['prerrequisitos']) {
                continue;
            }

            $idCursoPlan = $idsPlan[$fila['codigo']];
            DB::table('core.curso_prerequisito')->where('id_curso_plan', $idCursoPlan)->delete();
            DB::table('core.curso_prerequisito')->insert(array_map(fn (string $codigo): array => [
                'id_curso_plan' => $idCursoPlan,
                'id_curso_plan_requisito' => $idsPlan[$codigo],
            ], $fila['prerrequisitos']));
        }
    }

    private function areaPorDefecto(): int
    {
        return (int) (DB::table('core.area_formacion')->orderBy('id_area')->value('id_area')
            ?? throw new PrematriculaException('No hay áreas de formación registradas en el portal.'));
    }

    /** Revalida las prematrículas que incluyen el curso en su borrador o en su revisión vigente. */
    private function revalidarSolicitudesCon(int $idCursoPlan): void
    {
        $estudiantes = DB::table('prematricula.solicitud_prematricula as s')
            ->leftJoin('prematricula.detalle_solicitud as d', 'd.id_solicitud', '=', 's.id_solicitud')
            ->leftJoin('prematricula.revision_curso as rc', 'rc.id_revision', '=', 's.id_revision_vigente')
            ->where(fn ($query) => $query->where('d.id_curso_plan', $idCursoPlan)->orWhere('rc.id_curso_plan', $idCursoPlan))
            ->distinct()
            ->pluck('s.id_estudiante')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->revalidacion->paraEstudiantes($estudiantes);
    }

    /**
     * @param  array<string, list<string>>  $grafo
     * @return array<string, true>
     */
    private function ciclicos(array $grafo): array
    {
        $visitados = [];
        $camino = [];
        $posiciones = [];
        $ciclicos = [];
        foreach (array_keys($grafo) as $codigo) {
            $this->visitar((string) $codigo, $grafo, $visitados, $camino, $posiciones, $ciclicos);
        }

        return $ciclicos;
    }

    /**
     * @param  array<string, list<string>>  $grafo
     * @param  array<string, true>  $visitados
     * @param  list<string>  $camino
     * @param  array<string, int>  $posiciones
     * @param  array<string, true>  $ciclicos
     */
    private function visitar(string $codigo, array $grafo, array &$visitados, array &$camino, array &$posiciones, array &$ciclicos): void
    {
        if (isset($posiciones[$codigo])) {
            foreach (array_slice($camino, $posiciones[$codigo]) as $enCiclo) {
                $ciclicos[$enCiclo] = true;
            }

            return;
        }
        if (isset($visitados[$codigo])) {
            return;
        }

        $visitados[$codigo] = true;
        $posiciones[$codigo] = count($camino);
        $camino[] = $codigo;
        foreach ($grafo[$codigo] ?? [] as $requisito) {
            $this->visitar($requisito, $grafo, $visitados, $camino, $posiciones, $ciclicos);
        }
        array_pop($camino);
        unset($posiciones[$codigo]);
    }
}
