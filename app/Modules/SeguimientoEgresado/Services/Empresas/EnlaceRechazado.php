<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use RuntimeException;

/** El enlace dejó de estar disponible al momento de guardar la respuesta. */
final class EnlaceRechazado extends RuntimeException
{
    public function __construct(public readonly MotivoEnlace $motivo)
    {
        parent::__construct($motivo->mensaje());
    }
}
