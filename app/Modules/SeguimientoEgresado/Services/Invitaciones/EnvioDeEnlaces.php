<?php

namespace App\Modules\SeguimientoEgresado\Services\Invitaciones;

use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail;
use App\Modules\SeguimientoEgresado\Models\CatCanalEnvio;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía por correo el enlace personal de una invitación y deja registrado lo que realmente ocurrió:
 *  - enviado_en solo se marca cuando el servidor de correo aceptó el mensaje;
 *  - si el envío falla se marca entrega_fallida;
 *  - con MAIL_MAILER=log o array el mensaje no sale a ningún servidor, así que no se marca como enviado.
 * En todos los casos el enlace sigue disponible en pantalla para que la Secretaría lo entregue por otro medio.
 */
final class EnvioDeEnlaces
{
    /** Controladores que no entregan el mensaje a un servidor real. */
    private const MAILERS_SIN_ENTREGA = ['log', 'array'];

    public function enviar(InvitacionEgresado $invitacion, string $tokenPlano): ResultadoEnvio
    {
        if (! config('seguimiento-egresado.correo.enviar')) {
            return new ResultadoEnvio(EstadoEnvio::Desactivado);
        }

        $invitacion->loadMissing(['egresado.contactoVigente', 'campania']);
        $egresado = $invitacion->egresado;

        [$direccion, $canal] = $this->direccionDe($egresado, $invitacion->proposito);
        $redirigidoA = config('seguimiento-egresado.correo.redirigir_a');
        $destino = $redirigidoA ?: $direccion;

        if (! $destino) {
            return new ResultadoEnvio(EstadoEnvio::SinCorreo);
        }

        // Lista de correos permitidos: se revisa la dirección real del egresado, no la de redirección.
        if (! $this->estaPermitido($direccion)) {
            return new ResultadoEnvio(EstadoEnvio::Desactivado);
        }

        $correo = new InvitacionEgresadoMail(
            nombre: $egresado->nombres,
            url: url('seguimiento-egresado/acceso/'.$tokenPlano),
            proposito: $invitacion->proposito,
            campania: $invitacion->campania?->nombre,
            venceEn: $invitacion->expira_en ? Carbon::parse($invitacion->expira_en) : null,
        );

        try {
            Mail::to($destino)->send($correo);
        } catch (Throwable $e) {
            // Nunca se registra el enlace: el mensaje de la excepción puede traer datos del servidor, no el token.
            Log::warning('No se pudo enviar el correo de la invitación '.$invitacion->id.': '.$e->getMessage());
            $invitacion->update(['entrega_fallida' => true]);

            return new ResultadoEnvio(EstadoEnvio::Fallido, $destino, $redirigidoA !== null);
        }

        if (in_array(config('mail.default'), self::MAILERS_SIN_ENTREGA, true)) {
            return new ResultadoEnvio(EstadoEnvio::Simulado, $destino, $redirigidoA !== null);
        }

        $invitacion->update([
            'enviado_en' => now(),
            'entrega_fallida' => false,
            'id_canal_envio' => CatCanalEnvio::where('codigo', $canal)->value('id') ?? $invitacion->id_canal_envio,
        ]);

        return new ResultadoEnvio(EstadoEnvio::Enviado, $destino, $redirigidoA !== null);
    }

    /** Sin lista configurada se puede enviar a cualquiera; con lista, solo a las direcciones que figuran en ella. */
    private function estaPermitido(?string $direccion): bool
    {
        $permitidos = config('seguimiento-egresado.correo.permitidos', []);

        return $permitidos === [] || ($direccion !== null && in_array(mb_strtolower(trim($direccion)), $permitidos, true));
    }

    /**
     * Correo y canal del catálogo. El contacto inicial va al correo institucional (el alumno aún no dejó otro); las campañas
     * de seguimiento van solo al correo personal que el egresado dejó, nunca al institucional.
     *
     * @return array{0: ?string, 1: string}
     */
    private function direccionDe(Egresado $egresado, string $proposito): array
    {
        if ($proposito === 'CONTACTO_INICIAL') {
            return [$egresado->correo_institucional ?: null, 'CORREO_INSTITUCIONAL'];
        }

        return [$egresado->contactoVigente?->correo_personal ?: null, 'CORREO_PERSONAL'];
    }
}
