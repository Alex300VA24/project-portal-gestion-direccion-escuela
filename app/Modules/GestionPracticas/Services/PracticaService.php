<?php

namespace App\Modules\GestionPracticas\Services;

use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\ObservacionPractica;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Support\Actor;
use App\Shared\Services\ModuleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de la práctica: registrada → en_revision → (observada ↔ en_revision) → en_curso → finalizada.
 * El cierre (finalizada) lo realiza EvaluacionService.
 */
final class PracticaService extends ModuleService
{
    public function __construct(private readonly DocumentoService $documentos) {}

    /**
     * El estudiante seleccionado registra los datos de su práctica y adjunta la carta de aceptación.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(Actor $actor, Postulacion $postulacion, array $datos, UploadedFile $carta): Practica
    {
        if ((int) $postulacion->id_estudiante !== $actor->requiereEstudiante()) {
            throw new AuthorizationException('Esta postulación pertenece a otro estudiante.');
        }

        if ($postulacion->estado !== Postulacion::SELECCIONADO) {
            throw ValidationException::withMessages(['postulacion' => 'Solo puede registrar la práctica de una postulación en la que fue seleccionado.']);
        }

        if ($postulacion->id_practica !== null) {
            throw ValidationException::withMessages(['postulacion' => 'Esta postulación ya tiene una práctica registrada.']);
        }

        $convocatoria = $postulacion->convocatoria;

        return DB::transaction(function () use ($actor, $postulacion, $datos, $carta, $convocatoria): Practica {
            $practica = Practica::create([
                ...$this->campos($datos),
                'id_estudiante' => $actor->requiereEstudiante(),
                'id_empresa' => $convocatoria->id_empresa,
                'tipo_practica' => $convocatoria->tipo_practica,
                'estado' => Practica::REGISTRADA,
            ])->refresh();

            $postulacion->update(['id_practica' => $practica->id_practica]);
            $this->documentos->subir($practica, $actor, $carta, 'carta_aceptacion');

            return $practica;
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Practica $practica, array $datos): Practica
    {
        $this->exigir($practica, [Practica::REGISTRADA, Practica::OBSERVADA], 'editar');

        $practica->update($this->campos($datos));

        return $practica;
    }

    /** El estudiante envía (o reenvía, tras corregir) la información para validación. */
    public function enviarARevision(Practica $practica): Practica
    {
        $this->exigir($practica, [Practica::REGISTRADA, Practica::OBSERVADA], 'enviar a revisión');

        $tieneCarta = $practica->documentos()
            ->where('tipo_documento', 'carta_aceptacion')
            ->where('estado', '<>', DocumentoPractica::REEMPLAZADO)
            ->exists();

        if (! $tieneCarta) {
            throw ValidationException::withMessages(['archivo' => 'Debe adjuntar la carta de aceptación antes de enviar.']);
        }

        DB::transaction(function () use ($practica): void {
            $practica->observaciones()
                ->where('estado', ObservacionPractica::PENDIENTE)
                ->update(['estado' => ObservacionPractica::SUBSANADA, 'subsanada_en' => now()]);

            $practica->update(['estado' => Practica::EN_REVISION, 'enviada_en' => now()]);
        });

        return $practica;
    }

    /** El administrador valida la práctica, asigna al docente supervisor y la pasa a «en curso». */
    public function validar(Practica $practica, Actor $admin, int $idDocente): Practica
    {
        $this->exigir($practica, [Practica::EN_REVISION], 'validar');

        $existe = DB::table('core.docente')->where('id_docente', $idDocente)->exists();

        if (! $existe) {
            throw ValidationException::withMessages(['id_docente' => 'El docente seleccionado no existe o no está activo. Elija uno de la lista.']);
        }

        DB::transaction(function () use ($practica, $admin, $idDocente): void {
            $idAdmin = $admin->requiereUsuario();

            $practica->documentos()
                ->where('tipo_documento', 'carta_aceptacion')
                ->where('estado', DocumentoPractica::PENDIENTE)
                ->update(['estado' => DocumentoPractica::APROBADO, 'revisado_por' => $idAdmin, 'revisado_en' => now()]);

            $practica->update([
                'estado' => Practica::EN_CURSO,
                'validado_por' => $idAdmin,
                'validada_en' => now(),
                'id_docente_supervisor' => $idDocente,
                'supervisor_asignado_por' => $idAdmin,
                'supervisor_asignado_en' => now(),
            ]);
        });

        return $practica;
    }

    /** El administrador devuelve la práctica con una observación (opcionalmente sobre un documento). */
    public function observar(Practica $practica, Actor $admin, string $descripcion, ?int $idDocumento = null): Practica
    {
        $this->exigir($practica, [Practica::EN_REVISION], 'observar');

        if ($idDocumento !== null && ! $practica->documentos()->where('id_documento', $idDocumento)->exists()) {
            throw ValidationException::withMessages(['id_documento' => 'El documento no pertenece a esta práctica.']);
        }

        DB::transaction(function () use ($practica, $admin, $descripcion, $idDocumento): void {
            $practica->observaciones()->create([
                'id_documento' => $idDocumento,
                'descripcion' => $descripcion,
                'registrado_por' => $admin->requiereUsuario(),
            ]);

            if ($idDocumento !== null) {
                $practica->documentos()->where('id_documento', $idDocumento)->update([
                    'estado' => DocumentoPractica::OBSERVADO,
                    'revisado_por' => $admin->requiereUsuario(),
                    'revisado_en' => now(),
                ]);
            }

            $practica->update(['estado' => Practica::OBSERVADA]);
        });

        return $practica;
    }

    /** @param list<string> $permitidos */
    private function exigir(Practica $practica, array $permitidos, string $accion): void
    {
        if (! in_array($practica->estado, $permitidos, true)) {
            throw ValidationException::withMessages([
                'estado' => "No se puede {$accion} una práctica en estado «".str_replace('_', ' ', $practica->estado).'».',
            ]);
        }
    }

    /** @param array<string, mixed> $datos @return array<string, mixed> */
    private function campos(array $datos): array
    {
        return array_intersect_key($datos, array_flip([
            'id_periodo', 'area', 'funciones', 'fecha_inicio', 'fecha_fin', 'horas_totales',
        ]));
    }
}
