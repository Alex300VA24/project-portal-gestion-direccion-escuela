<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use RuntimeException;

/** La invitación no se puede emitir; el mensaje se muestra a Secretaría junto al campo indicado. */
final class EnlaceNoGenerable extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'id_campania')
    {
        parent::__construct($mensaje);
    }
}
