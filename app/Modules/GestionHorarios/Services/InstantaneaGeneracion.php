<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Repositories\InstantaneaGeneracionRepository;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use LogicException;

final class InstantaneaGeneracion
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function construir(int $periodo): array
    {
        return $this->capturar($periodo, null);
    }

    public function construirConComplementos(int $periodo, ProveedorComplementosTransaccional $proveedor): array
    {
        if ($proveedor->conexion() !== $this->db) {
            throw new LogicException('Los complementos requieren la misma conexión PostgreSQL.');
        }

        return $this->capturar($periodo, $proveedor);
    }

    private function capturar(int $periodo, ?ProveedorComplementosTransaccional $proveedor): array
    {
        if ($this->db->getDriverName() !== 'pgsql' || $this->db->transactionLevel() !== 0) {
            throw new LogicException('Se requiere una conexión PostgreSQL sin transacción previa.');
        }
        if ($periodo <= 0) {
            throw new DomainException('Periodo inválido.');
        }

        return $this->db->transaction(function () use ($periodo, $proveedor) {
            $this->db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            $datos = (new InstantaneaGeneracionRepository($this->db))->leer($periodo);
            if ($datos['periodo'] === null) {
                throw new DomainException('Periodo institucional inexistente.');
            }
            $datos['poblacion_academica'] = (new PoblacionAcademica)->analizar($periodo, $datos['secciones'], $datos['demanda'], null, $datos['ofertas'], $datos['oferta_seccion'] ?? []);
            $datos['calendario_academico'] = (new CalendarioAcademico)->evaluar($datos['periodo'], $datos['bloques'], $datos['actividades'], null, $datos['bloqueos']);
            $datos = self::ordenarClaves($datos);
            $json = json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $instantanea = ['contrato' => 'INSTANTANEA_INSTITUCIONAL_V1', 'huella_sha256' => hash('sha256', $json), 'datos' => $datos];
            if ($proveedor === null) {
                return $instantanea;
            }

            return ['instantanea' => $instantanea, 'complementos' => $proveedor->obtener((string) $periodo, $instantanea['huella_sha256'])];
        });
    }

    private static function ordenarClaves(array $valor): array
    {
        if (! array_is_list($valor)) {
            ksort($valor, SORT_STRING);
        }
        foreach ($valor as $clave => $elemento) {
            if (is_array($elemento)) {
                $valor[$clave] = self::ordenarClaves($elemento);
            }
        }

        return $valor;
    }
}
