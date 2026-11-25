<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Shared\Models\Core\PlanEstudios;
use Illuminate\Database\Eloquent\Builder;

/** Limita el catálogo de planes; no sustituye la autorización de otras entidades. */
final class AmbitoPlanes
{
    public function __construct(private readonly ?ProveedorEscuelasAutorizadas $proveedor = null) {}

    /** @return Builder<PlanEstudios> */
    public function consultar(?User $usuario): Builder
    {
        $escuelas = $usuario ? (new AmbitoConsultaAdministrativa($this->proveedor))->escuelas($usuario) : [];

        return PlanEstudios::query()->whereIn('id_escuela', $escuelas);
    }

    public function contiene(?User $usuario, int $idPlan): bool
    {
        return $idPlan > 0 && $this->consultar($usuario)->where('id_plan', $idPlan)->exists();
    }
}
