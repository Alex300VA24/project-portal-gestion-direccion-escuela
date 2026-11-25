<?php

namespace App\Modules\GestionHorarios\Contracts;

use Illuminate\Database\ConnectionInterface;

/** Proveedor de la misma conexión PostgreSQL, sin garantías sobre fuentes externas. */
interface ProveedorComplementosTransaccional extends ProveedorComplementosAcademicos
{
    public function conexion(): ConnectionInterface;
}
