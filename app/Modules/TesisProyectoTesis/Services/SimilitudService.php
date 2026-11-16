<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ReporteSimilitud;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conformidad del asesor y reporte de Turnitin (actividades 11, 12, 24 y 25).
 */
final class SimilitudService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly ArchivoService $archivos,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
        private readonly RubricaService $rubrica,
        private readonly RevisionService $revisiones,
    ) {}

    /**
     * Requisitos para pasar Turnitin: la rúbrica del asesor conforme sobre la versión vigente y nada pendiente.
     *
     * @return array{entrega: ?Entrega, rubricaConforme: bool, abiertas: int, reporte: ?ReporteSimilitud, listo: bool}
     */
    public function requisitos(Proyecto $proyecto, int $idDocente): array
    {
        $entrega = $this->rubrica->entregaObjetivo($proyecto);
        $verificacion = $entrega ? $this->rubrica->revisionDe($entrega, $idDocente, 'Verificacion') : null;
        $abiertas = $this->revisiones->observacionesAbiertasDe($proyecto, $idDocente)->count();
        $reporte = $entrega?->reporteSimilitud()->first();
        $conforme = $verificacion !== null && $verificacion->conforme;

        return [
            'entrega' => $entrega,
            'rubricaConforme' => $conforme,
            'verificacion' => $verificacion,
            'abiertas' => $abiertas,
            'reporte' => $reporte,
            'listo' => in_array($proyecto->estado, Estados::DESARROLLO, true) && $conforme && $abiertas === 0 && $reporte === null,
        ];
    }

    /** @return Collection<int, ReporteSimilitud> */
    public function historial(Proyecto $proyecto): Collection
    {
        return ReporteSimilitud::query()
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'tesis.reporte_similitud.id_entrega')
            ->where('e.id_proyecto', $proyecto->id_proyecto)
            ->orderByDesc('tesis.reporte_similitud.generado_en')
            ->get(['tesis.reporte_similitud.uuid', 'tesis.reporte_similitud.porcentaje', 'tesis.reporte_similitud.resultado', 'tesis.reporte_similitud.generado_en', 'tesis.reporte_similitud.indicaciones', 'e.version', 'e.etapa']);
    }

    public function registrar(Proyecto $proyecto, Identidad $asesor, float $porcentaje, UploadedFile $pdf, ?string $indicaciones, bool $conformidad): ReporteSimilitud
    {
        $apto = $porcentaje <= Parametros::SIMILITUD_MAXIMA;

        if ($apto && ! $conformidad) {
            throw ValidationException::withMessages(['conformidad' => 'Para enviar al jurado debes declarar tu conformidad.']);
        }

        return DB::transaction(function () use ($proyecto, $asesor, $porcentaje, $pdf, $indicaciones, $apto) {
            $p = $this->historial->bloquear($proyecto);
            $req = $this->requisitos($p, (int) $asesor->idDocente);

            if (! in_array($p->estado, Estados::DESARROLLO, true)) {
                throw ValidationException::withMessages(['porcentaje' => 'El proyecto no está en desarrollo con el asesor.']);
            }
            if ($req['entrega'] === null || ! $req['rubricaConforme']) {
                throw ValidationException::withMessages(['porcentaje' => 'Primero verifica la versión vigente con la rúbrica y da tu conformidad.']);
            }
            if ($req['abiertas'] > 0) {
                throw ValidationException::withMessages(['porcentaje' => 'Aún hay observaciones tuyas sin levantar.']);
            }
            if ($req['reporte'] !== null) {
                throw ValidationException::withMessages(['porcentaje' => 'Ya registraste el reporte de similitud de esta versión.']);
            }

            $entrega = $req['entrega'];
            $reporte = ReporteSimilitud::create([
                'id_entrega' => $entrega->id_entrega,
                'porcentaje' => round($porcentaje, 2),
                'herramienta' => 'Turnitin',
                'id_archivo' => $this->archivos->guardar($pdf, $asesor->idUsuario),
                'id_docente' => $asesor->idDocente,
                'indicaciones' => $indicaciones,
                'resultado' => $apto ? 'Apto' : 'NoApto',
            ])->refresh();

            $estudiantes = $this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all());
            $texto = number_format($porcentaje, 2).' %';

            if (! $apto) {
                $entrega->update(['estado' => 'Observada']);
                $this->notificaciones->notificar($estudiantes, 'similitud.no_apto', "Similitud {$texto}: supera el límite",
                    'Corrige el documento para bajar la similitud a '.Parametros::SIMILITUD_MAXIMA.' % o menos y sube una nueva versión.', $p);

                return $reporte;
            }

            $tieneJurado = $p->juradosActivos()->count() === Parametros::MIEMBROS_JURADO;
            if ($p->enEtapaTesis() && $tieneJurado) {
                $this->historial->cambiarEstado($p, Estados::EN_REVISION_JURADO, $asesor->idUsuario, "Conformidad del asesor y Turnitin {$texto}: tesis enviada a revisión final del jurado.");
                $this->notificaciones->notificar(
                    $this->identidad->usuariosDeDocentes($p->juradosActivos()->pluck('id_docente')->all()),
                    'jurado.revision_final', "Tesis por revisar · {$p->codigo}", 'El asesor dio su conformidad. Revisa la tesis con la rúbrica.', $p,
                );
            } else {
                $this->historial->cambiarEstado($p, Estados::VERIFICADO_ASESOR, $asesor->idUsuario, "Conformidad del asesor y Turnitin {$texto}.");
            }

            $this->notificaciones->notificar($this->identidad->usuariosConRol('director_escuela'), 'asesor.conformidad',
                "Conformidad del asesor · {$p->codigo}", $p->enEtapaTesis() ? "Tesis enviada a revisión final (Turnitin {$texto})." : "Listo para asignar jurado (Turnitin {$texto}).", $p);
            $this->notificaciones->notificar($estudiantes, 'asesor.conformidad', 'Tu asesor dio su conformidad',
                "Similitud {$texto} (apto). ".($p->enEtapaTesis() ? 'La tesis pasa a la revisión final del jurado.' : 'El Director asignará el jurado.'), $p);

            return $reporte;
        });
    }
}
