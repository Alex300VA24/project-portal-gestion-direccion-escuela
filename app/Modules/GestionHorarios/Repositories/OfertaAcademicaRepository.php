<?php

namespace App\Modules\GestionHorarios\Repositories;

use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class OfertaAcademicaRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function periodos(): Collection
    {
        return $this->db->table('core.periodo_academico')->orderByDesc('fecha_inicio')->get(['id_periodo', 'codigo']);
    }

    /** Periodos con oferta, sección o demanda del plan; no atribuye otros periodos al plan. */
    public function periodosParaPlan(int $plan): Collection
    {
        return $this->db->table('core.periodo_academico as p')
            ->where(function ($q) use ($plan) {
                $q->whereExists(function ($sub) use ($plan) {
                    $sub->selectRaw('1')->from('gestion_horarios.oferta_curso as o')
                        ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')
                        ->whereColumn('o.id_periodo', 'p.id_periodo')->where('cp.id_plan', $plan);
                })->orWhereExists(function ($sub) use ($plan) {
                    $sub->selectRaw('1')->from('core.seccion as s')
                        ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
                        ->whereColumn('s.id_periodo', 'p.id_periodo')->where('cp.id_plan', $plan);
                });
                if (! (new RepositorioDemandaPostgres($this->db))->disponible()) {
                    return;
                }
                $q->orWhereExists(function ($sub) use ($plan) {
                    $sub->selectRaw('1')->from('gestion_horarios.demanda_instantanea as di')
                        ->join('gestion_horarios.demanda_curso as dc', 'dc.id_demanda', '=', 'di.id_demanda')
                        ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'dc.id_curso_plan')
                        ->whereColumn('di.id_periodo', 'p.id_periodo')->where('cp.id_plan', $plan);
                });
            })->orderByDesc('p.fecha_inicio')->get(['p.id_periodo', 'p.codigo']);
    }

    public function catalogo(?int $plan = null): Collection
    {
        return $this->db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->when($plan !== null, fn ($q) => $q->where('cp.id_plan', $plan))->orderBy('c.nombre')->get(['cp.id_curso_plan', 'c.codigo', 'c.nombre', 'cp.ciclo']);
    }

    public function cursos(int $periodo, ?int $demanda, string $buscar, string $ciclo, string $estado, string $tipo = '', ?int $plan = null): Collection
    {
        $ids = $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->select('id_curso_plan');
        if ($demanda !== null) {
            $ids->union($this->db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda)->select('id_curso_plan'));
        }

        return $this->db->query()->fromSub($ids, 'ids')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'ids.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->when($plan !== null, fn ($q) => $q->where('cp.id_plan', $plan))
            ->leftJoin('gestion_horarios.oferta_curso as o', function ($join) use ($periodo) {
                $join->on('o.id_curso_plan', '=', 'cp.id_curso_plan')->where('o.id_periodo', '=', $periodo);
            })
            ->when($demanda !== null, fn ($q) => $q->leftJoin('gestion_horarios.demanda_curso as d', function ($join) use ($demanda) {
                $join->on('d.id_curso_plan', '=', 'cp.id_curso_plan')->where('d.id_demanda', '=', $demanda);
            }))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('c.codigo', 'ilike', '%'.$buscar.'%')->orWhere('c.nombre', 'ilike', '%'.$buscar.'%')))
            ->when($ciclo !== '' && ctype_digit($ciclo), fn ($q) => $q->where('cp.ciclo', (int) $ciclo))
            ->when(in_array($estado, ['PLANIFICADA', 'OFERTADA', 'CERRADA'], true), fn ($q) => $q->where('o.estado', $estado))
            ->when(in_array($tipo, ['REGULAR', 'REPETICION'], true), fn ($q) => $q->where('o.tipo_oferta', $tipo))
            ->orderBy('cp.ciclo')->orderBy('c.nombre')->orderBy('o.tipo_oferta')
            ->select(['cp.id_curso_plan', 'cp.ciclo', 'c.codigo', 'c.nombre', 'o.id_oferta', 'o.tipo_oferta', 'o.estado', 'o.docentes_minimos'])
            ->selectRaw($demanda === null ? 'NULL AS total, NULL AS manana, NULL AS tarde' : 'd.total, d.manana, d.tarde')
            ->get();
    }
}
