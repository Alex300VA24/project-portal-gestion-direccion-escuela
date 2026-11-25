<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

/** Lectura parcial autorizada; todavía no autoriza ni despacha generación. */
final class PreparacionInstitucional
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ?ProveedorEscuelasAutorizadas $escuelas = null,
        private readonly ?ProveedorAlcanceRecursosAcademicos $recursos = null,
    ) {}

    public function preparar(User $usuario, int $escuela, int $periodo, int $plan): array
    {
        $this->autorizar($usuario, $escuela);
        if ($periodo < 1 || $plan < 1) {
            throw new \DomainException('Periodo o plan inválido.');
        }

        $academico = (new FuentesAcademicasAutorizadas($this->db, $this->escuelas))->consultar($usuario, $periodo, $plan);
        if ((int) $academico['escuela_id'] !== $escuela || (int) $academico['plan_id'] !== $plan || (int) $academico['periodo']['id_periodo'] !== $periodo) {
            throw new AuthorizationException;
        }
        $alcance = (new AmbitoRecursosCompartidos($this->escuelas, $this->recursos))->consultar($usuario, $escuela, $periodo);
        $recursos = $alcance === null ? null : (new ConsultaRecursosAcademicosAcotados($this->db, $this->escuelas, $this->recursos))->consultar($usuario, $escuela, $periodo, $plan);

        $this->autorizar($usuario, $escuela);
        $actual = (new AmbitoRecursosCompartidos($this->escuelas, $this->recursos))->consultar($usuario, $escuela, $periodo);
        if (! $this->mismoAlcance($alcance, $actual)) {
            throw new AuthorizationException;
        }

        return [
            'contrato' => 'PREPARACION_INSTITUCIONAL_PARCIAL_V1',
            'escuela_id' => $escuela, 'periodo_id' => $periodo, 'plan_id' => $plan,
            'ejecutable' => false, 'entrada' => null, 'fuentes_academicas' => $academico, 'recursos' => $recursos,
            'diagnosticos' => array_merge($academico['diagnosticos'], [
                ['codigo' => 'CAPTURA_COMPLETA_ACOTADA_NO_DISPONIBLE'],
                ['codigo' => 'DISPONIBILIDAD_Y_OCUPACION_NO_AUTORIZADAS'],
                ['codigo' => 'COMPLEMENTOS_INSTITUCIONALES_NO_ACREDITADOS'],
                ['codigo' => 'VIGENCIA_HASTA_PERSISTENCIA_NO_ACREDITADA'],
            ], $alcance === null ? [['codigo' => 'ALCANCE_RECURSOS_NO_DISPONIBLE']] : []),
        ];
    }

    private function autorizar(User $usuario, int $escuela): void
    {
        if ($escuela < 1 || ! (new AutorizacionGestionAcademica)->permite($usuario)
            || ! in_array($escuela, (new AmbitoConsultaAdministrativa($this->escuelas))->escuelas($usuario), true)) {
            throw new AuthorizationException;
        }
    }

    private function mismoAlcance(?array $anterior, ?array $actual): bool
    {
        if ($anterior === null || $actual === null) {
            return $anterior === $actual;
        }
        foreach (['docentes', 'ambientes'] as $tipo) {
            sort($anterior[$tipo]);
            sort($actual[$tipo]);
        }

        return $anterior === $actual;
    }
}
