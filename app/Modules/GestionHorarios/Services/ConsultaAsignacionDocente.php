<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConsultaAsignacionDocenteRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

final class ConsultaAsignacionDocente
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, string $buscar, string $ciclo, string $seccion, ?int $plan = null): array
    {
        $periodosRepo = new OfertaAcademicaRepository($this->db);
        $periodos = $plan !== null ? $periodosRepo->periodosParaPlan($plan) : $periodosRepo->periodos();
        $repo = new ConsultaAsignacionDocenteRepository($this->db);
        $docentes = $repo->docentes($buscar, $periodo, $plan);
        $actividades = collect();
        $periodoValido = $periodo !== null && $periodos->contains('id_periodo', $periodo);
        foreach ($docentes as $docente) {
            $docente->minutos_confirmados = null;
            $docente->actividades_confirmadas = 0;
        }
        if ($periodoValido) {
            $carga = $repo->cargaConfirmada($periodo, $plan)->groupBy('id_docente');
            foreach ($docentes as $docente) {
                $filas = $carga->get($docente->id_docente, collect());
                $incompleta = $filas->contains(fn ($fila) => ! $fila->hora_inicio || ! $fila->hora_fin || $fila->hora_inicio >= $fila->hora_fin);
                $docente->minutos_confirmados = $incompleta ? null : $filas->sum(fn ($fila) => Carbon::parse($fila->hora_inicio)->diffInMinutes(Carbon::parse($fila->hora_fin)));
                $docente->actividades_confirmadas = $filas->count();
            }
            $ids = $docentes->pluck('id_docente')->map(fn ($id) => (string) $id)->all();
            $actividades = $repo->actividades($periodo, $ciclo, $seccion, $plan)->groupBy('id_actividad')->map(function ($filas) {
                $actividad = $filas->first();
                $actividad->asignaciones = $filas->whereNotNull('id_asignacion')->values();
                $actividad->sin_docente_activo = ! $actividad->asignaciones->contains(fn ($fila) => in_array($fila->estado_asignacion, ['PROPUESTA', 'CONFIRMADA'], true));
                $actividad->duplicados = $actividad->asignaciones->whereIn('estado_asignacion', ['PROPUESTA', 'CONFIRMADA'])->groupBy('id_docente')->contains(fn ($grupo) => $grupo->count() > 1);

                return $actividad;
            })->values();
            if ($buscar !== '') {
                $actividades = $actividades->filter(fn ($a) => $a->asignaciones->contains(fn ($fila) => in_array((string) $fila->id_docente, $ids, true)))->values();
            }
        }

        return compact('periodos', 'docentes', 'actividades', 'periodoValido');
    }
}
