<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Repositories\ConsultaRecursosAcotadosRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

/** Proyección aislada de identidades de ambientes y asignaciones del plan. Sin ruta institucional. */
final class ConsultaRecursosAcademicosAcotados
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ?ProveedorEscuelasAutorizadas $escuelas = null,
        private readonly ?ProveedorAlcanceRecursosAcademicos $recursos = null,
    ) {}

    public function consultar(User $usuario, int $escuela, int $periodo, int $plan): array
    {
        if ($escuela <= 0 || $periodo <= 0 || $plan <= 0) {
            throw new AuthorizationException;
        }
        $alcance = (new AmbitoRecursosCompartidos($this->escuelas, $this->recursos))->consultar($usuario, $escuela, $periodo);
        if ($alcance === null) {
            throw new AuthorizationException;
        }

        $repo = new ConsultaRecursosAcotadosRepository($this->db);
        if (! $repo->planDeEscuela($escuela, $plan) || ! $repo->periodoVinculado($plan, $periodo)) {
            throw new AuthorizationException;
        }

        $resultado = [
            'contrato' => 'RECURSOS_ACOTADOS_27C13',
            'escuela_id' => $escuela,
            'periodo_id' => $periodo,
            'plan_id' => $plan,
            'asignaciones' => $repo->asignaciones($escuela, $plan, $periodo, $alcance['docentes']),
            'ambientes' => $repo->ambientes($alcance['ambientes']),
            'incluye_disponibilidad_global' => false,
            'incluye_ocupacion_global' => false,
            'ejecutable' => false,
        ];

        // La autorización puede haber cambiado durante las consultas. No devolver
        // una proyección obtenida con un alcance que ya no coincide.
        $alcanceActual = (new AmbitoRecursosCompartidos($this->escuelas, $this->recursos))->consultar($usuario, $escuela, $periodo);
        if ($alcanceActual === null || ! $this->mismoAlcance($alcance, $alcanceActual)
            || ! $repo->planDeEscuela($escuela, $plan) || ! $repo->periodoVinculado($plan, $periodo)) {
            throw new AuthorizationException;
        }

        return $resultado;
    }

    private function mismoAlcance(array $anterior, array $actual): bool
    {
        foreach (['docentes', 'ambientes'] as $tipo) {
            sort($anterior[$tipo]);
            sort($actual[$tipo]);
        }

        return $anterior === $actual;
    }
}
