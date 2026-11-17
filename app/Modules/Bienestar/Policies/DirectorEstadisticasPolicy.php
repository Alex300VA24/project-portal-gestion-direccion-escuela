<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class DirectorEstadisticasPolicy
{
    public function view(User $user): bool
    {
        return $user->can(BienestarPermissions::ESTADISTICAS);
    }

    public function mutate(User $user): bool
    {
        return false; // Director tiene acceso estrictamente de solo lectura (403 en cualquier mutación)
    }
}
