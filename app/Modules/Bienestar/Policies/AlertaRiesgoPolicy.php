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
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)
            || BienestarPermissions::check($user, BienestarPermissions::SECRETARIA)
            || BienestarPermissions::check($user, BienestarPermissions::REPORTAR);
    }

    public function view(User $user, AlertaRiesgo $alerta): bool
    {
        if (BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)) {
            return true;
        }

        if (BienestarPermissions::check($user, BienestarPermissions::SECRETARIA)) {
            return true;
        }

        if (BienestarPermissions::check($user, BienestarPermissions::REPORTAR)) {
            $actor = ActorBienestarResolver::resolve($user);

            return $actor->idDocente !== null && $alerta->id_docente === $actor->idDocente;
        }

        return false;
    }

    public function reportar(User $user): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::REPORTAR);
    }

    public function filtrar(User $user, AlertaRiesgo $alerta): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SECRETARIA);
    }

    public function reabrir(User $user, AlertaRiesgo $alerta): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function atender(User $user, AlertaRiesgo $alerta): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }
}
