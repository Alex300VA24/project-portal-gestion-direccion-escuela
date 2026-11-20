<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Modules\GestionHorarios\Contracts\IdentidadCursoPlan;
use Illuminate\Database\ConnectionInterface;

final class IdentidadCursoPlanInstitucional implements IdentidadCursoPlan
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function coincide(int $escuela, int $plan, int $curso, int $cursoPlan): bool
    {
        return $this->db->table('core.curso_plan as cp')->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')->where('p.id_escuela', $escuela)->where('cp.id_plan', $plan)->where('cp.id_curso', $curso)->where('cp.id_curso_plan', $cursoPlan)->exists();
    }
}
