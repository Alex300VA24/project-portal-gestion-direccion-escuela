<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Illuminate\Support\Facades\DB;

final class AccesoInvestigacionService
{
    public function __construct(
        private readonly IdentidadInvestigacionService $identidad,
        private readonly DatosAcademicosService $datos,
    ) {}

    public function resolver(User $user): ActorInvestigacion
    {
        return $this->identidad->resolver($user);
    }

    public function puedeVerSeccion(User $user, int $idSeccion, string $workspace): bool
    {
        $actor = $this->identidad->resolver($user);

        if (! $actor->tieneRol($workspace)) {
            return false;
        }

        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->estado !== 'Activa') {
            return false;
        }

        return match ($workspace) {
            'director_escuela' => $seccion->id_escuela !== null
                && $this->tieneAlcanceEscuela($actor, $seccion->id_escuela),
            'profesor' => $actor->idDocente !== null
                && $seccion->id_docente_responsable === $actor->idDocente,
            'alumno' => $seccion->id_escuela !== null
                && $this->miembroDeAlgunGrupo($actor, $idSeccion, $seccion->id_escuela) !== null,
            default => false,
        };
    }

    public function puedeVerResultados(User $user, int $idSeccion): bool
    {
        $actor = $this->identidad->resolver($user);
        $seccion = $this->datos->seccionConEscuela($idSeccion);

        return $actor->tieneRol('director_escuela')
            && $seccion !== null
            && $seccion->id_escuela !== null
            && $this->tieneAlcanceEscuela($actor, $seccion->id_escuela);
    }

    public function exigirVerSeccion(User $user, int $idSeccion, string $workspace): ActorInvestigacion
    {
        $actor = $this->identidad->resolver($user);

        if (! $this->puedeVerSeccion($user, $idSeccion, $workspace)) {
            throw new AccesoDenegado(
                'No tiene acceso a la sección solicitada para este rol.',
            );
        }

        return $actor;
    }

    public function tieneAlcanceEscuela(ActorInvestigacion $actor, int $idEscuela): bool
    {
        return DB::table('investigacion_formativa.director_escuela')
            ->where('user_id', $actor->user->id)
            ->where('id_escuela', $idEscuela)
            ->where('activo', true)
            ->exists();
    }

    public function idEstudianteEnEscuela(ActorInvestigacion $actor, int $idEscuela): ?int
    {
        return $this->datos->estudianteActivoDePersonaEnEscuela($actor->idPersona, $idEscuela);
    }

    public function participanteActivo(int $idSeccion, int $idEstudiante): bool
    {
        return DB::table('investigacion_formativa.participante_seccion')
            ->where('id_seccion', $idSeccion)
            ->where('id_estudiante', $idEstudiante)
            ->where('estado', 'activo')
            ->exists();
    }

    public function puedeParticipar(User $user, int $idSeccion): bool
    {
        try {
            $actor = $this->identidad->resolver($user);
        } catch (AccesoDenegado) {
            return false;
        }

        $seccion = $this->datos->seccionConEscuela($idSeccion);

        if ($seccion === null || $seccion->estado !== 'Activa' || $seccion->id_escuela === null) {
            return false;
        }

        $idEstudiante = $this->idEstudianteEnEscuela($actor, $seccion->id_escuela);

        return $idEstudiante !== null && $this->participanteActivo($idSeccion, $idEstudiante);
    }

    public function puedeCrearGrupo(User $user, int $idSeccion): bool
    {
        try {
            $actor = $this->identidad->resolver($user);
        } catch (AccesoDenegado) {
            return false;
        }

        return $actor->tieneRol('alumno') && $this->puedeParticipar($user, $idSeccion);
    }

    public function verEnAlgunEspacio(User $user, int $idSeccion): bool
    {
        foreach (['director_escuela', 'profesor', 'alumno'] as $workspace) {
            if ($this->puedeVerSeccion($user, $idSeccion, $workspace)) {
                return true;
            }
        }

        return false;
    }

    public function idSeccionDeGrupo(int $idGrupo): ?int
    {
        $idSeccion = DB::table('investigacion_formativa.grupo')
            ->where('id_grupo', $idGrupo)
            ->value('id_seccion');

        return $idSeccion === null ? null : (int) $idSeccion;
    }

    public function idSeccionDeProyecto(int $idProyecto): ?int
    {
        $idGrupo = $this->idGrupoDeProyecto($idProyecto);

        return $idGrupo === null ? null : $this->idSeccionDeGrupo($idGrupo);
    }

    public function idGrupoDeProyecto(int $idProyecto): ?int
    {
        $idGrupo = DB::table('investigacion_formativa.proyecto')
            ->where('id_proyecto', $idProyecto)
            ->value('id_grupo');

        return $idGrupo === null ? null : (int) $idGrupo;
    }

    public function puedeVerGrupo(User $user, int $idGrupo): bool
    {
        $idSeccion = $this->idSeccionDeGrupo($idGrupo);

        if ($idSeccion === null) {
            return false;
        }

        if ($this->puedeVerSeccion($user, $idSeccion, 'director_escuela')
            || $this->puedeVerSeccion($user, $idSeccion, 'profesor')) {
            return true;
        }

        $actor = $this->resolver($user);

        if (! $actor->tieneRol('alumno')) {
            return false;
        }

        $idEscuela = $this->datos->escuelaDeSeccion($idSeccion);

        if ($idEscuela === null) {
            return false;
        }

        $idEstudiante = $this->idEstudianteEnEscuela($actor, $idEscuela);

        return $idEstudiante !== null
            && $this->miembroDeGrupo($idGrupo, $idEstudiante) !== null
            && $this->participanteActivo($idSeccion, $idEstudiante);
    }

    public function esCoordinadorDeGrupo(ActorInvestigacion $actor, int $idGrupo): bool
    {
        $idSeccion = $this->idSeccionDeGrupo($idGrupo);

        if ($idSeccion === null) {
            return false;
        }

        $idEscuela = $this->datos->escuelaDeSeccion($idSeccion);

        if ($idEscuela === null) {
            return false;
        }

        $idEstudiante = $this->idEstudianteEnEscuela($actor, $idEscuela);

        if ($idEstudiante === null) {
            return false;
        }

        $miembro = $this->miembroDeGrupo($idGrupo, $idEstudiante);

        return $miembro !== null
            && $miembro->es_coordinador
            && $this->participanteActivo($idSeccion, $idEstudiante);
    }

    public function esCoordinadorEnSeccion(ActorInvestigacion $actor, int $idSeccion): bool
    {
        $idEscuela = $this->datos->escuelaDeSeccion($idSeccion);

        if ($idEscuela === null) {
            return false;
        }

        $idEstudiante = $this->idEstudianteEnEscuela($actor, $idEscuela);

        if ($idEstudiante === null) {
            return false;
        }

        $esCoordinador = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->where('g.id_seccion', $idSeccion)
            ->where('ge.id_estudiante', $idEstudiante)
            ->where('ge.es_coordinador', true)
            ->exists();

        return $esCoordinador && $this->participanteActivo($idSeccion, $idEstudiante);
    }

    /**
     * @return object{id_grupo: int, es_coordinador: bool}|null
     */
    public function miembroDeGrupo(int $idGrupo, int $idEstudiante): ?object
    {
        $miembro = DB::table('investigacion_formativa.grupo_estudiante')
            ->where('id_grupo', $idGrupo)
            ->where('id_estudiante', $idEstudiante)
            ->select(['id_grupo', 'es_coordinador'])
            ->first();

        return $miembro === null ? null : (object) [
            'id_grupo' => (int) $miembro->id_grupo,
            'es_coordinador' => (bool) $miembro->es_coordinador,
        ];
    }

    public function exigirCoordinador(ActorInvestigacion $actor, int $idGrupo): void
    {
        $grupo = DB::table('investigacion_formativa.grupo')
            ->where('id_grupo', $idGrupo)
            ->select(['id_grupo', 'id_seccion'])
            ->first();

        if ($grupo === null) {
            throw new AccesoDenegado('El grupo solicitado no existe.');
        }

        $idEscuela = $this->datos->escuelaDeSeccion((int) $grupo->id_seccion);

        if ($idEscuela === null) {
            throw new AccesoDenegado('La sección del grupo no tiene una escuela configurada.');
        }

        $idEstudiante = $this->idEstudianteEnEscuela($actor, $idEscuela);

        if ($idEstudiante === null) {
            throw new AccesoDenegado('No tiene una identidad de estudiante activa en la escuela del grupo.');
        }

        $miembro = $this->miembroDeGrupo($idGrupo, $idEstudiante);

        if ($miembro === null || ! $miembro->es_coordinador) {
            throw new AccesoDenegado('Solo el coordinador del grupo puede realizar esta acción.');
        }

        if (! $this->participanteActivo((int) $grupo->id_seccion, $idEstudiante)) {
            throw new AccesoDenegado('El coordinador no figura como participante activo de la sección.');
        }
    }

    private function miembroDeAlgunGrupo(ActorInvestigacion $actor, int $idSeccion, int $idEscuela): ?int
    {
        $idEstudiante = $this->idEstudianteEnEscuela($actor, $idEscuela);

        if ($idEstudiante === null) {
            return null;
        }

        $idGrupo = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->join('investigacion_formativa.grupo as g', 'g.id_grupo', '=', 'ge.id_grupo')
            ->where('g.id_seccion', $idSeccion)
            ->where('ge.id_estudiante', $idEstudiante)
            ->value('ge.id_grupo');

        return $idGrupo === null ? null : (int) $idGrupo;
    }
}
