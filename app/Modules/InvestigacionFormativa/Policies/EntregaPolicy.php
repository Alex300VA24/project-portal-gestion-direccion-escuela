<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Models\Proyecto;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;

final class EntregaPolicy
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
    ) {}

    public function create(User $user, Proyecto $proyecto): bool
    {
        return $this->seguro(function () use ($user, $proyecto): bool {
            if (! $user->can('investigacion-formativa.submissions.create')) {
                return false;
            }

            return $this->acceso->esCoordinadorDeGrupo(
                $this->acceso->resolver($user),
                (int) $proyecto->id_grupo,
            );
        });
    }

    public function download(User $user, Entrega $entrega): bool
    {
        return $this->seguro(function () use ($user, $entrega): bool {
            if (! $user->can('investigacion-formativa.files.download')) {
                return false;
            }

            $idGrupo = $this->acceso->idGrupoDeProyecto((int) $entrega->id_proyecto);

            return $idGrupo !== null && $this->acceso->puedeVerGrupo($user, $idGrupo);
        });
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
