<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;

/** Exclusivo de pruebas; no se registra en la aplicación. */
final class ProveedorPermisoGestionFicticio implements ProveedorPermisoGestionAcademica
{
    public function __construct(public bool $permitido = true) {}

    public function puedeGestionar(User $usuario): bool
    {
        return $this->permitido;
    }
}
