<?php

namespace App\Modules\GestionHorarios\Engine;

use RuntimeException;
use Throwable;

final class ErrorEjecucionPythonPrueba extends RuntimeException
{
    public function __construct(public readonly string $diagnostico, ?Throwable $anterior = null)
    {
        parent::__construct('El proceso de prueba no completó la ejecución.', 0, $anterior);
    }
}
