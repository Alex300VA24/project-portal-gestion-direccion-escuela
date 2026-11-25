<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use Illuminate\Database\ConnectionInterface;

/** Control adicional de ámbito; el rol por sí solo no autoriza escrituras. */
final class AmbitoEscrituraAcademica
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?ProveedorEscuelasAutorizadas $proveedor = null) {}

    public function autorizarCurso(User $usuario, int $cursoPlan): void
    {
        $escuelas = (new AmbitoConsultaAdministrativa($this->proveedor))->escuelas($usuario);
        abort_if($escuelas === [], 403);
        $permitido = $this->db->table('core.curso_plan as cp')->join('core.plan_estudios as p', 'p.id_plan', '=', 'cp.id_plan')->where('cp.id_curso_plan', $cursoPlan)->whereIn('p.id_escuela', $escuelas)->sharedLock()->exists();
        abort_unless($permitido, 403);
    }
}
