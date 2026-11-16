<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Models\TipoSolicitud;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Designación del asesor: el Director solicita al Decanato (actividad 3) y la Secretaría
 * registra la resolución recibida (actividades 4 a 6).
 */
final class DesignacionService
{
    public function __construct(
        private readonly IdentidadService $identidad,
        private readonly CatalogoService $catalogo,
        private readonly ArchivoService $archivos,
        private readonly HistorialService $historial,
        private readonly NotificacionService $notificaciones,
    ) {}

    /** Solicitudes de asesor que esperan la resolución del Decanato. */
    public function solicitudesPendientes()
    {
        return Solicitud::query()
            ->where('estado', 'Pendiente')
            ->where('id_tipo_solicitud', $this->idTipo(Parametros::TIPO_SOLICITUD_ASESOR))
            ->with('proyecto:id_proyecto,uuid,codigo,titulo,id_linea,estado,etapa')
            ->orderBy('presentada_en')
            ->get(['id_solicitud', 'uuid', 'id_proyecto', 'presentada_en', 'asunto', 'detalle', 'id_docente_sugerido']);
    }

    public function resolucionesRecientes(int $limite = 8)
    {
        return Resolucion::query()->with('solicitud.proyecto:id_proyecto,codigo')
            ->orderByDesc('registrado_en')->limit($limite)
            ->get(['id_resolucion', 'uuid', 'numero', 'fecha', 'id_solicitud', 'registrado_en']);
    }

    public function solicitudPendiente(Proyecto $proyecto): ?Solicitud
    {
        return Solicitud::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('id_tipo_solicitud', $this->idTipo(Parametros::TIPO_SOLICITUD_ASESOR))
            ->where('estado', 'Pendiente')
            ->first();
    }

    /**
     * Docentes especialistas en la línea del proyecto, con su carga como asesor (referencial).
     *
     * @return list<array{codigo: string, nombre: string, carga: int}>
     */
    public function sugerencias(Proyecto $proyecto): array
    {
        $ids = $this->catalogo->docentesDeLinea($proyecto->id_linea);
        $carga = AsesorProyecto::query()->whereIn('id_docente', $ids)->where('estado', 'Activo')
            ->selectRaw('id_docente, COUNT(*) AS n')->groupBy('id_docente')->pluck('n', 'id_docente');

        $lista = [];
        foreach ($this->identidad->docentes($ids) as $id => $d) {
            $lista[] = ['codigo' => $d['codigo'], 'nombre' => $d['nombre'], 'carga' => (int) ($carga[$id] ?? 0)];
        }
        usort($lista, fn ($a, $b) => [$a['carga'], $a['nombre']] <=> [$b['carga'], $b['nombre']]);

        return $lista;
    }

    /**
     * @param  array{destinatario: string, destinatario_area: string, asunto: string, detalle: string, docente_sugerido?: ?string}  $datos
     */
    public function solicitarAsesor(Proyecto $proyecto, Identidad $director, array $datos): Solicitud
    {
        $sugerido = null;
        if (! empty($datos['docente_sugerido'])) {
            $sugerido = $this->identidad->idDocentePorCodigo($datos['docente_sugerido']);
            if ($sugerido === null || ! in_array($sugerido, $this->catalogo->docentesDeLinea($proyecto->id_linea), true)) {
                throw ValidationException::withMessages(['docente_sugerido' => 'El docente sugerido debe ser especialista activo en la línea del proyecto.']);
            }
        }

        return DB::transaction(function () use ($proyecto, $director, $datos, $sugerido) {
            $p = $this->historial->bloquear($proyecto);

            if ($p->estado !== Estados::REGISTRADO || $p->asesorActivo()->exists()) {
                throw ValidationException::withMessages(['asunto' => 'Este proyecto ya no espera la designación de asesor.']);
            }
            if ($this->solicitudPendiente($p) !== null) {
                throw ValidationException::withMessages(['asunto' => 'Ya existe una solicitud pendiente al Decanato para este proyecto.']);
            }

            $solicitud = Solicitud::create([
                'id_proyecto' => $p->id_proyecto,
                'id_tipo_solicitud' => $this->idTipo(Parametros::TIPO_SOLICITUD_ASESOR),
                'presentada_por' => $director->idUsuario,
                'estado' => 'Pendiente',
                'destinatario' => $datos['destinatario'],
                'destinatario_area' => $datos['destinatario_area'],
                'asunto' => $datos['asunto'],
                'detalle' => $datos['detalle'],
                'id_docente_sugerido' => $sugerido,
            ])->refresh();

            $this->notificaciones->notificar(
                $this->identidad->usuariosConRol('secretaria'),
                'asesor.solicitado', "Solicitud de asesor enviada · {$p->codigo}",
                'Registra la resolución del Decanato cuando llegue.', $p,
            );
            $this->notificaciones->notificar(
                $this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all()),
                'asesor.solicitado', 'Se solicitó la designación de tu asesor',
                'El Director envió la solicitud al Decanato. Te avisaremos cuando se registre la resolución.', $p,
            );

            return $solicitud;
        });
    }

    /**
     * @param  array{numero: string, fecha: string, organo_emisor: string, dirigido_a: string, docente: string, asunto: string, detalle?: ?string}  $datos
     */
    public function registrarResolucion(Solicitud $solicitud, Identidad $secretaria, array $datos, UploadedFile $pdf): Resolucion
    {
        $idDocente = $this->identidad->idDocentePorCodigo($datos['docente']);
        if ($idDocente === null) {
            throw ValidationException::withMessages(['docente' => 'El docente designado no está activo.']);
        }

        return DB::transaction(function () use ($solicitud, $secretaria, $datos, $pdf, $idDocente) {
            $s = Solicitud::query()->whereKey($solicitud->id_solicitud)->lockForUpdate()->firstOrFail();
            $p = $this->historial->bloquear($s->proyecto);

            if ($s->estado !== 'Pendiente' || $s->id_tipo_solicitud !== $this->idTipo(Parametros::TIPO_SOLICITUD_ASESOR)) {
                throw ValidationException::withMessages(['numero' => 'Esta solicitud ya fue atendida.']);
            }
            if ($p->estado !== Estados::REGISTRADO || $p->asesorActivo()->exists()) {
                throw ValidationException::withMessages(['numero' => 'El proyecto ya tiene asesor designado.']);
            }
            if (Resolucion::query()->where('numero', trim($datos['numero']))->exists()) {
                throw ValidationException::withMessages(['numero' => 'Ya existe una resolución con ese número.']);
            }

            $resolucion = Resolucion::create([
                'numero' => trim($datos['numero']),
                'fecha' => $datos['fecha'],
                'asunto' => $datos['asunto'],
                'detalle' => $datos['detalle'] ?? null,
                'organo_emisor' => $datos['organo_emisor'],
                'dirigido_a' => $datos['dirigido_a'],
                'id_solicitud' => $s->id_solicitud,
                'id_archivo' => $this->archivos->guardar($pdf, $secretaria->idUsuario),
                'registrado_por' => $secretaria->idUsuario,
            ])->refresh();

            AsesorProyecto::create([
                'id_proyecto' => $p->id_proyecto,
                'id_docente' => $idDocente,
                'tipo' => 'Asesor',
                'id_resolucion' => $resolucion->id_resolucion,
                'estado' => 'Activo',
                'fecha_inicio' => $datos['fecha'],
            ]);

            $s->update(['estado' => 'Atendida', 'atendida_en' => Carbon::now()]);
            $this->historial->cambiarEstado($p, Estados::CON_ASESOR, $secretaria->idUsuario, "Resolución N.° {$resolucion->numero}: asesor designado.");

            $nombre = $this->identidad->nombreDocente($idDocente);
            $this->notificaciones->notificar(
                $this->identidad->usuariosDeDocentes([$idDocente]),
                'asesor.designado', "Fuiste designado asesor · {$p->codigo}",
                "Resolución N.° {$resolucion->numero}. Proyecto: {$p->titulo}", $p,
            );
            $this->notificaciones->notificar(
                $this->identidad->usuariosDeEstudiantes($p->integrantesActivos()->pluck('id_estudiante')->all()),
                'asesor.designado', 'Se registró la resolución de tu asesor',
                "Tu asesor es {$nombre} (Resolución N.° {$resolucion->numero}). Ya puedes subir tus avances.", $p,
            );
            $this->notificaciones->notificar(
                $this->identidad->usuariosConRol('director_escuela'),
                'asesor.designado', "Asesor designado · {$p->codigo}",
                "La Secretaría registró la Resolución N.° {$resolucion->numero}: {$nombre}.", $p,
            );

            return $resolucion;
        });
    }

    private function idTipo(string $nombre): int
    {
        return (int) TipoSolicitud::query()->firstOrCreate(['nombre' => $nombre])->id_tipo_solicitud;
    }
}
