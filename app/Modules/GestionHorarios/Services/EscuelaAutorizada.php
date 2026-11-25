<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;

/** Bloqueo explícito hasta disponer del contrato institucional de adscripción. */
final class EscuelaAutorizada
{
    public function obtener(User $usuario): ?int
    {
        // El guard actual no proporciona una relación verificable usuario–escuela.
        return null;
    }
}
