<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Modules\GestionHorarios\Contracts\CatalogoDemanda;
use Illuminate\Database\ConnectionInterface;

final class CatalogoInstitucional implements CatalogoDemanda
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function periodoExiste(int $periodo): bool
    {
        return $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->exists();
    }

    public function cursoPlanExiste(int $cursoPlan): bool
    {
        return $this->db->table('core.curso_plan')->where('id_curso_plan', $cursoPlan)->exists();
    }
}
