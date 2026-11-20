<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/** Consultas de proyecciones por plan; no exponen disponibilidad ni ocupación global. */
final class ConsultaRecursosAcotadosRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function planDeEscuela(int $escuela, int $plan): bool
    {
        return $this->db->table('core.plan_estudios')
            ->where('id_plan', $plan)->where('id_escuela', $escuela)->exists();
    }

    public function periodoVinculado(int $plan, int $periodo): bool
    {
        if (! $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->exists()) {
            return false;
        }
        $conOferta = $this->db->table('gestion_horarios.oferta_curso as o')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')
            ->where('cp.id_plan', $plan)->where('o.id_periodo', $periodo)->exists();
        if ($conOferta) {
            return true;
        }

        return $this->db->table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->where('cp.id_plan', $plan)->where('s.id_periodo', $periodo)->exists();
    }

    /** @param list<int> $docentes */
    public function asignaciones(int $escuela, int $plan, int $periodo, array $docentes): Collection
    {
        if ($docentes === []) {
            return collect();
        }

        return $this->db->table('gestion_horarios.asignacion_docente as ad')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('gestion_horarios.oferta_seccion as os', function ($join) {
                $join->on('os.id_seccion', '=', 's.id_seccion')->where('os.estado', '=', 'VIGENTE');
            })
            ->join('gestion_horarios.oferta_curso as o', function ($join) {
                $join->on('o.id_oferta', '=', 'os.id_oferta')->on('o.id_periodo', '=', 's.id_periodo')->on('o.id_curso_plan', '=', 's.id_curso_plan');
            })
            ->where('p.id_escuela', $escuela)->where('cp.id_plan', $plan)->where('s.id_periodo', $periodo)
            ->where('s.estado', 'ACTIVA')->where('o.estado', 'OFERTADA')->where('ad.estado', 'CONFIRMADA')
            ->whereIn('ad.id_docente', $docentes)
            ->distinct()->orderBy('c.codigo')->orderBy('s.codigo')->orderBy('a.id_actividad')->orderBy('ad.id_asignacion')
            ->get(['ad.id_asignacion', 'ad.id_docente', 'a.id_actividad', 'a.tipo_actividad', 's.id_seccion', 's.codigo as seccion', 'c.codigo as curso_codigo', 'c.nombre as curso_nombre']);
    }

    /** @param list<int> $ambientes */
    public function ambientes(array $ambientes): Collection
    {
        if ($ambientes === []) {
            return collect();
        }

        return $this->db->table('core.ambiente')->whereIn('id_ambiente', $ambientes)
            ->orderBy('codigo')->orderBy('id_ambiente')
            ->get(['id_ambiente', 'codigo', 'nombre', 'tipo', 'capacidad', 'estado']);
    }
}
