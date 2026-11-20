<?php

namespace App\Modules\GestionHorarios\Contracts;

/** Corrobora la identidad explícita en el catálogo institucional. */
interface IdentidadCursoPlan
{
    public function coincide(int $escuela, int $plan, int $curso, int $cursoPlan): bool;
}
