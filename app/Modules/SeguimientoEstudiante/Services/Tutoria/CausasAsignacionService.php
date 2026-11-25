<?php

namespace App\Modules\SeguimientoEstudiante\Services\Tutoria;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CausasAsignacionService
{
    private const TABLE = 'seguimiento_estudiante.operacion';

    public function __construct(private readonly AsignacionTutoriaService $asignaciones) {}

    public function consultar(int $escuela, int $periodo): array
    {
        $datos = $this->consulta($escuela, $periodo)->value('datos');

        return $datos === null
            ? ['revision' => 0, 'activa' => null, 'versiones' => []]
            : json_decode($datos, true, flags: JSON_THROW_ON_ERROR);
    }

    public function guardar(int $persona, int $cuenta, int $escuela, int $periodo, array $datos): void
    {
        DB::transaction(function () use ($persona, $cuenta, $escuela, $periodo, $datos): void {
            $ambito = $this->asignaciones->ambitos($persona)->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo)
                ->sharedLock()->first(['ambito.id_persona']);
            throw_if($ambito === null, ModelNotFoundException::class);
            DB::table(self::TABLE)->insertOrIgnore([
                'id_escuela' => $escuela,
                'clave' => "causas-asignacion|{$periodo}",
                'datos' => '{"revision":0,"activa":null,"versiones":[]}',
            ]);
            $consulta = $this->consulta($escuela, $periodo);
            $estado = json_decode((clone $consulta)->lockForUpdate()->value('datos'), true, flags: JSON_THROW_ON_ERROR);
            if ($estado['revision'] !== (int) $datos['revision']) {
                throw ValidationException::withMessages(['revision' => 'El catálogo cambió. Recarga la página.']);
            }
            $version = (string) Str::uuid();
            $estado['revision']++;
            $estado['versiones'][$version] = [
                'version' => $version,
                'revision' => $estado['revision'],
                'estado' => $datos['estado'],
                'referencia' => $datos['referencia'],
                'autoridad' => $datos['autoridad'] ?? null,
                'fecha_aprobacion' => $datos['fecha_aprobacion'] ?? null,
                'motivo' => $datos['motivo'],
                'causas' => array_values(array_map(
                    fn (array $causa): array => array_replace($causa, ['habilitada' => (bool) $causa['habilitada']]),
                    $datos['causas'],
                )),
                'id_usuario' => $cuenta,
                'registrado_en' => now()->toIso8601String(),
            ];
            if ($datos['estado'] === 'VIGENTE') {
                $estado['activa'] = $version;
            }
            $consulta->update(['datos' => json_encode($estado, JSON_THROW_ON_ERROR), 'actualizado_en' => now()]);
        });
    }

    public function resolver(int $escuela, int $periodo, string $version, string $codigo): array
    {
        $datos = $this->consulta($escuela, $periodo)->sharedLock()->value('datos');
        $estado = $datos === null ? [] : json_decode($datos, true, flags: JSON_THROW_ON_ERROR);
        if (($estado['activa'] ?? null) !== $version) {
            throw ValidationException::withMessages(['version_causa' => 'Consulta la versión vigente del catálogo.']);
        }
        $causa = collect($estado['versiones'][$version]['causas'])->firstWhere('codigo', $codigo);
        if ($causa === null || ! $causa['habilitada']) {
            throw ValidationException::withMessages(['causa' => 'Selecciona una causa habilitada.']);
        }

        return $causa;
    }

    private function consulta(int $escuela, int $periodo): Builder
    {
        return DB::table(self::TABLE)->where('id_escuela', $escuela)->where('clave', "causas-asignacion|{$periodo}");
    }
}
