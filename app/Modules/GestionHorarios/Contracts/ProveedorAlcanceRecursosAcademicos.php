<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Models\User;

/**
 * Contrato por acordar con Core/Seguridad y Recursos Académicos.
 * Una lista autoriza solo la identidad del recurso en ese ámbito: no sus datos globales.
 */
interface ProveedorAlcanceRecursosAcademicos
{
    /**
     * @return array{escuela_id: int, periodo_id: int, docentes: list<int>, ambientes: list<int>}
     */
    public function recursosVisibles(User $usuario, int $escuela, int $periodo): array;
}
