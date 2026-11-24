<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\RevisionCurso;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Modules\PreMatricula\Models\ValidacionSolicitud;

/**
 * Validación académica de una solicitud. No bloquea el registro: informa a la
 * Dirección si la selección sigue siendo válida tras importar nuevos resultados.
 */
final class RevalidacionPrematricula
{
    public function __construct(
        private readonly ElegibilidadAcademica $elegibilidad,
        private readonly CatalogoAcademico $catalogo,
    ) {}

    public function evaluar(SolicitudPrematricula $solicitud): ValidacionSolicitud
    {
        $solicitud->loadMissing('periodo');
        $historiales = $this->elegibilidad->historiales($solicitud->id_estudiante, $solicitud->periodo);
        $ids = $solicitud->id_revision_vigente === null
            ? $solicitud->detalles()->pluck('id_curso_plan')->all()
            : RevisionCurso::query()->where('id_revision', $solicitud->id_revision_vigente)->pluck('id_curso_plan')->all();

        $cursos = $this->catalogo->cursos(array_map('intval', $ids))
            ->map(fn (object $curso): array => $this->elegibilidad->evaluar($curso, $historiales))
            ->values()->all();
        $estados = array_column($cursos, 'estado');
        $estado = match (true) {
            in_array(ValidacionSolicitud::REQUIERE_AJUSTE, $estados, true) => ValidacionSolicitud::REQUIERE_AJUSTE,
            in_array(ValidacionSolicitud::PENDIENTE_RESULTADOS, $estados, true) => ValidacionSolicitud::PENDIENTE_RESULTADOS,
            default => ValidacionSolicitud::VALIDA,
        };

        $validacion = ValidacionSolicitud::query()->updateOrCreate(
            ['id_solicitud' => $solicitud->id_solicitud],
            ['estado' => $estado, 'cursos' => $cursos, 'evaluada_en' => now(), 'id_revision' => $solicitud->id_revision_vigente],
        );
        $solicitud->setRelation('validacion', $validacion);

        return $validacion;
    }

    /** @param list<int> $idsEstudiante */
    public function paraEstudiantes(array $idsEstudiante): void
    {
        if ($idsEstudiante === []) {
            return;
        }

        SolicitudPrematricula::query()
            ->whereIn('id_estudiante', $idsEstudiante)
            ->whereIn('estado', [SolicitudPrematricula::ESTADO_BORRADOR, SolicitudPrematricula::ESTADO_ENVIADA])
            ->whereHas('periodo', fn ($query) => $query->where('fecha_fin', '>=', now()->toDateString()))
            ->orderBy('id_solicitud')
            ->chunkById(100, function ($solicitudes): void {
                foreach ($solicitudes as $solicitud) {
                    $this->evaluar($solicitud);
                }
            }, 'id_solicitud');
    }
}
