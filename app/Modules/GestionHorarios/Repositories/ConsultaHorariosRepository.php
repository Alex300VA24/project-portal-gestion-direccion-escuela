<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaHorariosRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function horarios(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->orderByDesc('version')->get(['id_horario', 'ambito', 'version', 'estado']);
    }

    public function sesiones(int $periodo, int $horario, string $ciclo, string $seccion): Collection
    {
        return $this->db->table('gestion_horarios.detalle_horario as d')
            ->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->leftJoin('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->leftJoin('core.escuela_profesional as esc', 'esc.id_escuela', '=', 'pe.id_escuela')
            ->leftJoin('core.facultad as fac', 'fac.id_facultad', '=', 'esc.id_facultad')
            ->leftJoin('core.ambiente as am', 'am.id_ambiente', '=', 'd.id_ambiente')
            ->where('h.id_periodo', $periodo)->where('s.id_periodo', $periodo)->where('h.id_horario', $horario)
            ->when($ciclo !== '' && ctype_digit($ciclo), fn ($q) => $q->where('cp.ciclo', (int) $ciclo))
            ->when($seccion !== '', fn ($q) => $q->where('s.codigo', $seccion))
            ->orderBy('a.hora_inicio')->orderBy('a.id_actividad')
            ->get(['d.id_detalle', 'a.id_actividad', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 's.codigo as seccion', 'cp.id_plan', 'cp.ciclo', 'pe.nombre as plan_nombre', 'esc.nombre as escuela_nombre', 'fac.nombre as facultad_nombre', 'c.codigo as curso_codigo', 'c.nombre as curso_nombre', 'am.nombre as ambiente']);
    }

    public function docentes(Collection $sesiones): Collection
    {
        if ($sesiones->isEmpty()) {
            return collect();
        }

        return $this->db->table('gestion_horarios.asignacion_docente as ad')
            ->join('core.docente as doc', 'doc.id_docente', '=', 'ad.id_docente')
            ->join('core.persona as p', 'p.id_persona', '=', 'doc.id_persona')
            ->whereIn('ad.id_actividad', $sesiones->pluck('id_actividad'))->where('ad.estado', 'CONFIRMADA')
            ->orderBy('ad.id_asignacion')->get(['ad.id_actividad', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno'])->groupBy('id_actividad');
    }
}
