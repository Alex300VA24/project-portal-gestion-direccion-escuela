<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entregas del estudiante (actividades 7, 9, 16, 18, 20, 29) y respuesta a observaciones.
 */
final class AvanceService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly ArchivoService $archivos,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
        private readonly JuradoService $jurado,
    ) {}

    /**
     * @param  array{tipo: string, numero_semana: int, comentario?: ?string}  $datos
     */
    public function subir(Proyecto $proyecto, Identidad $estudiante, array $datos, UploadedFile $pdf): Entrega
    {
        return DB::transaction(function () use ($proyecto, $estudiante, $datos, $pdf) {
            $p = $this->historial->bloquear($proyecto);

            if (! in_array($p->estado, Estados::PERMITEN_ENTREGA, true)) {
                throw ValidationException::withMessages(['archivo' => 'En el estado actual del proyecto ('.Estados::etiqueta($p->estado).') no se reciben entregas.']);
            }

            $version = (int) Entrega::query()->where('id_proyecto', $p->id_proyecto)->where('etapa', $p->etapa)->max('version') + 1;

            $entrega = Entrega::create([
                'id_proyecto' => $p->id_proyecto,
                'etapa' => $p->etapa,
                'version' => $version,
                'tipo' => $datos['tipo'],
                'numero_semana' => (int) $datos['numero_semana'],
                'comentario' => $datos['comentario'] ?? null,
                'entregado_por' => $estudiante->idEstudiante,
                'estado' => 'Pendiente',
                'id_archivo' => $this->archivos->guardar($pdf, $estudiante->idUsuario),
            ])->refresh();

            if ($p->estado === Estados::CON_ASESOR) {
                $this->historial->cambiarEstado($p, Estados::EN_DESARROLLO, $estudiante->idUsuario, 'Primer avance semanal (v'.$version.').');
            }

            $tipo = $datos['tipo'] === 'Final' ? 'versión final' : 'avance de la semana '.$entrega->numero_semana;
            $destinos = $p->estado === Estados::EN_REVISION_JURADO
                ? $this->identidad->usuariosDeDocentes($p->juradosActivos()->pluck('id_docente')->all())
                : $this->identidad->usuariosDeDocentes(array_filter([$p->asesorActivo()->value('id_docente')]));

            $this->notificaciones->notificar($destinos, 'entrega.subida', "Nueva entrega · {$p->codigo} v{$version}",
                "Los estudiantes subieron la {$tipo}.", $p);

            $this->jurado->notificarIntermediaSiCorresponde($p);

            return $entrega;
        });
    }

    /**
     * Observaciones del proyecto con su revisor, agrupables por estado.
     *
     * @return Collection<int, Observacion>
     */
    public function observaciones(Proyecto $proyecto, ?string $etapa = null): Collection
    {
        $obs = Observacion::query()
            ->join('tesis.revision as r', 'r.id_revision', '=', 'tesis.observacion.id_revision')
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'r.id_entrega')
            ->where('e.id_proyecto', $proyecto->id_proyecto)
            ->when($etapa, fn ($q) => $q->where('e.etapa', $etapa))
            ->with(['criterio:id_criterio,orden,descripcion', 'entregaCorreccion:id_entrega,uuid,version,numero_semana'])
            ->orderByRaw("CASE tesis.observacion.estado WHEN 'Pendiente' THEN 0 WHEN 'Respondida' THEN 1 ELSE 2 END")
            ->orderBy('r.revisado_en')
            ->get([
                'tesis.observacion.id_observacion', 'tesis.observacion.uuid', 'tesis.observacion.id_revision', 'tesis.observacion.descripcion',
                'tesis.observacion.estado', 'tesis.observacion.id_criterio', 'tesis.observacion.seccion_documento',
                'tesis.observacion.respuesta_estudiante', 'tesis.observacion.id_entrega_correccion', 'tesis.observacion.respondida_en',
                'tesis.observacion.subsanada_en', 'r.id_docente', 'r.rol_revisor', 'r.tipo as tipo_revision', 'r.revisado_en',
                'r.referencial', 'e.version as version_entrega', 'e.numero_semana as semana_entrega', 'e.id_entrega as id_entrega_origen', 'e.etapa as etapa_entrega',
            ]);

        $docentes = $this->identidad->docentes($obs->pluck('id_docente')->unique()->values()->all());

        return $obs->each(fn ($o) => $o->setAttribute('revisor', $docentes[$o->id_docente]['nombre'] ?? '—'));
    }

    public function proyectoDeObservacion(Observacion $observacion): ?Proyecto
    {
        return $observacion->revision?->entrega?->proyecto;
    }

    public function responder(Observacion $observacion, Identidad $estudiante, string $respuesta, ?string $uuidEntrega): Observacion
    {
        return DB::transaction(function () use ($observacion, $estudiante, $respuesta, $uuidEntrega) {
            $o = Observacion::query()->whereKey($observacion->id_observacion)->lockForUpdate()->firstOrFail();
            $revision = Revision::query()->with('entrega')->findOrFail($o->id_revision);
            $proyecto = $revision->entrega->proyecto;

            if ($o->estado === 'Levantada') {
                throw ValidationException::withMessages(['respuesta' => 'Esta observación ya fue levantada por el revisor.']);
            }

            $idCorreccion = null;
            if ($uuidEntrega !== null && $uuidEntrega !== '') {
                $correccion = Entrega::query()->where('uuid', $uuidEntrega)->where('id_proyecto', $proyecto->id_proyecto)->first();
                if ($correccion === null || $correccion->entregado_en->lt($revision->entrega->entregado_en) || $correccion->is($revision->entrega)) {
                    throw ValidationException::withMessages(['entrega' => 'Elige una entrega de tu proyecto posterior a la observada.']);
                }
                $idCorreccion = $correccion->id_entrega;
            }

            $o->update([
                'respuesta_estudiante' => $respuesta,
                'id_entrega_correccion' => $idCorreccion,
                'estado' => 'Respondida',
                'respondida_en' => Carbon::now(),
            ]);

            $this->notificaciones->notificar(
                $this->identidad->usuariosDeDocentes([(int) $revision->id_docente]),
                'observacion.respondida', "Observación respondida · {$proyecto->codigo}",
                'El estudiante indicó cómo corrigió la observación. Revísala y dala por levantada.', $proyecto,
            );

            return $o;
        });
    }

    /**
     * Entregas del proyecto para elegir dónde se corrigió una observación.
     *
     * @return Collection<int, Entrega>
     */
    public function entregasParaCorreccion(Proyecto $proyecto): Collection
    {
        return Entrega::query()->where('id_proyecto', $proyecto->id_proyecto)
            ->orderByDesc('entregado_en')->get(['id_entrega', 'uuid', 'version', 'numero_semana', 'tipo', 'etapa', 'entregado_en']);
    }
}
