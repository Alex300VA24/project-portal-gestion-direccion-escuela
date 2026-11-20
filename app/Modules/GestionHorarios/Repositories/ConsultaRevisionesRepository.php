<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaRevisionesRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function pendientes(array $escuelas): Collection
    {
        if (! array_is_list($escuelas) || ! $escuelas || count(array_filter($escuelas, fn ($id) => is_int($id) && $id > 0)) !== count($escuelas)) {
            return collect();
        }
        $revisiones = $this->db->table('gestion_horarios.revision_oferta_pendiente as r')
            ->join('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'r.id_oferta')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'o.id_periodo')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->whereIn('pe.id_escuela', $escuelas)
            ->orderByDesc('r.creada_en')->orderByDesc('r.id_revision')->limit(200)
            ->get(['r.id_revision', 'r.motivo', 'r.creada_en', 'o.id_oferta', 'o.id_periodo', 'o.id_curso_plan', 'o.tipo_oferta', 'o.estado as estado_oferta', 'p.codigo as periodo', 'c.codigo as codigo_curso', 'c.nombre']);

        $estados = $this->db->table('core.seccion as s')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_seccion', '=', 's.id_seccion')
            ->join('gestion_horarios.detalle_horario as d', 'd.id_actividad', '=', 'a.id_actividad')
            ->join('gestion_horarios.horario as h', function ($join) {
                $join->on('h.id_horario', '=', 'd.id_horario')->on('h.id_periodo', '=', 's.id_periodo');
            })
            ->whereIn('s.id_periodo', $revisiones->pluck('id_periodo')->unique()->all())
            ->whereIn('s.id_curso_plan', $revisiones->pluck('id_curso_plan')->unique()->all())
            ->whereIn('pe.id_escuela', $escuelas)
            ->select('s.id_periodo', 's.id_curso_plan', 'h.estado')->distinct()->get()
            ->groupBy(fn ($fila) => $fila->id_periodo.':'.$fila->id_curso_plan);

        return $revisiones->map(function ($revision) use ($estados) {
            $revision->estados_horario = $estados->get($revision->id_periodo.':'.$revision->id_curso_plan, collect())->pluck('estado')->sort()->values()->all();

            return $revision;
        });
    }
}
