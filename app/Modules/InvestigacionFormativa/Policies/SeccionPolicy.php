<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Shared\Models\Core\Seccion;

final class SeccionPolicy
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
    ) {}

    public function verComoDirector(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.view')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'director_escuela'));
    }

    public function verComoProfesor(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.view')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'profesor'));
    }

    public function verComoAlumno(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.view')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'alumno'));
    }

    public function habilitar(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.courses.manage')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'director_escuela'));
    }

    public function configurar(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.configuration.manage')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'profesor'));
    }

    public function gestionarEncuesta(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.surveys.manage')
            && $this->acceso->puedeVerSeccion($user, (int) $seccion->id_seccion, 'director_escuela'));
    }

    public function prepararEncuesta(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $this->acceso->puedeGestionarEncuesta(
            $user,
            (int) $seccion->id_seccion,
        ));
    }

    public function responderEncuesta(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.surveys.respond')
            && $this->acceso->puedeParticipar($user, (int) $seccion->id_seccion));
    }

    public function verResultados(User $user, Seccion $seccion): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.reports.view')
            && $this->acceso->puedeVerResultados($user, (int) $seccion->id_seccion));
    }

    public function crearGrupo(User $user, Seccion $seccion): bool
    {
        return $user->can('investigacion-formativa.groups.create')
            && $this->acceso->puedeCrearGrupo($user, (int) $seccion->id_seccion);
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
