<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

enum MotivoAcceso: string
{
    case Permitido = 'permitido';
    /** Token desconocido, invitación anulada o expirada, o vencida por fecha. */
    case EnlaceNoValido = 'enlace_no_valido';
}
