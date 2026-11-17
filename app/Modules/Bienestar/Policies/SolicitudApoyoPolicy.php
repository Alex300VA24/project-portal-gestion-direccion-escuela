<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class SolicitudApoyoPolicy
{
    public function viewAny(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SOLICITAR)
            || BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function view(User $user, SolicitudApoyo $solicitud): bool
    {
        if (BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)) {
            return true;
        }

        if (BienestarPermissions::check($user, BienestarPermissions::SOLICITAR)) {
            $actor = ActorBienestarResolver::resolve($user);

            return $actor->idEstudiante !== null && $solicitud->id_estudiante === $actor->idEstudiante;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SOLICITAR);
    }

    public function dictaminar(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }
}
