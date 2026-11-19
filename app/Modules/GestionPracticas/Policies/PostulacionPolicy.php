<?php

namespace App\Modules\GestionPracticas\Policies;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;

final class PostulacionPolicy
{
    /** Solo el estudiante que postuló gestiona su postulación. */
    public function gestionar(User $user, Postulacion $postulacion): bool
    {
        return $user->can(Acceso::POSTULAR)
            && Actor::de($user)->estudianteId === (int) $postulacion->id_estudiante;
    }
}
