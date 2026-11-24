<?php

namespace App\Modules\InvestigacionFormativa\Policies;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;

final class GrupoPolicy
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
    ) {}

    public function view(User $user, Grupo $grupo): bool
    {
        return $this->seguro(function () use ($user, $grupo): bool {
            if (! $user->can('investigacion-formativa.view')) {
                return false;
            }

            $idSeccion = (int) $grupo->id_seccion;

            if ($this->acceso->puedeVerSeccion($user, $idSeccion, 'director_escuela')) {
                return true;
            }

            if ($this->acceso->puedeVerSeccion($user, $idSeccion, 'profesor')) {
                return true;
            }

            $idEscuela = $this->datos->escuelaDeSeccion($idSeccion);

            if ($idEscuela === null) {
                return false;
            }

            $actor = $this->acceso->resolver($user);
            $idEstudiante = $this->acceso->idEstudianteEnEscuela($actor, $idEscuela);

            return $idEstudiante !== null
                && $this->acceso->miembroDeGrupo((int) $grupo->id_grupo, $idEstudiante) !== null;
        });
    }

    public function revisarTitulo(User $user, Grupo $grupo): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.groups.review')
            && $this->acceso->puedeVerSeccion($user, (int) $grupo->id_seccion, 'profesor'));
    }

    public function evaluar(User $user, Grupo $grupo): bool
    {
        return $this->seguro(fn (): bool => $user->can('investigacion-formativa.evaluations.manage')
            && $this->acceso->puedeVerSeccion($user, (int) $grupo->id_seccion, 'profesor'));
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
