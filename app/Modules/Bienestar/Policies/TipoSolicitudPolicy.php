<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class TipoSolicitudPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, TipoSolicitud $tipoSolicitud): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function update(User $user, TipoSolicitud $tipoSolicitud): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function delete(User $user, TipoSolicitud $tipoSolicitud): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }
}
