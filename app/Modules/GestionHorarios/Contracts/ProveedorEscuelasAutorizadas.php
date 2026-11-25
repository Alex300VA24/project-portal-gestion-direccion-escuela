<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Models\User;

/** Contrato pendiente de implementación institucional; una lista vacía deniega acceso. */
interface ProveedorEscuelasAutorizadas
{
    /** @return list<int> Identificadores de escuelas con autorización vigente para el actor y su rol. */
    public function escuelasVigentes(User $usuario): array;
}
