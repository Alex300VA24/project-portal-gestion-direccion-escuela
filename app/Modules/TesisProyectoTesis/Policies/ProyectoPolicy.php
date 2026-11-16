<?php

namespace App\Modules\TesisProyectoTesis\Policies;

use App\Models\User;
use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\JuradoProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\ProyectoEstudiante;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;

/**
 * Autorización por proyecto: el alumno solo ve SUS proyectos; el profesor solo
 * donde es asesor o jurado ACTIVO; Director y Secretaría según su función.
 */
final class ProyectoPolicy
{
    public function __construct(private readonly IdentidadService $identidad) {}

    public function verComoAlumno(User $user, Proyecto $proyecto): bool
    {
        $id = $this->identidad->actual($user)?->idEstudiante;

        return $id !== null && $user->hasRole('alumno') && ProyectoEstudiante::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('id_estudiante', $id)
            ->where('estado', 'Activo')
            ->exists();
    }

    public function verComoAsesor(User $user, Proyecto $proyecto): bool
    {
        $id = $this->identidad->actual($user)?->idDocente;

        return $id !== null && $user->hasRole('profesor') && AsesorProyecto::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('id_docente', $id)
            ->where('estado', 'Activo')
            ->exists();
    }

    public function verComoJurado(User $user, Proyecto $proyecto): bool
    {
        $id = $this->identidad->actual($user)?->idDocente;

        return $id !== null && $user->hasRole('profesor') && JuradoProyecto::query()
            ->where('id_proyecto', $proyecto->id_proyecto)
            ->where('id_docente', $id)
            ->where('estado', 'Activo')
            ->exists();
    }

    public function gestionarComoDirector(User $user, Proyecto $proyecto): bool
    {
        return $user->hasRole('director_escuela') && $this->identidad->actual($user) !== null;
    }

    public function gestionarComoSecretaria(User $user, Proyecto $proyecto): bool
    {
        return $user->hasRole('secretaria') && $this->identidad->actual($user) !== null;
    }

    /** Ver el expediente y sus archivos: cualquiera de los roles con relación al proyecto. */
    public function ver(User $user, Proyecto $proyecto): bool
    {
        return $this->gestionarComoDirector($user, $proyecto)
            || $this->gestionarComoSecretaria($user, $proyecto)
            || $this->verComoAlumno($user, $proyecto)
            || $this->verComoAsesor($user, $proyecto)
            || $this->verComoJurado($user, $proyecto);
    }
}
