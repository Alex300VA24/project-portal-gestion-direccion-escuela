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
        return BienestarPermissions::check($user, BienestarPermissions::SOLICITAR)
            || BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function view(User $user, Cita $cita): bool
    {
        if (BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)) {
            return true;
        }

        if (BienestarPermissions::check($user, BienestarPermissions::SOLICITAR)) {
            $actor = ActorBienestarResolver::resolve($user);

            return $actor->idEstudiante !== null && $cita->caso?->id_estudiante === $actor->idEstudiante;
        }

        return false;
    }
}
