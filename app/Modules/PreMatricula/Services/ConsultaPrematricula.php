<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Consultas de la Dirección y Secretaría: registros, constancias y situación académica. */
final class ConsultaPrematricula
{
    public function __construct(
        private readonly ElegibilidadAcademica $elegibilidad,
        private readonly CatalogoAcademico $catalogo,
    ) {}

    /** @return Collection<int, PeriodoAcademico> períodos más recientes primero */
    public function periodos(): Collection
    {
        return PeriodoAcademico::query()->orderByDesc('fecha_inicio')->get();
    }

    public function periodoPorDefecto(?int $idPeriodo, ?PeriodoAcademico $habilitado): ?PeriodoAcademico
    {
        if ($idPeriodo !== null) {
            return PeriodoAcademico::query()->find($idPeriodo);
        }

        return $habilitado ?? PeriodoAcademico::query()
            ->whereIn('id_periodo', SolicitudPrematricula::query()->select('id_periodo'))
            ->orderByDesc('fecha_inicio')->first();
    }

    public function registros(int $idPeriodo, ?string $estado, ?string $buscar): LengthAwarePaginator
    {
        return SolicitudPrematricula::query()
            ->with(['estudiante.persona', 'revisionVigente', 'validacion'])
            ->where('id_periodo', $idPeriodo)
            ->when($estado !== null && $estado !== '', fn ($query) => $estado === 'CORRECCION'
                ? $query->where('estado', SolicitudPrematricula::ESTADO_BORRADOR)->whereNotNull('id_cronograma_correccion')
                : $query->where('estado', $estado)->whereNull('id_cronograma_correccion'))
            ->when($buscar !== null && $buscar !== '', function ($query) use ($buscar): void {
                $patron = '%'.addcslashes($buscar, '\\%_').'%';
                $query->whereHas('estudiante', fn ($e) => $e->whereLike('codigo_universitario', $patron)
                    ->orWhereHas('persona', fn ($p) => $p->whereLike('nombres', $patron, caseSensitive: false)
                        ->orWhereLike('apellido_paterno', $patron, caseSensitive: false)
                        ->orWhereLike('apellido_materno', $patron, caseSensitive: false)));
            })
            ->orderByDesc('enviada_en')
            ->orderByDesc('id_solicitud')
            ->paginate(20)
            ->withQueryString();
    }

    /** @return array<string, int> */
    public function conteos(int $idPeriodo): array
    {
        $base = SolicitudPrematricula::query()->where('id_periodo', $idPeriodo);

        return [
            'ENVIADA' => (clone $base)->where('estado', SolicitudPrematricula::ESTADO_ENVIADA)->count(),
            'BORRADOR' => (clone $base)->where('estado', SolicitudPrematricula::ESTADO_BORRADOR)->whereNull('id_cronograma_correccion')->count(),
            'CORRECCION' => (clone $base)->where('estado', SolicitudPrematricula::ESTADO_BORRADOR)->whereNotNull('id_cronograma_correccion')->count(),
        ];
    }

    /** @return Collection<int, RevisionSolicitud> */
    public function revisiones(SolicitudPrematricula $solicitud): Collection
    {
        return $solicitud->revisiones()->whereNotNull('numero_revision')->orderByDesc('numero_revision')->get();
    }

    /** @return Collection<int, Estudiante> */
    public function buscarEstudiantes(string $buscar): Collection
    {
        $patron = '%'.addcslashes($buscar, '\\%_').'%';

        return Estudiante::query()->with('persona')
            ->where(fn ($query) => $query->where('codigo_universitario', $buscar)
                ->orWhereHas('persona', fn ($p) => $p->whereLike('nombres', $patron, caseSensitive: false)
                    ->orWhereLike('apellido_paterno', $patron, caseSensitive: false)
                    ->orWhereLike('apellido_materno', $patron, caseSensitive: false)))
            ->orderBy('codigo_universitario')
            ->limit(25)
            ->get();
    }

    /** @return array<string, mixed> */
    public function situacion(Estudiante $estudiante, ?PeriodoAcademico $periodo): array
    {
        $historial = DB::table('prematricula.historial_academico as h')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'h.id_periodo')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'h.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('h.id_estudiante', $estudiante->id_estudiante)
            ->orderByDesc('p.fecha_inicio')->orderBy('c.codigo')
            ->get(['p.codigo as periodo', 'c.codigo', 'c.nombre', 'cp.ciclo', 'c.creditos', 'h.nota_final', 'h.estado']);

        $solicitud = $periodo === null ? null : SolicitudPrematricula::query()->with(['revisionVigente', 'validacion'])
            ->where('id_estudiante', $estudiante->id_estudiante)->where('id_periodo', $periodo->id_periodo)->first();

        return [
            'historial' => $historial,
            'solicitud' => $solicitud,
            'disponibles' => $periodo === null ? collect() : $this->elegibilidad->disponibles($estudiante, $periodo),
        ];
    }
}
