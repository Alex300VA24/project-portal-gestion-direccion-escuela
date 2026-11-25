<?php

namespace App\Modules\Bienestar\Services;

use Illuminate\Support\Facades\DB;

/** Ciclo del estudiante (dato administrativo): según su última matrícula o, si no tiene, estimado por su ingreso. */
final class CicloEstudianteService
{
    /**
     * @param  iterable<int>  $ids
     * @return array<int, string>
     */
    public function paraEstudiantes(iterable $ids): array
    {
        $ids = collect($ids)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $filas = DB::table('core.estudiante as e')
            ->join('core.periodo_academico as pi', 'pi.id_periodo', '=', 'e.id_periodo_ingreso')
            ->whereIn('e.id_estudiante', $ids)
            ->selectRaw("e.id_estudiante, (select count(*) from core.periodo_academico p
                where p.fecha_inicio >= pi.fecha_inicio and p.fecha_inicio <= current_date and upper(p.tipo) = 'REGULAR') as periodos")
            ->pluck('periodos', 'id_estudiante');

        $matriculados = DB::table('gestion_matriculas.matricula as m')
            ->join('gestion_matriculas.matricula_detalle as d', 'd.id_matricula', '=', 'm.id_matricula')
            ->join('core.seccion as s', 's.id_seccion', '=', 'd.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->whereIn('m.id_estudiante', $ids)
            ->groupBy('m.id_estudiante')
            ->selectRaw('m.id_estudiante, max(cp.ciclo) as ciclo')
            ->pluck('ciclo', 'id_estudiante');

        $ciclos = [];
        foreach ($ids as $id) {
            $ciclos[$id] = match (true) {
                isset($matriculados[$id]) => 'Ciclo '.$matriculados[$id],
                ($filas[$id] ?? 0) > 0 => 'Ciclo '.min(10, (int) $filas[$id]).' (estimado)',
                default => 'Ciclo no registrado',
            };
        }

        return $ciclos;
    }
}
