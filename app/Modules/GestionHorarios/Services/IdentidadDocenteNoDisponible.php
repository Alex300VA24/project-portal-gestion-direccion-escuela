<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;

/** Sin vínculo oficial entre users y seguridad.usuario no se concede identidad docente. */
final class IdentidadDocenteNoDisponible implements ProveedorIdentidadDocente
{
    public function docenteId(User $usuario): ?int
    {
        return null;
    }
}
