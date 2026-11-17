<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class CitaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BienestarPermissions::SOLICITAR)
            || $user->can(BienestarPermissions::GESTIONAR);
    }

    public function view(User $user, Cita $cita): bool
    {
        if ($user->can(BienestarPermissions::GESTIONAR)) {
            return true;
        }

        if ($user->can(BienestarPermissions::SOLICITAR)) {
            $actor = ActorBienestarResolver::resolve($user);

            return $actor->idEstudiante !== null && $cita->caso?->id_estudiante === $actor->idEstudiante;
        }

        return false;
    }
}
