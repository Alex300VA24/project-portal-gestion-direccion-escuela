<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Models\User;

/** Debe resolver la identidad desde el guard y la fuente institucional, nunca desde la solicitud. */
interface ProveedorIdentidadDocente
{
    public function docenteId(User $usuario): ?int;
}
