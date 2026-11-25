<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class SecretariaPolicy
{
    public function viewAny(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SECRETARIA);
    }

    public function filtrar(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SECRETARIA);
    }

    public function revisar(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SECRETARIA);
    }
}
