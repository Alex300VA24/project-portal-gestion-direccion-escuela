<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

use Carbon\CarbonImmutable;

/**
 * Entrada del egresado mediante el enlace personal de su invitación. El enlace es el único dato que se pide:
 * quien lo tiene entra directamente a su propio expediente, mientras la invitación no venza ni se anule.
 * (Para esta etapa no se piden matrícula ni documento; el acceso queda limitado al expediente de la invitación.)
 */
final class EnlaceInvitacion
{
    /** Una invitación completada sigue permitiendo consultar el propio expediente hasta que venza. */
    private const ESTADOS_CON_ACCESO = ['PENDIENTE', 'COMPLETADA'];

    public function __construct(private readonly InvitacionRepositorio $invitaciones) {}

    /** Misma regla que debe usar quien emite la invitación: token_hash = SHA-256 en hexadecimal. */
    public static function hashDe(string $tokenPlano): string
    {
        return hash('sha256', $tokenPlano);
    }

    public function abrir(string $tokenPlano): ResultadoAcceso
    {
        $invitacion = $tokenPlano === '' ? null : $this->invitaciones->buscarPorHash(self::hashDe($tokenPlano));

        if ($invitacion === null
            || ! in_array($invitacion->estado, self::ESTADOS_CON_ACCESO, true)
            || $invitacion->expiraEn->lessThanOrEqualTo(CarbonImmutable::now())) {
            return ResultadoAcceso::rechazado(MotivoAcceso::EnlaceNoValido);
        }

        return ResultadoAcceso::permitido($invitacion);
    }
}
