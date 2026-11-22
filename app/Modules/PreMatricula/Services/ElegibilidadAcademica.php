<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\ValidacionSolicitud;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decide qué cursos puede llevar un estudiante a partir de los resultados oficiales
 * importados (prematricula.historial_academico) y los prerrequisitos del plan.
 */
final class ElegibilidadAcademica
{
    public function __construct(private readonly CatalogoAcademico $catalogo) {}

    /**
     * Resultados anteriores al período objetivo, del más reciente al más antiguo.
     *
     * @return Collection<int, object>
     */
    public function historiales(int $idEstudiante, ?PeriodoAcademico $objetivo = null): Collection
    {
        return DB::table('prematricula.historial_academico as h')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'h.id_periodo')
            ->where('h.id_estudiante', $idEstudiante)
            ->when($objetivo !== null, fn ($query) => $query->where('p.fecha_inicio', '<', $objetivo->fecha_inicio->toDateString()))
            ->orderByDesc('p.fecha_inicio')
            ->orderByDesc('h.id_historial')
            ->get(['h.id_curso_plan', 'h.estado', 'h.nota_final', 'p.codigo as periodo']);
    }

    /** Un aprobado en cualquier intento prevalece; si no, cuenta el intento más reciente. */
    public function estado(int $idCursoPlan, Collection $historiales): ?string
    {
        $intentos = $historiales->where('id_curso_plan', $idCursoPlan);

        if ($intentos->contains('estado', HistorialAcademico::APROBADO)) {
            return HistorialAcademico::APROBADO;
        }

        return $intentos->first()?->estado;
    }

    /** @return array{id_curso_plan: int, codigo: string, nombre: string, estado: string, motivo: string|null, prerrequisitos: list<array<string, mixed>>} */
    public function evaluar(object $curso, Collection $historiales): array
    {
        $propio = $this->estado($curso->id_curso_plan, $historiales);
        $bloqueado = ! $curso->activo || in_array($propio, [HistorialAcademico::APROBADO, HistorialAcademico::EN_CURSO], true);
        $estado = $bloqueado ? ValidacionSolicitud::REQUIERE_AJUSTE : ValidacionSolicitud::VALIDA;
        $prerrequisitos = [];

        foreach ($curso->prerrequisitos as $requisito) {
            $resultado = $this->estado($requisito['id_curso_plan'], $historiales);
            $estadoRequisito = match ($resultado) {
                HistorialAcademico::APROBADO => ValidacionSolicitud::VALIDA,
                HistorialAcademico::EN_CURSO => ValidacionSolicitud::PENDIENTE_RESULTADOS,
                default => ValidacionSolicitud::REQUIERE_AJUSTE,
            };

            if ($estadoRequisito === ValidacionSolicitud::REQUIERE_AJUSTE
                || ($estado === ValidacionSolicitud::VALIDA && $estadoRequisito === ValidacionSolicitud::PENDIENTE_RESULTADOS)) {
                $estado = $estadoRequisito;
            }

            $prerrequisitos[] = [...$requisito, 'resultado' => $resultado, 'estado' => $estadoRequisito];
        }

        return [
            'id_curso_plan' => $curso->id_curso_plan,
            'codigo' => $curso->codigo,
            'nombre' => $curso->nombre,
            'estado' => $estado,
            'motivo' => match (true) {
                ! $curso->activo => 'El curso está inactivo.',
                $propio === HistorialAcademico::APROBADO => 'Ya aprobaste este curso.',
                $propio === HistorialAcademico::EN_CURSO => 'Estás llevando este curso.',
                $estado === ValidacionSolicitud::REQUIERE_AJUSTE => 'Falta aprobar un prerrequisito.',
                default => null,
            },
            'prerrequisitos' => $prerrequisitos,
        ];
    }

    /**
     * Cursos del plan del estudiante que puede prematricular, con su evaluación.
     *
     * @return Collection<int, object>
     */
    public function disponibles(Estudiante $estudiante, ?PeriodoAcademico $periodo = null): Collection
    {
        $historiales = $this->historiales($estudiante->id_estudiante, $periodo);

        return $this->catalogo->cursosDelPlan($estudiante->id_plan)
            ->map(function (object $curso) use ($historiales): object {
                $curso->evaluacion = $this->evaluar($curso, $historiales);

                return $curso;
            })
            ->reject(fn (object $curso): bool => $curso->evaluacion['estado'] === ValidacionSolicitud::REQUIERE_AJUSTE)
            ->values();
    }
}
