<?php

namespace App\Modules\PreMatricula\Services;

use App\Shared\Contracts\FuenteDemandaPrematricula;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FuenteDemandaAgregada implements FuenteDemandaPrematricula
{
    public function __construct(private readonly DemandaAcademica $demanda) {}

    public function cursos(int $periodo): array
    {
        // La instantánea tiene alcance institucional completo por periodo. Nunca
        // enviar una sola escuela a un repositorio que versiona por periodo.
        if (config('gestion_horarios_integracion.habilitada') !== true
            || DB::connection()->getDriverName() !== 'pgsql'
            || ! DB::table('core.periodo_academico')->where('id_periodo', $periodo)->exists()) {
            throw new RuntimeException('Fuente institucional de demanda no habilitada o periodo inválido.');
        }
        $ids = DB::table('core.curso_plan')->orderBy('id_curso_plan')->pluck('id_curso_plan');
        return self::completar($ids, $this->demanda->porCurso($periodo));
    }

    /** No confunde ausencia de solicitud con ausencia de curso en el catálogo. */
    public static function completar(iterable $ids, iterable $filas): array
    {
        $cursos = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1 || isset($cursos[$id])) {
                throw new RuntimeException('Catálogo de cursos inválido.');
            }
            $cursos[$id] = ['id_curso_plan' => $id, 'total' => 0, 'manana' => 0, 'tarde' => 0];
        }
        if ($cursos === []) {
            throw new RuntimeException('Catálogo de cursos vacío.');
        }
        $vistos = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->id_curso_plan;
            if (! isset($cursos[$id], $fila->estudiantes, $fila->manana, $fila->tarde) || isset($vistos[$id])) {
                throw new RuntimeException('Demanda incompatible: revise el catálogo y el parche de turnos.');
            }
            $vistos[$id] = true;
            $cursos[$id] = ['id_curso_plan' => $id, 'total' => (int) $fila->estudiantes, 'manana' => (int) $fila->manana, 'tarde' => (int) $fila->tarde];
        }
        ksort($cursos, SORT_NUMERIC);

        return array_values($cursos);
    }
}
