<?php

namespace App\Modules\Bienestar\Services;

use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\SolicitudRequisitoDocumento;
use App\Modules\Bienestar\Models\TipoCaso;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Shared\Services\ModuleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SolicitudTramiteService extends ModuleService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /**
     * Registra una nueva solicitud de trámite de apoyo universitario, validando que se adjunten
     * todos los requisitos obligatorios y almacenándolos en disco privado con nombre UUID.
     */
    public function registrarSolicitud(int $idEstudiante, int $idTipoSolicitud, string $motivo, array $archivosSubidos, int $idUsuario): SolicitudApoyo
    {
        $tipo = TipoSolicitud::with('requisitosActivos')->findOrFail($idTipoSolicitud);

        // Validar servidor: comprobar que no falte ningún requisito obligatorio
        foreach ($tipo->requisitosActivos as $req) {
            if ($req->es_obligatorio && (empty($archivosSubidos[$req->id_requisito]) || ! ($archivosSubidos[$req->id_requisito] instanceof UploadedFile))) {
                throw ValidationException::withMessages([
                    "archivos.{$req->id_requisito}" => "El requisito obligatorio '{$req->nombre}' debe ser adjuntado.",
                ]);
            }
        }

        return DB::transaction(function () use ($idEstudiante, $tipo, $motivo, $archivosSubidos, $idUsuario) {
            $solicitud = SolicitudApoyo::create([
                'id_estudiante' => $idEstudiante,
                'id_tipo_solicitud' => $tipo->id_tipo_solicitud,
                'motivo' => $motivo,
                'estado' => 'enviada',
                'solicitada_en' => now(),
                'folio_externo_confirmado' => false,
            ]);

            foreach ($tipo->requisitosActivos as $req) {
                if (! empty($archivosSubidos[$req->id_requisito]) && $archivosSubidos[$req->id_requisito] instanceof UploadedFile) {
                    $file = $archivosSubidos[$req->id_requisito];
                    $idArchivo = $this->guardarArchivoPrivado($file, $idUsuario);

                    SolicitudRequisitoDocumento::create([
                        'id_solicitud' => $solicitud->id_solicitud,
                        'id_requisito' => $req->id_requisito,
                        'id_archivo' => $idArchivo,
                        'estado_revision' => 'pendiente',
                        'subido_en' => now(),
                        'actualizado_en' => now(),
                    ]);
                }
            }

            $this->notificaciones->solicitudNueva($solicitud->id_solicitud);

            return $solicitud;
        });
    }

    /**
     * La Secretaría marca la solicitud como 'recibida' para dar inicio a la revisión formal de requisitos.
     */
    public function recibirSolicitud(SolicitudApoyo $solicitud, int $idUsuario): void
    {
        if ($solicitud->estado !== 'enviada') {
            throw ValidationException::withMessages([
                'solicitud' => 'Solo se pueden recibir solicitudes que se encuentren en estado enviada.',
            ]);
        }

        $solicitud->update([
            'estado' => 'recibida',
            'revisado_por_secretaria' => $idUsuario,
            'fecha_revision_secretaria' => now(),
        ]);
    }

    /**
     * La Secretaría revisa los requisitos. Si existen observaciones, la solicitud pasa a estado 'observada'
     * y vuelve al estudiante para subsanar únicamente los requisitos observados.
     */
    public function observarSolicitud(SolicitudApoyo $solicitud, array $revisiones, ?string $obsGeneral, int $idUsuario): void
    {
        if (! in_array($solicitud->estado, ['enviada', 'recibida'], true)) {
            throw ValidationException::withMessages([
                'solicitud' => 'La solicitud no se encuentra en una etapa apta para observación.',
            ]);
        }

        DB::transaction(function () use ($solicitud, $revisiones, $obsGeneral, $idUsuario) {
            $hayObservados = false;

            foreach ($revisiones as $rev) {
                $idReq = (int) ($rev['id_requisito'] ?? 0);
                $estado = (string) ($rev['estado'] ?? 'conforme');
                $comentario = $rev['comentario'] ?? null;

                $doc = SolicitudRequisitoDocumento::where('id_solicitud', $solicitud->id_solicitud)
                    ->where('id_requisito', $idReq)
                    ->first();

                if ($doc) {
                    $doc->update([
                        'estado_revision' => $estado,
                        'comentario_revision' => $estado === 'observado' ? $comentario : null,
                        'revisado_por' => $idUsuario,
                        'revisado_en' => now(),
                    ]);

                    if ($estado === 'observado') {
                        $hayObservados = true;
                    }
                }
            }

            if (! $hayObservados) {
                throw ValidationException::withMessages([
                    'revisiones' => 'Para observar la solicitud debe marcar al menos un requisito como observado e ingresar su comentario.',
                ]);
            }

            $solicitud->update([
                'estado' => 'observada',
                'revisado_por_secretaria' => $idUsuario,
                'fecha_revision_secretaria' => now(),
                'observacion_general_secretaria' => $obsGeneral,
            ]);
        });
    }

    /**
     * La Secretaría remite la solicitud a Bienestar.
     * Requiere que todos los requisitos estén conformes y que la Secretaría confirme mediante casilla
     * que armó el folio fuera del sistema. El sistema NO genera folio ni PDF de carátula.
     */
    public function remitirABienestar(SolicitudApoyo $solicitud, bool $confirmaFolio, array $revisiones, int $idUsuario): void
    {
        if (! in_array($solicitud->estado, ['enviada', 'recibida'], true)) {
            throw ValidationException::withMessages([
                'solicitud' => 'Solo se pueden remitir a Bienestar las solicitudes recibidas y revisadas.',
            ]);
        }

        if (! $confirmaFolio) {
            throw ValidationException::withMessages([
                'confirma_folio_externo' => 'Debe confirmar con la casilla que armó el folio físico/externo por su cuenta antes de remitir a Bienestar.',
            ]);
        }

        DB::transaction(function () use ($solicitud, $revisiones, $idUsuario) {
            // Guardar revisiones individuales
            foreach ($revisiones as $rev) {
                $idReq = (int) ($rev['id_requisito'] ?? 0);
                $estado = (string) ($rev['estado'] ?? 'conforme');

                if ($estado === 'observado') {
                    throw ValidationException::withMessages([
                        'revisiones' => 'No se puede remitir a Bienestar una solicitud con requisitos observados. Debe observarla o marcarlos como conformes.',
                    ]);
                }

                SolicitudRequisitoDocumento::where('id_solicitud', $solicitud->id_solicitud)
                    ->where('id_requisito', $idReq)
                    ->update([
                        'estado_revision' => 'conforme',
                        'comentario_revision' => null,
                        'revisado_por' => $idUsuario,
                        'revisado_en' => now(),
                    ]);
            }

            // Comprobar que todos los requisitos obligatorios tengan documento conforme
            $solicitud->loadMissing('tipoSolicitud.requisitosActivos', 'documentosRequisitos');
            foreach ($solicitud->tipoSolicitud?->requisitosActivos ?? [] as $req) {
                if ($req->es_obligatorio) {
                    $doc = $solicitud->documentosRequisitos->firstWhere('id_requisito', $req->id_requisito);
                    if (! $doc || $doc->estado_revision !== 'conforme') {
                        throw ValidationException::withMessages([
                            'revisiones' => "El requisito obligatorio '{$req->nombre}' no tiene documentación conforme registrada.",
                        ]);
                    }
                }
            }

            $solicitud->update([
                'estado' => 'remitida',
                'folio_externo_confirmado' => true,
                'revisado_por_secretaria' => $idUsuario,
                'fecha_revision_secretaria' => now(),
                'fecha_remision_bienestar' => now(),
            ]);
            $this->notificaciones->solicitudRemitida($solicitud->id_solicitud);
        });
    }

    /**
     * El estudiante resube únicamente los requisitos que fueron observados.
     * Al guardar, la solicitud vuelve a estado 'recibida' para que la Secretaría reanude la verificación.
     */
    public function subsanarRequisitos(SolicitudApoyo $solicitud, array $archivosSubidos, int $idUsuario): void
    {
        if ($solicitud->estado !== 'observada') {
            throw ValidationException::withMessages([
                'solicitud' => 'Solo se pueden subsanar solicitudes que se encuentren en estado observada.',
            ]);
        }

        DB::transaction(function () use ($solicitud, $archivosSubidos, $idUsuario) {
            $solicitud->loadMissing('documentosRequisitos.requisito');
            $docsObservados = $solicitud->documentosRequisitos->where('estado_revision', 'observado');

            foreach ($docsObservados as $doc) {
                $idReq = $doc->id_requisito;
                if (! empty($archivosSubidos[$idReq]) && $archivosSubidos[$idReq] instanceof UploadedFile) {
                    $file = $archivosSubidos[$idReq];
                    $nuevoIdArchivo = $this->guardarArchivoPrivado($file, $idUsuario);

                    $doc->update([
                        'id_archivo' => $nuevoIdArchivo,
                        'estado_revision' => 'pendiente',
                        'comentario_revision' => null,
                        'actualizado_en' => now(),
                    ]);
                }
            }

            // Pasa a recibida para nueva revisión de Secretaría
            $solicitud->update([
                'estado' => 'recibida',
            ]);
            $this->notificaciones->solicitudNueva($solicitud->id_solicitud, true);
        });
    }

    /**
     * Bienestar dictamina una solicitud remitida con folio externo confirmado; al aprobar se abre el caso.
     */
    public function dictaminarSolicitud(SolicitudApoyo $solicitud, string $decision, ?string $observacion, int $idUsuario, CasoBienestarService $casos): void
    {
        DB::transaction(function () use ($solicitud, $decision, $observacion, $idUsuario, $casos) {
            $solicitud = SolicitudApoyo::query()->lockForUpdate()->findOrFail($solicitud->id_solicitud);
            abort_unless($solicitud->puedeDictaminarse(), 403, 'Solo se pueden dictaminar solicitudes que hayan sido remitidas por Secretaría con confirmación de foliado externo.');

            $solicitud->update([
                'estado' => $decision,
                'evaluado_por' => null,
                'observacion_dictamen' => $observacion,
                'resuelta_en' => now(),
            ]);

            if ($decision === 'aprobada' && ! CasoBienestar::where('id_solicitud_origen', $solicitud->id_solicitud)->exists()) {
                $tipo = TipoCaso::firstOrCreate(
                    ['nombre' => 'Apoyo Socioeconomico'],
                    ['descripcion' => 'Evaluacion socioeconomica y beneficios de bienestar']
                );
                $casos->abrir([
                    'id_solicitud_origen' => $solicitud->id_solicitud,
                    'id_estudiante' => $solicitud->id_estudiante,
                    'id_tipo_caso' => $tipo->id_tipo_caso,
                    'descripcion' => 'Caso abierto tras la aprobación de la solicitud de '.($solicitud->tipoSolicitud?->nombre ?? $solicitud->beneficio?->nombre ?? 'trámite UNT'),
                ], 'Apertura por solicitud aprobada', $idUsuario);
            }
        });
    }

    /**
     * Guarda el archivo en disco privado con nombre UUID único y registra en comun.archivo.
     */
    public function guardarArchivoPrivado(UploadedFile $file, int $idUsuario, string $carpeta = 'solicitudes'): int
    {
        $idModulo = DB::table('seguridad.modulo_sistema')->where('codigo', 'bienestar')->value('id_modulo') ?? 2;
        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $nombreFisico = "{$uuid}.{$ext}";
        $rutaAlmacenamiento = "bienestar/{$carpeta}/{$nombreFisico}";

        Storage::disk('local')->putFileAs("bienestar/{$carpeta}", $file, $nombreFisico);

        return (int) DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => $file->getClientOriginalName(),
            'ruta_almacenamiento' => $rutaAlmacenamiento,
            'tipo_mime' => $file->getClientMimeType() ?: 'application/octet-stream',
            'tamano_bytes' => $file->getSize() ?: 0,
            'hash_sha256' => hash_file('sha256', $file->getRealPath()),
            'subido_por' => $idUsuario,
            'subido_en' => now(),
        ], 'id_archivo');
    }
}
