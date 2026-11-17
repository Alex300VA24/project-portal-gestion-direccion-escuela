<?php

namespace App\Modules\Bienestar\Policies;

use App\Models\User;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;

final class AlertaRiesgoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BienestarPermissions::GESTIONAR)
            || $user->can(BienestarPermissions::REPORTAR);
    }

    public function view(User $user, AlertaRiesgo $alerta): bool
    {
        if ($user->can(BienestarPermissions::GESTIONAR)) {
            return true;
        }

        if ($user->can(BienestarPermissions::REPORTAR)) {
            $actor = ActorBienestarResolver::resolve($user);

            return $actor->idDocente !== null && $alerta->id_docente === $actor->idDocente;
        }

        return false;
    }

    public function reportar(User $user): bool
    {
        return $user->can(BienestarPermissions::REPORTAR);
    }

    public function atender(User $user, AlertaRiesgo $alerta): bool
    {
        return $user->can(BienestarPermissions::GESTIONAR);
    }
}
