<?php

namespace App\Modules\SeguimientoEgresado\Policies;

use App\Modules\SeguimientoEgresado\Support\Accesos\Identidad;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;

/**
 * Decisiones sobre recursos de un egresado concreto. El egresado solo alcanza su propio expediente;
 * el Director no consulta seguimientos individuales (solo agregados).
 * Es una clase plana para no depender del registro de Gates en archivos compartidos.
 */
final class ExpedientePolicy
{
    public function ver(Identidad $identidad, int $egresadoId): bool
    {
        return match ($identidad->perfil) {
            Perfil::Egresado => $this->esPropio($identidad, $egresadoId) && $identidad->puede('expediente.propio'),
            Perfil::Secretaria => $identidad->puede('expediente.gestionar'),
            Perfil::Director => $identidad->puede('expediente.consultar'),
        };
    }

    public function modificar(Identidad $identidad, int $egresadoId): bool
    {
        return match ($identidad->perfil) {
            Perfil::Egresado => $this->esPropio($identidad, $egresadoId) && $identidad->puede('expediente.propio'),
            Perfil::Secretaria => $identidad->puede('expediente.gestionar'),
            Perfil::Director => false,
        };
    }

    public function verSeguimiento(Identidad $identidad, int $egresadoId): bool
    {
        return match ($identidad->perfil) {
            Perfil::Egresado => $this->esPropio($identidad, $egresadoId) && $identidad->puede('seguimiento.propio'),
            Perfil::Secretaria => $identidad->puede('seguimiento.consultar'),
            Perfil::Director => false,
        };
    }

    /**
     * Solo el egresado, y únicamente en su propio nombre. Un mismo enlace puede llevar a cualquiera de sus encuestas
     * pendientes; que la invitación sea suya y esté disponible lo exige quien la lee (EncuestaService).
     */
    public function responderEncuesta(Identidad $identidad, int $egresadoId): bool
    {
        return $identidad->perfil === Perfil::Egresado
            && $this->esPropio($identidad, $egresadoId)
            && $identidad->puede('campanias.responder');
    }

    private function esPropio(Identidad $identidad, int $egresadoId): bool
    {
        return $identidad->egresadoId !== null && $identidad->egresadoId === $egresadoId;
    }
}
