<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revisión semanal de avances por el asesor (actividades 8 y 19) y levantamiento de observaciones.
 */
final class RevisionService
{
    public const DICTAMENES_AVANCE = [
        'Observado' => ['Con observaciones', 'Observada'],
        'SinObservaciones' => ['Sin observaciones', 'SinObservaciones'],
        'Culminado' => ['Proyecto culminado', 'Culminada'],
    ];

    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly NotificacionService $notificaciones,
    ) {}

    public function revisionAvance(Entrega $entrega): ?Revision
    {
        return Revision::query()->where('id_entrega', $entrega->id_entrega)->where('tipo', 'Avance')->with('observaciones')->first();
    }

    /**
     * @param  array{dictamen: string, comentario?: ?string, observaciones?: list<array{seccion: string, descripcion: string}>, levantar?: list<string>}  $datos
     */
    public function revisarAvance(Proyecto $proyecto, Entrega $entrega, Identidad $asesor, array $datos): Revision
    {
        $nuevas = array_values($datos['observaciones'] ?? []);

        if ($datos['dictamen'] === 'Observado' && $nuevas === []) {
            throw ValidationException::withMessages(['observaciones' => 'Registra al menos una observación o elige «Sin observaciones».']);
        }
        if ($datos['dictamen'] !== 'Observado' && $nuevas !== []) {
            throw ValidationException::withMessages(['dictamen' => 'Si registras observaciones, el resultado debe ser «Con observaciones».']);
        }

        return DB::transaction(function () use ($proyecto, $entrega, $asesor, $datos, $nuevas) {
            $e = Entrega::query()->whereKey($entrega->id_entrega)->lockForUpdate()->firstOrFail();

            if ($e->id_proyecto !== $proyecto->id_proyecto) {
                abort(404);
            }
            if ($e->estado !== 'Pendiente' || $this->revisionAvance($e) !== null) {
                throw ValidationException::withMessages(['dictamen' => 'Esta entrega ya fue revisada.']);
            }

            $revision = Revision::create([
                'id_entrega' => $e->id_entrega,
                'id_docente' => $asesor->idDocente,
                'id_rubrica' => null,
                'tipo' => 'Avance',
                'rol_revisor' => 'Asesor',
                'dictamen' => $datos['dictamen'] === 'SinObservaciones' ? 'Conforme' : $datos['dictamen'],
                'comentario' => $datos['comentario'] ?? null,
                'revisado_en' => Carbon::now(),
            ])->refresh();

            foreach ($nuevas as $o) {
                Observacion::create([
                    'id_revision' => $revision->id_revision,
                    'seccion_documento' => $o['seccion'],
                    'descripcion' => $o['descripcion'],
                    'estado' => 'Pendiente',
                ]);
            }

            $levantadas = 0;
            foreach (array_unique($datos['levantar'] ?? []) as $uuid) {
                $obs = Observacion::query()->where('uuid', $uuid)->first();
                if ($obs !== null) {
                    $this->levantar($obs, $asesor, $proyecto, notificar: false);
                    $levantadas++;
                }
            }

            $e->update(['estado' => self::DICTAMENES_AVANCE[$datos['dictamen']][1]]);

            $mensaje = match ($datos['dictamen']) {
                'Observado' => count($nuevas).' observaciones por levantar.',
                'Culminado' => 'El asesor considera el proyecto culminado y lo verificará con la rúbrica.',
                default => 'Sin observaciones. Continúa con la siguiente semana.',
            };
            if ($levantadas > 0) {
                $mensaje .= " Se levantaron {$levantadas} observaciones.";
            }

            $this->notificaciones->notificar(
                $this->identidad->usuariosDeEstudiantes($proyecto->integrantesActivos()->pluck('id_estudiante')->all()),
                'avance.revisado', "El asesor revisó tu entrega v{$e->version}", $mensaje, $proyecto,
            );

            return $revision;
        });
    }

    /**
     * Da por levantada una observación respondida. Solo puede hacerlo el docente que la registró.
     */
    public function levantar(Observacion $observacion, Identidad $docente, Proyecto $proyecto, bool $notificar = true): void
    {
        DB::transaction(function () use ($observacion, $docente, $proyecto, $notificar) {
            $o = Observacion::query()->whereKey($observacion->id_observacion)->lockForUpdate()->firstOrFail();
            $revision = Revision::query()->with('entrega:id_entrega,id_proyecto')->findOrFail($o->id_revision);

            if ($revision->entrega->id_proyecto !== $proyecto->id_proyecto || (int) $revision->id_docente !== $docente->idDocente) {
                throw ValidationException::withMessages(['levantar' => 'Solo el revisor que registró la observación puede levantarla.']);
            }
            if ($o->estado !== 'Respondida') {
                throw ValidationException::withMessages(['levantar' => 'Solo se levantan observaciones que el estudiante ya respondió.']);
            }

            $o->update(['estado' => 'Levantada', 'subsanada_en' => Carbon::now()]);

            if ($notificar) {
                $this->notificaciones->notificar(
                    $this->identidad->usuariosDeEstudiantes($proyecto->integrantesActivos()->pluck('id_estudiante')->all()),
                    'observacion.levantada', 'Se levantó una observación', mb_substr($o->descripcion, 0, 160), $proyecto,
                );
            }
        });
    }

    /** Observaciones abiertas (pendientes o respondidas) que registró un docente en el proyecto. */
    public function observacionesAbiertasDe(Proyecto $proyecto, int $idDocente)
    {
        return Observacion::query()
            ->join('tesis.revision as r', 'r.id_revision', '=', 'tesis.observacion.id_revision')
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'r.id_entrega')
            ->where('e.id_proyecto', $proyecto->id_proyecto)
            ->where('r.id_docente', $idDocente)
            ->whereIn('tesis.observacion.estado', ['Pendiente', 'Respondida'])
            ->with('entregaCorreccion:id_entrega,uuid,version')
            ->orderBy('tesis.observacion.id_observacion')
            ->get(['tesis.observacion.id_observacion', 'tesis.observacion.uuid', 'tesis.observacion.descripcion', 'tesis.observacion.estado',
                'tesis.observacion.seccion_documento', 'tesis.observacion.respuesta_estudiante', 'tesis.observacion.id_entrega_correccion',
                'tesis.observacion.id_criterio', 'e.version as version_entrega']);
    }
}
