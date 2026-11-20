<?php

namespace App\Modules\GestionHorarios\Contracts;

/** Fuente aprobada de una instantánea completa; no constituye matrícula. */
interface ProveedorDemandaAcademica
{
    /** Devuelve una captura completa y versionada para el ámbito solicitado. */
    public function obtener(int $escuela, int $periodo): array;
}
