<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaAsignacionDocenteRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function docentes(string $buscar, ?int $periodo = null, ?int $plan = null): Collection
    {
        return $this->db->table('core.docente as doc')->leftJoin('core.persona as p', 'p.id_persona', '=', 'doc.id_persona')
            ->when($plan !== null, fn ($q) => $q->whereExists(function ($q) use ($periodo, $plan) {
                $q->selectRaw('1')->from('gestion_horarios.asignacion_docente as asignacion')
                    ->join('gestion_horarios.actividad_academica as actividad', 'actividad.id_actividad', '=', 'asignacion.id_actividad')
                    ->join('core.seccion as seccion', 'seccion.id_seccion', '=', 'actividad.id_seccion')
                    ->join('core.curso_plan as curso_plan', 'curso_plan.id_curso_plan', '=', 'seccion.id_curso_plan')
                    ->whereColumn('asignacion.id_docente', 'doc.id_docente')
                    ->whereIn('asignacion.estado', ['PROPUESTA', 'CONFIRMADA'])
                    ->where('seccion.id_periodo', $periodo)->where('curso_plan.id_plan', $plan);
            }))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('doc.codigo_docente', 'ilike', '%'.$buscar.'%')->orWhere('p.numero_documento', 'ilike', '%'.$buscar.'%')->orWhereRaw("concat_ws(' ', p.nombres, p.apellido_paterno, p.apellido_materno) ILIKE ?", ['%'.$buscar.'%'])->orWhereRaw("concat_ws(' ', p.apellido_paterno, p.apellido_materno, p.nombres) ILIKE ?", ['%'.$buscar.'%'])))
            ->orderBy('p.apellido_paterno')->orderBy('p.nombres')->orderBy('doc.id_docente')
            ->get(['doc.id_docente', 'doc.codigo_docente', 'doc.estado', 'doc.horas_maximas', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno']);
    }

    public function actividades(int $periodo, string $ciclo, string $seccion, ?int $plan = null): Collection
    {
        return $this->db->table('gestion_horarios.actividad_academica as a')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->leftJoin('gestion_horarios.asignacion_docente as ad', 'ad.id_actividad', '=', 'a.id_actividad')
            ->leftJoin('core.docente as doc', 'doc.id_docente', '=', 'ad.id_docente')
            ->leftJoin('core.persona as p', 'p.id_persona', '=', 'doc.id_persona')
            ->where('s.id_periodo', $periodo)
            ->when($plan !== null, fn ($q) => $q->where('cp.id_plan', $plan))
            ->when($ciclo !== '' && ctype_digit($ciclo), fn ($q) => $q->where('cp.ciclo', (int) $ciclo))
            ->when($seccion !== '', fn ($q) => $q->where('s.codigo', 'ilike', '%'.$seccion.'%'))
            ->orderBy('cp.ciclo')->orderBy('c.nombre')->orderBy('s.codigo')->orderBy('a.id_actividad')->orderBy('ad.id_asignacion')
            ->get(['a.id_actividad', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 's.codigo as seccion', 'cp.ciclo', 'c.codigo as curso_codigo', 'c.nombre as curso_nombre', 'ad.id_asignacion', 'ad.id_docente', 'ad.estado as estado_asignacion', 'doc.codigo_docente', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno']);
    }

    /** Actividades confirmadas distintas para evitar contar una actividad repetida dos veces por docente. */
    public function cargaConfirmada(int $periodo, ?int $plan = null): Collection
    {
        return $this->db->table('gestion_horarios.asignacion_docente as ad')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->where('s.id_periodo', $periodo)->where('ad.estado', 'CONFIRMADA')
            ->when($plan !== null, fn ($q) => $q->where('cp.id_plan', $plan))
            ->select('ad.id_docente', 'a.id_actividad', 'a.hora_inicio', 'a.hora_fin')->distinct()->get();
    }
}
