<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Models\ArticuloInformativo;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class ArticuloInformativoPolicy
{
    public function viewAny(?User $user): bool
    {
        return true; // Acceso público a la lista de artículos
    }

    public function view(?User $user, ArticuloInformativo $articulo): bool
    {
        if ($articulo->estado === 'publicado') {
            return true;
        }

        return $user !== null && BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function create(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function update(User $user, ArticuloInformativo $articulo): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function delete(User $user, ArticuloInformativo $articulo): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }
}
