<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Models\User;

/** Contrato pendiente de aprobación por Seguridad; no acredita una escuela. */
interface ProveedorPermisoGestionAcademica
{
    public function puedeGestionar(User $usuario): bool;
}
