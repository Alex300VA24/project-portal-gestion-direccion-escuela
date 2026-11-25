<?php

namespace App\Modules\GestionPracticas\Policies;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Support\Acceso;
use App\Modules\GestionPracticas\Support\Actor;

final class PracticaPolicy
{
    /** Ven la práctica: el administrador, el estudiante dueño y el supervisor asignado. */
    public function view(User $user, Practica $practica): bool
    {
        return $user->can(Acceso::PRACTICAS_VALIDAR)
            || $this->esDelEstudiante($user, $practica)
            || $this->esSupervisor($user, $practica);
    }

    /** Editar datos, subir documentos y enviar a revisión: el estudiante dueño. */
    public function gestionar(User $user, Practica $practica): bool
    {
        return $this->esDelEstudiante($user, $practica);
    }

    public function validar(User $user, Practica $practica): bool
    {
        return $user->can(Acceso::PRACTICAS_VALIDAR);
    }

    /** Seguimiento, evaluación y cierre: solo el supervisor asignado. */
    public function supervisar(User $user, Practica $practica): bool
    {
        return $this->esSupervisor($user, $practica);
    }

    /** Revisar documentos: el administrador o el supervisor asignado. */
    public function revisarDocumentos(User $user, Practica $practica): bool
    {
        return $user->can(Acceso::PRACTICAS_VALIDAR) || $this->esSupervisor($user, $practica);
    }

    private function esDelEstudiante(User $user, Practica $practica): bool
    {
        return $user->can(Acceso::PRACTICAS_REGISTRAR)
            && Actor::de($user)->estudianteId === (int) $practica->id_estudiante;
    }

    private function esSupervisor(User $user, Practica $practica): bool
    {
        return $user->can(Acceso::PRACTICAS_SUPERVISAR)
            && $practica->id_docente_supervisor !== null
            && Actor::de($user)->docenteId === (int) $practica->id_docente_supervisor;
    }
}
