<?php

namespace App\Modules\GestionHorarios\Contracts;

/** Fuente versionada ligada al periodo y a la huella de la instantánea.
 *
 * Debe declarar procedencia, población por sección y actividad, particiones,
 * sesiones físicas, calendario, disponibilidad y restricciones. La ausencia
 * de cualquiera de estos contratos debe impedir la construcción ejecutable.
 * No existe un proveedor institucional registrado.
 */
interface ProveedorComplementosAcademicos
{
    public function obtener(string $periodo, string $huella): array;
}
