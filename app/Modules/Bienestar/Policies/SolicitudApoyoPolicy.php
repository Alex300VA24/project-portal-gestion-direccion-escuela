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
            || BienestarPermissions::check($user, BienestarPermissions::SECRETARIA)
            || BienestarPermissions::check($user, BienestarPermissions::GESTIONAR);
    }

    public function view(User $user, SolicitudApoyo $solicitud): bool
    {
        if (BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)) {
            return in_array($solicitud->estado, ['remitida', 'aprobada', 'rechazada'], true);
        }

        if (BienestarPermissions::check($user, BienestarPermissions::SECRETARIA)) {
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

    public function revisar(User $user, SolicitudApoyo $solicitud): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::SECRETARIA);
    }

    public function subsanar(User $user, SolicitudApoyo $solicitud): bool
    {
        if (! BienestarPermissions::check($user, BienestarPermissions::SOLICITAR)) {
            return false;
        }

        $actor = ActorBienestarResolver::resolve($user);

        return $actor->idEstudiante !== null
            && $solicitud->id_estudiante === $actor->idEstudiante
            && $solicitud->estado === 'observada';
    }

    public function dictaminar(User $user, SolicitudApoyo $solicitud): bool
    {
        return BienestarPermissions::check($user, BienestarPermissions::GESTIONAR)
            && $solicitud->puedeDictaminarse();
    }
}
