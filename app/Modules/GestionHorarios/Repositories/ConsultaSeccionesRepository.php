<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaSeccionesRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function cursos(int $periodo, ?int $demanda, string $buscar, string $ciclo, ?int $plan = null): Collection
    {
        $ids = $this->db->table('core.seccion')->where('id_periodo', $periodo)->select('id_curso_plan')
            ->union($this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->select('id_curso_plan'));
        if ($demanda !== null) {
            $ids->union($this->db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda)->select('id_curso_plan'));
        }

        $cursos = $this->db->query()->fromSub($ids, 'ids')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'ids.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->when($plan !== null, fn ($q) => $q->where('cp.id_plan', $plan))
            ->leftJoin('gestion_horarios.demanda_curso as d', function ($join) use ($demanda) {
                $join->on('d.id_curso_plan', '=', 'cp.id_curso_plan')->where('d.id_demanda', '=', $demanda);
            })
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('c.codigo', 'ilike', '%'.$buscar.'%')->orWhere('c.nombre', 'ilike', '%'.$buscar.'%')
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('core.seccion as busqueda')->whereColumn('busqueda.id_curso_plan', 'cp.id_curso_plan')->where('busqueda.id_periodo', $periodo)->where('busqueda.codigo', 'ilike', '%'.$buscar.'%'))))
            ->when($ciclo !== '' && ctype_digit($ciclo), fn ($q) => $q->where('cp.ciclo', (int) $ciclo))
            ->orderBy('cp.ciclo')->orderBy('c.nombre')
            ->get(['cp.id_curso_plan', 'cp.ciclo', 'c.codigo', 'c.nombre', 'd.total', 'd.manana', 'd.tarde']);

        $cursoIds = $cursos->pluck('id_curso_plan')->all();
        $secciones = $this->db->table('core.seccion as s')
            ->where('s.id_periodo', $periodo)->whereIn('s.id_curso_plan', $cursoIds)
            ->orderBy('s.codigo')->get(['s.id_seccion', 's.id_curso_plan', 's.codigo', 's.capacidad', 's.estado'])
            ->groupBy('id_curso_plan');
        $ofertas = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->whereIn('id_curso_plan', $cursoIds)
            ->orderBy('tipo_oferta')->get(['id_oferta', 'id_curso_plan', 'tipo_oferta', 'estado'])->groupBy('id_curso_plan');
        $asociaciones = collect();
        if ($this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion')) {
            $asociaciones = $this->db->table('gestion_horarios.oferta_seccion as os')
                ->join('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')
                ->join('core.seccion as s', 's.id_seccion', '=', 'os.id_seccion')
                ->where('s.id_periodo', $periodo)->whereIn('s.id_curso_plan', $cursoIds)
                ->where('o.id_periodo', $periodo)->whereColumn('o.id_curso_plan', 's.id_curso_plan')
                ->orderByDesc('os.id_oferta_seccion')
                ->get(['os.id_oferta_seccion', 'os.id_seccion', 'os.id_oferta', 'os.estado', 'os.registrada_en', 'os.cerrada_en', 'o.tipo_oferta', 'o.id_periodo', 'o.id_curso_plan'])->groupBy('id_seccion');
        }
        foreach ($secciones->flatten() as $seccion) {
            $seccion->asociaciones = $asociaciones->get($seccion->id_seccion, collect());
        }
        $actividades = $this->db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->where('s.id_periodo', $periodo)->whereIn('s.id_curso_plan', $cursoIds)
            ->select('s.id_curso_plan', 'a.tipo_actividad')->distinct()->get()->groupBy('id_curso_plan');

        return $cursos->map(function ($curso) use ($secciones, $ofertas, $actividades) {
            $curso->secciones = $secciones->get($curso->id_curso_plan, collect());
            $curso->ofertas = $ofertas->get($curso->id_curso_plan, collect());
            $curso->actividades = $actividades->get($curso->id_curso_plan, collect())->pluck('tipo_actividad')->sort()->values()->all();

            return $curso;
        });
    }
}
