<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Models\User;

/**
 * Core/Recursos debe acreditar expresamente el uso de aforos y bloqueos de los
 * ambientes del plan, no solo la visibilidad de sus identificadores.
 * No registrar implementaciones por defecto ni inferir permisos desde el rol.
 */
interface ProveedorAmbientesPlanificacion
{
    /** @return array{escuela_id: int, periodo_id: int, plan_id: int, ambientes: list<int>}|null */
    public function ambientesParaPlanificar(User $usuario, int $escuela, int $periodo, int $plan): ?array;
}
