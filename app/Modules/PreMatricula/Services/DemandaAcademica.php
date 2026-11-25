<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demanda estimada por curso. Cuenta solo la revisión vigente de cada prematrícula
 * registrada; los borradores no suman y una corrección en curso conserva su registro anterior.
 */
final class DemandaAcademica
{
    public const AVISO = 'Estimación basada en prematrículas registradas; no es la matrícula definitiva.';

    /** @return array{total_estudiantes: int, total_registros: int, ciclos: Collection<int, object>} */
    public function resumen(int $idPeriodo): array
    {
        $base = $this->selecciones($idPeriodo);

        return [
            'total_estudiantes' => (int) (clone $base)->distinct()->count('s.id_estudiante'),
            'total_registros' => (int) DB::table('prematricula.solicitud_prematricula')->where('id_periodo', $idPeriodo)->whereNotNull('id_revision_vigente')->count(),
            'ciclos' => (clone $base)->selectRaw('cp.ciclo, COUNT(DISTINCT s.id_estudiante)::int AS estudiantes')
                ->groupBy('cp.ciclo')->orderBy('cp.ciclo')->get(),
        ];
    }

    /** @return Collection<int, object> */
    public function porCurso(int $idPeriodo, ?int $ciclo = null, ?string $buscar = null): Collection
    {
        return $this->selecciones($idPeriodo)
            ->when($ciclo !== null, fn (Builder $query) => $query->where('cp.ciclo', $ciclo))
            ->when($buscar !== null && $buscar !== '', function (Builder $query) use ($buscar): void {
                $patron = '%'.addcslashes($buscar, '\\%_').'%';
                $query->where(fn (Builder $q) => $q->whereLike('c.codigo', $patron)->orWhereLike('c.nombre', $patron));
            })
            ->groupBy('rc.id_curso_plan', 'c.codigo', 'c.nombre', 'cp.ciclo', 'c.creditos')
            ->select(['rc.id_curso_plan', 'c.codigo', 'c.nombre', 'cp.ciclo', 'c.creditos'])
            ->selectRaw('COUNT(DISTINCT s.id_estudiante)::int AS estudiantes')
            ->selectRaw("COUNT(DISTINCT s.id_estudiante) FILTER (WHERE rc.preferencia_turno = 'MANANA')::int AS manana")
            ->selectRaw("COUNT(DISTINCT s.id_estudiante) FILTER (WHERE rc.preferencia_turno = 'TARDE')::int AS tarde")
            ->selectRaw("COUNT(*) FILTER (WHERE val.estado = 'VALIDA')::int AS valida")
            ->selectRaw("COUNT(*) FILTER (WHERE val.estado = 'PENDIENTE_RESULTADOS')::int AS pendiente")
            ->selectRaw("COUNT(*) FILTER (WHERE val.estado = 'REQUIERE_AJUSTE' OR s.id_cronograma_correccion IS NOT NULL)::int AS requiere_ajuste")
            ->orderByDesc('estudiantes')
            ->orderBy('c.codigo')
            ->get();
    }

    /** @return Collection<int, object> */
    public function interesados(int $idPeriodo, int $idCursoPlan): Collection
    {
        return $this->selecciones($idPeriodo)
            ->where('rc.id_curso_plan', $idCursoPlan)
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 's.id_estudiante')
            ->leftJoin('core.persona as pe', 'pe.id_persona', '=', 'e.id_persona')
            ->select(['e.codigo_universitario', 'pe.nombres', 'pe.apellido_paterno', 'pe.apellido_materno', 'r.enviada_en', 'r.numero_revision'])
            ->selectRaw("COALESCE(val.estado, 'SIN_VALIDACION') AS validacion")
            ->orderBy('pe.apellido_paterno')
            ->orderBy('pe.nombres')
            ->get();
    }

    public function csv(int $idPeriodo): string
    {
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['codigo', 'curso', 'ciclo', 'creditos', 'estudiantes', 'validos', 'pendientes_de_resultados', 'requieren_ajuste'], escape: '');
        foreach ($this->porCurso($idPeriodo) as $fila) {
            fputcsv($salida, [$fila->codigo, $fila->nombre, $fila->ciclo, (float) $fila->creditos, $fila->estudiantes, $fila->valida, $fila->pendiente, $fila->requiere_ajuste], escape: '');
        }
        rewind($salida);

        return (string) stream_get_contents($salida);
    }

    /** Una fila por curso registrado en la revisión vigente de cada prematrícula del período. */
    private function selecciones(int $idPeriodo): Builder
    {
        return DB::table('prematricula.solicitud_prematricula as s')
            ->join('prematricula.revision_solicitud as r', function ($join): void {
                $join->on('r.id_revision', '=', 's.id_revision_vigente')
                    ->on('r.id_solicitud', '=', 's.id_solicitud')
                    ->where('r.motivo', '=', RevisionSolicitud::MOTIVO_ENVIO);
            })
            ->join('prematricula.revision_curso as rc', 'rc.id_revision', '=', 'r.id_revision')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'rc.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->leftJoin('prematricula.validacion_solicitud as v', 'v.id_solicitud', '=', 's.id_solicitud')
            ->leftJoin(DB::raw("LATERAL (
                SELECT curso->>'estado' AS estado
                FROM jsonb_array_elements(CASE WHEN jsonb_typeof(v.cursos) = 'array' THEN v.cursos ELSE '[]'::jsonb END) AS curso
                WHERE (curso->>'id_curso_plan')::bigint = rc.id_curso_plan
                LIMIT 1
            ) AS val"), DB::raw('true'), '=', DB::raw('true'))
            ->where('s.id_periodo', $idPeriodo)
            ->whereIn('s.estado', [SolicitudPrematricula::ESTADO_ENVIADA, SolicitudPrematricula::ESTADO_BORRADOR]);
    }
}
