<?php

namespace App\Modules\GestionPracticas\Policies;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;

final class ConvocatoriaPolicy
{
    /** El administrador revisa cualquiera; la empresa ve las suyas; el estudiante solo las publicadas. */
    public function view(User $user, Convocatoria $convocatoria): bool
    {
        return $user->can(Acceso::CONVOCATORIAS_REVISAR)
            || $this->esDeLaEmpresa($user, $convocatoria)
            || ($user->can(Acceso::CONVOCATORIAS_VER) && $convocatoria->estado === Convocatoria::PUBLICADA);
    }

    /** Editar, enviar a revisión, cerrar o anular: solo la empresa propietaria. */
    public function gestionar(User $user, Convocatoria $convocatoria): bool
    {
        return $this->esDeLaEmpresa($user, $convocatoria);
    }

    public function revisar(User $user, Convocatoria $convocatoria): bool
    {
        return $user->can(Acceso::CONVOCATORIAS_REVISAR);
    }

    private function esDeLaEmpresa(User $user, Convocatoria $convocatoria): bool
    {
        return $user->can(Acceso::CONVOCATORIAS_GESTIONAR)
            && Actor::de($user)->empresaId === (int) $convocatoria->id_empresa;
    }
}
