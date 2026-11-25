<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use App\Modules\GestionHorarios\Repositories\CapturaAcademicaAcotadaRepository;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/** Lectura académica parcial por plan; no construye entradas ejecutables para el motor. */
final class FuentesAcademicasAutorizadas
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?ProveedorEscuelasAutorizadas $escuelas = null) {}

    public function consultar(User $usuario, int $periodo, int $plan): array
    {
        if ($this->db->getDriverName() !== 'pgsql' || $this->db->transactionLevel() !== 0) {
            throw new LogicException('La consulta requiere PostgreSQL sin transacción previa.');
        }
        if ($periodo < 1 || $plan < 1) {
            throw new DomainException('Periodo o plan inválido.');
        }
        if (! (new RegistroPolicy)->viewAny($usuario)) {
            throw new AuthorizationException;
        }
        $autorizadas = (new AmbitoConsultaAdministrativa($this->escuelas))->escuelas($usuario);
        if ($autorizadas === []) {
            throw new AuthorizationException;
        }

        $resultado = $this->db->transaction(function () use ($periodo, $plan, $autorizadas): array {
            $this->db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');

            return (new CapturaAcademicaAcotadaRepository($this->db))->leer($periodo, $plan, $autorizadas);
        });

        if (! (new RegistroPolicy)->viewAny($usuario)
            || ! in_array((int) $resultado['escuela_id'], (new AmbitoConsultaAdministrativa($this->escuelas))->escuelas($usuario), true)) {
            throw new AuthorizationException;
        }

        $datos = array_intersect_key($resultado, array_flip(['periodo', 'escuela_id', 'plan_id', 'cursos', 'ofertas', 'secciones', 'oferta_seccion', 'actividades', 'asignaciones', 'diagnosticos']));
        $resultado['captura'] = [
            'contrato' => 'CAPTURA_ACADEMICA_ACOTADA_V1',
            'huella_sha256' => hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'fuentes' => [
                ['origen' => 'core', 'version' => null],
                ['origen' => 'gestion_horarios', 'version' => null],
            ],
            'garantia' => 'POSTGRESQL_MISMA_TRANSACCION_SOLO_PROYECCION_ACADEMICA',
        ];

        return $resultado;
    }
}
