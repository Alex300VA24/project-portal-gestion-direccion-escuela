<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;

final class PoblacionAcademicaRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function secciones(int $periodo): array
    {
        return $this->db->table('core.seccion')->where('id_periodo', $periodo)->orderBy('id_seccion')->get(['id_seccion', 'id_periodo', 'id_curso_plan', 'capacidad'])->map(fn ($fila) => (array) $fila)->all();
    }
}
