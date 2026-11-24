<?php

namespace App\Modules\SeguimientoEstudiante\Services\Tutoria;

use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlanTutoriaService
{
    private const TABLE = 'seguimiento_estudiante.operacion';

    public const FIELDS = [
        'foundation' => 'Fundamentación',
        'needs' => 'Necesidades identificadas',
        'objectives' => 'Objetivos',
        'evaluation' => 'Evaluación de resultados',
        'improvement' => 'Mejora y continuidad',
    ];

    public function __construct(
        private readonly ExpedientePolicy $policy,
        private readonly AsignacionTutoriaService $asignaciones,
    ) {}

    private function asignaciones(int $persona): Builder
    {
        return $this->policy->asignacionesVigentes($persona)
            ->join('core.estudiante as estudiante', 'estudiante.id_estudiante', '=', 'asignacion.id_estudiante')
            ->join('core.escuela_profesional as escuela', 'escuela.id_escuela', '=', 'estudiante.id_escuela')
            ->where('estudiante.estado', 'Activo');
    }

    public function ambitos(int $persona): array
    {
        return $this->asignaciones($persona)
            ->select('escuela.id_escuela', 'escuela.nombre as escuela', 'periodo.id_periodo', 'periodo.codigo as periodo')
            ->distinct()
            ->orderBy('escuela', 'asc')
            ->orderBy('periodo', 'asc')
            ->get()
            ->all();
    }

    private function enAmbito(int $persona, int $escuela, int $periodo): Builder
    {
        return $this->asignaciones($persona)
            ->where('estudiante.id_escuela', $escuela)
            ->where('asignacion.id_periodo', $periodo);
    }

    private function documento(int $persona, int $escuela, int $periodo): Builder
    {
        return DB::table(self::TABLE)->where('id_escuela', $escuela)->where('clave', "plan-tutoria|{$periodo}|{$persona}");
    }

    private function vacio(): array
    {
        return [
            'revision' => 0, 'status' => 'SIN_GUARDAR',
            'fields' => array_fill_keys(array_keys(self::FIELDS), ''), 'activities' => [],
            'versions' => [], 'operations' => [],
        ];
    }

    public function consultar(int $persona, int $escuela, int $periodo): array
    {
        $ambito = $this->enAmbito($persona, $escuela, $periodo)
            ->select('escuela.nombre as escuela', 'periodo.codigo as periodo')
            ->first();
        throw_if($ambito === null, ModelNotFoundException::class);
        $datos = $this->documento($persona, $escuela, $periodo)->value('datos');
        $plan = $datos === null ? $this->vacio() : json_decode($datos, true, flags: JSON_THROW_ON_ERROR);
        unset($plan['operations']);

        return compact('ambito', 'plan');
    }

    public function guardar(int $persona, int $cuenta, int $escuela, int $periodo, array $input): void
    {
        DB::transaction(function () use ($persona, $cuenta, $escuela, $periodo, $input): void {
            $estudiantes = $this->enAmbito($persona, $escuela, $periodo)
                ->select('estudiante.id_estudiante')
                ->sharedLock()
                ->pluck('id_estudiante')
                ->unique();
            throw_if($estudiantes->isEmpty(), ModelNotFoundException::class);

            DB::table(self::TABLE)->insertOrIgnore([
                'id_escuela' => $escuela, 'clave' => "plan-tutoria|{$periodo}|{$persona}",
                'datos' => json_encode($this->vacio(), JSON_THROW_ON_ERROR),
            ]);
            $documento = $this->documento($persona, $escuela, $periodo);
            $plan = json_decode((clone $documento)->lockForUpdate()->value('datos'), true, flags: JSON_THROW_ON_ERROR);
            $huella = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
            $envio = $input['request_id'];
            if (isset($plan['operations'][$envio])) {
                if ($plan['operations'][$envio] !== $huella) {
                    throw ValidationException::withMessages(['version' => 'Ese envío ya corresponde a otro contenido. Recarga el plan.']);
                }

                return;
            }
            if ($plan['revision'] !== $input['version']) {
                throw ValidationException::withMessages(['version' => 'Hay una versión más reciente del plan. Recarga la página antes de guardar.']);
            }

            $plan['revision']++;
            $plan['status'] = $input['action'] === 'PRESENTAR' ? 'PRESENTADO' : 'BORRADOR';
            $plan['fields'] = $input['fields'];
            $plan['activities'] = $input['activities'];
            $plan['population'] = $estudiantes->count();
            $plan['updated_at'] = now('America/Lima')->toIso8601String();
            $plan['versions'][] = [
                'version' => $plan['revision'], 'action' => $input['action'], 'date' => $plan['updated_at'],
                'actor' => $persona, 'id_usuario' => $cuenta,
                'fields' => $plan['fields'], 'activities' => $plan['activities'], 'population' => $plan['population'],
            ];
            if ($input['action'] === 'PRESENTAR') {
                $plan['submitted_version'] = $plan['revision'];
            }
            $plan['operations'][$envio] = $huella;
            $documento->update([
                'datos' => json_encode($plan, JSON_THROW_ON_ERROR), 'actualizado_en' => $plan['updated_at'],
            ]);
        });
    }

    private function coordinacion(int $persona, int $escuela, int $periodo): Builder
    {
        return $this->asignaciones->ambitos($persona)
            ->where('ambito.id_escuela', $escuela)->where('ambito.id_periodo', $periodo);
    }

    public function presentados(int $persona, int $escuela, int $periodo): array
    {
        $ambito = $this->coordinacion($persona, $escuela, $periodo)->first(['escuela.nombre as escuela', 'periodo.codigo as periodo']);
        throw_if($ambito === null, ModelNotFoundException::class);
        $planes = DB::table(self::TABLE.' as plan')
            ->join('core.persona as autor', function (JoinClause $join): void {
                $join->whereRaw("autor.id_persona::text = split_part(plan.clave, '|', 3)");
            })
            ->where('plan.id_escuela', $escuela)
            ->where('plan.clave', 'like', "plan-tutoria|{$periodo}|%")
            ->whereRaw("plan.datos->>'submitted_version' IS NOT NULL")
            ->orderByRaw("(plan.datos->>'status' = 'PRESENTADO') DESC")
            ->orderBy('autor.apellido_paterno')->orderBy('autor.id_persona')
            ->paginate(12, ['plan.datos', 'autor.id_persona', 'autor.nombres', 'autor.apellido_paterno', 'autor.apellido_materno'])
            ->through(function ($fila) use ($persona): array {
                $plan = json_decode($fila->datos, true, flags: JSON_THROW_ON_ERROR);
                $presentado = collect($plan['versions'])->firstWhere('version', $plan['submitted_version']);

                return [
                    'id_persona' => $fila->id_persona,
                    'nombre' => trim($fila->nombres.' '.$fila->apellido_paterno.' '.$fila->apellido_materno),
                    'revision' => $plan['revision'],
                    'presentado' => $presentado,
                    'pendiente' => $plan['status'] === 'PRESENTADO' && (int) $fila->id_persona !== $persona,
                ];
            });

        return compact('ambito', 'planes');
    }

    public function revisar(int $persona, int $cuenta, int $escuela, int $periodo, int $autor, array $datos): void
    {
        DB::transaction(function () use ($persona, $cuenta, $escuela, $periodo, $autor, $datos): void {
            $ambito = $this->coordinacion($persona, $escuela, $periodo)->sharedLock()->first(['ambito.id_persona']);
            throw_if($ambito === null || $persona === $autor, ModelNotFoundException::class);
            $documento = $this->documento($autor, $escuela, $periodo);
            $json = (clone $documento)->lockForUpdate()->value('datos');
            throw_if($json === null, ModelNotFoundException::class);
            $plan = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            throw_unless(isset($plan['submitted_version']), ModelNotFoundException::class);

            if ($plan['revision'] !== (int) $datos['version'] || $plan['status'] !== 'PRESENTADO'
                || $plan['submitted_version'] !== (int) $datos['version_presentada']) {
                throw ValidationException::withMessages(['version' => 'El aporte cambió o ya fue revisado. Recarga la página.']);
            }

            $plan['revision']++;
            $plan['status'] = $datos['decision'];
            $plan['updated_at'] = now('America/Lima')->toIso8601String();
            $revision = [
                'version' => $plan['submitted_version'],
                'id_usuario' => $cuenta,
                'actor' => $persona,
                'decision' => $datos['decision'],
                'comment' => $datos['comentario'],
                'date' => $plan['updated_at'],
            ];
            foreach ($plan['versions'] as $indice => $version) {
                if ($version['version'] === $plan['submitted_version']) {
                    $plan['versions'][$indice]['review'] = $revision;
                    break;
                }
            }
            $plan['review'] = $revision;
            $documento->update([
                'datos' => json_encode($plan, JSON_THROW_ON_ERROR), 'actualizado_en' => $plan['updated_at'],
            ]);
        });
    }
}
