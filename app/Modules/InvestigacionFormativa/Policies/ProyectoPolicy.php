<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Models\Proyecto;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;

final class ProyectoPolicy
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
    ) {}

    public function view(User $user, Proyecto $proyecto): bool
    {
        return $this->seguro(function () use ($user, $proyecto): bool {
            return $user->can('investigacion-formativa.view')
                && $this->acceso->puedeVerGrupo($user, (int) $proyecto->id_grupo);
        });
    }

    public function update(User $user, Proyecto $proyecto): bool
    {
        return $this->seguro(function () use ($user, $proyecto): bool {
            if (! $user->can('investigacion-formativa.projects.update')) {
                return false;
            }

            return $this->acceso->esCoordinadorDeGrupo(
                $this->acceso->resolver($user),
                (int) $proyecto->id_grupo,
            );
        });
    }

    public function review(User $user, Proyecto $proyecto): bool
    {
        return $this->seguro(function () use ($user, $proyecto): bool {
            if (! $user->can('investigacion-formativa.groups.review')) {
                return false;
            }

            $idSeccion = $this->acceso->idSeccionDeGrupo((int) $proyecto->id_grupo);

            return $idSeccion !== null
                && $this->acceso->puedeVerSeccion($user, $idSeccion, 'profesor');
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
