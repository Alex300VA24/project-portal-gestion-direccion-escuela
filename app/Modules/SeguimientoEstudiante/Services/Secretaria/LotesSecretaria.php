<?php

namespace App\Modules\SeguimientoEstudiante\Services\Secretaria;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LotesSecretaria
{
    private const LOTES = 'seguimiento_estudiante.recepcion_lote';

    private const REVISIONES = 'seguimiento_estudiante.recepcion_revision';

    public function __construct(
        private readonly PrevalidarRecepcion $lector,
        private readonly ReferenciasRecepcion $referencias,
    ) {}

    public function ambitos(int $persona): Builder
    {
        return DB::table('seguimiento_estudiante.secretaria_ambito as ambito')
            ->join('core.escuela_profesional as escuela', 'escuela.id_escuela', '=', 'ambito.id_escuela')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'ambito.id_periodo')
            ->where('ambito.id_persona', $persona);
    }

    private function accesibles(int $persona): Builder
    {
        return DB::table(self::LOTES.' as lote')
            ->join('core.escuela_profesional as escuela', 'escuela.id_escuela', '=', 'lote.id_escuela')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'lote.id_periodo')
            ->whereExists(DB::table('seguimiento_estudiante.secretaria_ambito as ambito')
                ->where('ambito.id_persona', $persona)
                ->whereColumn('ambito.id_escuela', 'lote.id_escuela')
                ->whereColumn('ambito.id_periodo', 'lote.id_periodo'));
    }

    public function consultar(int $persona, ?int $id = null, ?int $version = null): array
    {
        $ambitos = $this->ambitos($persona)->orderBy('escuela.nombre')->orderByDesc('periodo.fecha_inicio')->get([
            'ambito.id_escuela', 'ambito.id_periodo', 'escuela.nombre as escuela', 'periodo.codigo as periodo',
        ]);
        $lotes = $this->accesibles($persona)->orderByDesc('lote.id_lote')->paginate(20, [
            'lote.id_lote', 'lote.tipo', 'lote.referencia', 'escuela.nombre as escuela', 'periodo.codigo as periodo',
        ]);

        $lote = null;
        $revision = null;
        $versiones = collect();
        $resultado = null;

        if ($id !== null) {
            $lote = $this->accesibles($persona)->where('lote.id_lote', $id)
                ->first(['lote.*', 'escuela.nombre as escuela', 'periodo.codigo as periodo']);
            abort_if($lote === null, 404);
            $revisiones = DB::table(self::REVISIONES)->where('id_lote', $id);
            $versiones = (clone $revisiones)->orderByDesc('version')->paginate(20, ['version', 'fecha_corte'], 'historial');
            $revision = $revisiones->when($version !== null, fn (Builder $consulta) => $consulta->where('version', $version))
                ->orderByDesc('version')->first();
            abort_if($revision === null, 404);

            $guardado = json_decode($revision->resultado, true, flags: JSON_THROW_ON_ERROR);
            $resultado = [
                'filas' => $guardado['filas'],
                'aceptadas' => count($guardado['registros']),
                'errores' => $guardado['errores'],
                'puedeConfirmar' => $revision->confirmado_en === null && $guardado['errores'] === []
                    && $guardado['registros'] !== [] && (int) (clone $revisiones)->max('version') === (int) $revision->version,
            ];
            unset($revision->resultado);
        }

        return compact('ambitos', 'lotes', 'lote', 'revision', 'versiones', 'resultado');
    }

    public function guardar(int $persona, int $cuenta, array $datos, string $bytes): array
    {
        return DB::transaction(function () use ($persona, $cuenta, $datos, $bytes): array {
            $escuela = (int) $datos['id_escuela'];
            $periodo = (int) $datos['id_periodo'];
            abort_if($this->ambitos($persona)->where('ambito.id_escuela', $escuela)
                ->where('ambito.id_periodo', $periodo)->sharedLock()->first(['ambito.id_persona']) === null, 404);

            $clave = [
                'id_escuela' => $escuela,
                'id_periodo' => $periodo,
                'tipo' => $datos['tipo'],
                'referencia' => $datos['referencia'],
            ];
            DB::table(self::LOTES)->insertOrIgnore($clave);
            $lote = DB::table(self::LOTES)->where($clave)->lockForUpdate()->value('id_lote');
            $revisiones = DB::table(self::REVISIONES)->where('id_lote', $lote);
            $sha = hash('sha256', $bytes);
            $corte = $datos['fecha_corte'];
            $resultado = $this->lector->normalizar($datos['tipo'], $bytes);
            $resultado = $this->referencias->comprobar($datos['tipo'], $escuela, $periodo, $resultado);
            $json = json_encode($resultado, JSON_THROW_ON_ERROR);
            $huellaValidacion = hash('sha256', $json);
            $vigente = $revisiones->orderByDesc('version')->first(['version', 'sha256', 'fecha_corte', 'sha256_validacion']);
            if ($vigente && $vigente->sha256 === $sha && $vigente->fecha_corte === $corte && $vigente->sha256_validacion === $huellaValidacion) {
                return [$lote, $vigente->version];
            }

            $version = ((int) ($vigente?->version ?? 0)) + 1;
            DB::table(self::REVISIONES)->insert([
                'id_lote' => $lote, 'version' => $version, 'id_usuario' => $cuenta, 'fecha_corte' => $corte,
                'sha256' => $sha, 'sha256_validacion' => $huellaValidacion, 'resultado' => $json,
            ]);

            return [$lote, $version];
        });
    }

    public function confirmar(int $persona, int $cuenta, int $id, int $version): void
    {
        DB::transaction(function () use ($persona, $cuenta, $id, $version): void {
            $lote = $this->accesibles($persona)->where('lote.id_lote', $id)
                ->lock('for update of lote')->first(['lote.*']);
            abort_if($lote === null, 404);
            abort_if($this->ambitos($persona)->where('ambito.id_escuela', $lote->id_escuela)
                ->where('ambito.id_periodo', $lote->id_periodo)->sharedLock()->first(['ambito.id_persona']) === null, 404);

            $revisiones = DB::table(self::REVISIONES)->where('id_lote', $id);
            $revision = (clone $revisiones)->orderByDesc('version')->first();
            if ($revision === null || (int) $revision->version !== $version) {
                throw ValidationException::withMessages(['revision' => 'La revisión cambió. Abre la revisión más reciente.']);
            }
            if ($revision->confirmado_en !== null) {
                return;
            }

            $guardado = json_decode($revision->resultado, true, flags: JSON_THROW_ON_ERROR);
            $actual = $this->referencias->comprobar($lote->tipo, $lote->id_escuela, $lote->id_periodo, $guardado);
            if ($actual['errores'] !== [] || $actual['registros'] === [] || $actual['registros'] != $guardado['registros']) {
                throw ValidationException::withMessages(['revision' => 'Hay errores o cambiaron las referencias. Corrige o vuelve a validar el CSV.']);
            }
            $revisiones->where('version', $version)->update([
                'confirmado_por' => $cuenta, 'confirmado_en' => now('America/Lima')->toIso8601String(),
            ]);
        });
    }
}
