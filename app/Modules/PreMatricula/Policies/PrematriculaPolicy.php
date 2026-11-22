<?php

namespace App\Modules\PreMatricula\Policies;

use App\Models\User;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Models\Core\Estudiante;

/**
 * Autorización del módulo por rol de Spatie.
 * Alumno: su propia prematrícula. Dirección y Secretaría: gestión del proceso.
 */
final class PrematriculaPolicy
{
    public const ROL_ALUMNO = 'alumno';

    public const ROLES_COORDINACION = ['director_escuela', 'secretaria'];

    public function usarComoAlumno(User $user): bool
    {
        return $user->hasRole(self::ROL_ALUMNO);
    }

    public function coordinar(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_COORDINACION);
    }

    public function verSolicitud(User $user, SolicitudPrematricula $solicitud, ?Estudiante $propio): bool
    {
        return $this->coordinar($user)
            || ($this->usarComoAlumno($user) && $propio !== null && (int) $solicitud->id_estudiante === (int) $propio->id_estudiante);
    }
}
