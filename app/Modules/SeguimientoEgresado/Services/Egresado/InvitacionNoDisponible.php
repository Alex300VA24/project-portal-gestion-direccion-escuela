<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

/** La invitación no admite una respuesta nueva; no se guardó nada. */
final class InvitacionNoDisponible extends \RuntimeException
{
    public function __construct(public readonly MotivoInvitacion $motivo)
    {
        parent::__construct("La invitación no está disponible: {$motivo->value}.");
    }
}
