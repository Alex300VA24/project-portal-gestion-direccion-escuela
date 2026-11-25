<?php

namespace App\Modules\PreMatricula\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura del catálogo compartido (core.curso_plan, core.curso, core.curso_prerequisito).
 * Consultas de solo lectura; la malla se administra con PlanEstudios, como en master.
 */
final class CatalogoAcademico
{
    /** @return Collection<int, object> cursos del plan, ordenados por ciclo y nombre */
    public function cursosDelPlan(int $idPlan): Collection
    {
        return $this->leer(fn (Builder $query) => $query->where('cp.id_plan', $idPlan));
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, object> cursos indexados por id_curso_plan
     */
    public function cursos(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $this->leer(fn (Builder $query) => $query->whereIn('cp.id_curso_plan', $ids))->keyBy('id_curso_plan');
    }

    public static function activo(?string $estado): bool
    {
        return in_array(mb_strtoupper(trim((string) $estado)), ['ACTIVO', 'VIGENTE'], true);
    }

    /** @param \Closure(Builder): Builder $filtro */
    private function leer(\Closure $filtro): Collection
    {
        $filas = $filtro(DB::table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso'))
            ->orderBy('cp.ciclo')
            ->orderBy('c.nombre')
            ->get(['cp.id_curso_plan', 'cp.id_plan', 'cp.ciclo', 'c.id_curso', 'c.codigo', 'c.nombre', 'c.creditos', 'c.estado']);

        $prerrequisitos = DB::table('core.curso_prerequisito as p')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'p.id_curso_plan_requisito')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->whereIn('p.id_curso_plan', $filas->pluck('id_curso_plan')->all())
            ->orderBy('c.codigo')
            ->get(['p.id_curso_plan', 'p.id_curso_plan_requisito', 'c.codigo', 'c.nombre'])
            ->groupBy('id_curso_plan');

        return $filas->map(fn (object $fila): object => (object) [
            'id_curso_plan' => (int) $fila->id_curso_plan,
            'id_plan' => (int) $fila->id_plan,
            'ciclo' => (int) $fila->ciclo,
            'id_curso' => (int) $fila->id_curso,
            'codigo' => $fila->codigo,
            'nombre' => $fila->nombre,
            'creditos' => (float) $fila->creditos,
            'activo' => self::activo($fila->estado),
            'prerrequisitos' => ($prerrequisitos->get($fila->id_curso_plan) ?? collect())
                ->map(fn (object $requisito): array => [
                    'id_curso_plan' => (int) $requisito->id_curso_plan_requisito,
                    'codigo' => $requisito->codigo,
                    'nombre' => $requisito->nombre,
                ])->values()->all(),
        ])->values();
    }
}
