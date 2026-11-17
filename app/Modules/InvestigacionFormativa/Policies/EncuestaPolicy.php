<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;

final class EncuestaPolicy
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
    ) {}

    public function responder(User $user, Encuesta $encuesta): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.surveys.respond')
            && $this->acceso->puedeParticipar($user, (int) $encuesta->id_seccion));
    }

    public function gestionar(User $user, Encuesta $encuesta): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.surveys.manage')
            && $this->acceso->puedeVerSeccion($user, (int) $encuesta->id_seccion, 'director_escuela'));
    }

    private function seguro(callable $check): bool
    {
        try {
            return $check();
        } catch (AccesoDenegado) {
            return false;
        }
    }
}
