<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\ImportacionHistorial;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Consulta del historial académico importado, como AcademicHistoryController e imports de master. */
final class HistorialAcademicoConsulta
{
    /** Historial para la coordinación, filtrable por estudiante y período. */
    public function listar(?string $buscar, ?int $idPeriodo): LengthAwarePaginator
    {
        $buscar = trim((string) $buscar);

        return $this->consulta()
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $patron = '%'.addcslashes(mb_strtolower($buscar), '\\%_').'%';
                $query->where(fn (Builder $q) => $q->whereRaw('lower(e.codigo_universitario) like ?', [$patron])
                    ->orWhereRaw("lower(pe.nombres || ' ' || pe.apellido_paterno || ' ' || coalesce(pe.apellido_materno, '')) like ?", [$patron]));
            })
            ->when($idPeriodo !== null, fn (Builder $query) => $query->where('h.id_periodo', $idPeriodo))
            ->orderByDesc('p.fecha_inicio')->orderBy('e.codigo_universitario')->orderBy('c.codigo')
            ->paginate(20)
            ->withQueryString();
    }

    /** @return Collection<int, object> historial de un estudiante, del período más reciente al más antiguo */
    public function delEstudiante(int $idEstudiante): Collection
    {
        return $this->consulta()
            ->where('h.id_estudiante', $idEstudiante)
            ->orderByDesc('p.fecha_inicio')->orderBy('c.codigo')
            ->get();
    }

    /** @return Collection<int, object> cambios de una importación con el curso y el estudiante afectados */
    public function cambios(ImportacionHistorial $importacion): Collection
    {
        return DB::table('prematricula.cambio_historial as ch')
            ->join('prematricula.historial_academico as h', 'h.id_historial', '=', 'ch.id_historial')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'h.id_estudiante')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'h.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'h.id_periodo')
            ->where('ch.id_importacion', $importacion->id_importacion)
            ->orderBy('ch.id_cambio')
            ->get(['ch.id_cambio', 'ch.antes', 'ch.despues', 'e.codigo_universitario', 'c.codigo as curso', 'c.nombre', 'p.codigo as periodo'])
            ->map(function (object $cambio): object {
                $cambio->antes = $cambio->antes === null ? null : json_decode($cambio->antes, true);
                $cambio->despues = json_decode($cambio->despues, true);

                return $cambio;
            });
    }

    private function consulta(): Builder
    {
        return DB::table('prematricula.historial_academico as h')
            ->join('core.estudiante as e', 'e.id_estudiante', '=', 'h.id_estudiante')
            ->leftJoin('core.persona as pe', 'pe.id_persona', '=', 'e.id_persona')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'h.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'h.id_periodo')
            ->select([
                'h.id_historial', 'h.nota_final', 'h.estado',
                'e.codigo_universitario', 'pe.nombres', 'pe.apellido_paterno', 'pe.apellido_materno',
                'c.codigo', 'c.nombre', 'c.creditos', 'cp.ciclo', 'p.codigo as periodo',
            ]);
    }
}
