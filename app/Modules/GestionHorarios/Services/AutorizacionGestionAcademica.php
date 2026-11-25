<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use Throwable;

/** Deniega por defecto; la escuela debe comprobarse por separado. */
final class AutorizacionGestionAcademica
{
    public function permite(User $usuario): bool
    {
        try {
            if (! (new RegistroPolicy)->viewAny($usuario) || ! app()->bound(ProveedorPermisoGestionAcademica::class)) {
                return false;
            }

            return app(ProveedorPermisoGestionAcademica::class)->puedeGestionar($usuario) === true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function exigir(User $usuario): void
    {
        abort_unless($this->permite($usuario), 403);
    }
}
