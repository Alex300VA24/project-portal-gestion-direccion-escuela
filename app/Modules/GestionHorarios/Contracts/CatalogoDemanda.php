<?php

namespace App\Modules\GestionHorarios\Contracts;

interface CatalogoDemanda
{
    public function periodoExiste(int $periodo): bool;

    public function cursoPlanExiste(int $cursoPlan): bool;
}
