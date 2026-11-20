<?php

namespace App\Modules\SeguimientoEgresado\Services\Invitaciones;

/** Qué pasó con el correo de una invitación. Solo Enviado cuenta como entregado al servidor de correo. */
enum EstadoEnvio: string
{
    /** El servidor de correo aceptó el mensaje. */
    case Enviado = 'enviado';

    /** El correo se escribió en el registro (MAIL_MAILER=log) o no salió a ningún servidor: no se entregó. */
    case Simulado = 'simulado';

    /** El envío está desactivado (SG_CORREO_ENVIAR=false). */
    case Desactivado = 'desactivado';

    /** El egresado no tiene correo registrado. */
    case SinCorreo = 'sin_correo';

    /** El servidor de correo rechazó el mensaje o no se pudo conectar. */
    case Fallido = 'fallido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Enviado => 'Enviado por correo',
            self::Simulado => 'Enlace listo para entregar',
            self::Desactivado => 'Enlace listo para entregar',
            self::SinCorreo => 'Sin correo registrado',
            self::Fallido => 'Falló el envío',
        };
    }

    /** Clase de insignia de los estilos compartidos (sg-badge ...). */
    public function insignia(): string
    {
        return match ($this) {
            self::Enviado => 'success',
            self::Fallido => 'danger',
            default => 'warning',
        };
    }
}
