<?php

namespace Tests\Support\GestionHorarios\Engine;

use DomainException;

/** Identidad e integridad verificables de un resultado ficticio; no autentica su origen. */
final class TrazabilidadEjecucionPrueba
{
    public function crear(string $solicitud, string $intento, int $generacion, array $preparada, array $resultado, int $workerPid, int $lanzadorPid): array
    {
        $entrada = $preparada['entrada'] ?? [];
        $huella = hash('sha256', json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($solicitud === '' || $intento === '' || $generacion < 1 || $workerPid < 1 || $lanzadorPid < 1 || ! is_string($preparada['huella_sha256'] ?? null) || ! hash_equals($huella, $preparada['huella_sha256']) || ! is_string($preparada['huella_academica_sha256'] ?? null) || ($entrada['contrato'] ?? null) !== 'HORARIOS_MOTOR_V1' || ! is_int($entrada['tiempo_limite'] ?? null) || ($resultado['tiempo_limite_s'] ?? null) !== $entrada['tiempo_limite']) {
            throw new DomainException('La identidad o los parámetros de la ejecución son incompatibles.');
        }

        return ['solicitud' => $solicitud, 'intento' => $intento, 'generacion' => $generacion, 'huella_entrada' => $huella, 'huella_academica' => $preparada['huella_academica_sha256'], 'contrato' => $entrada['contrato'], 'motor' => $resultado['version_motor'] ?? null, 'presupuesto' => $entrada['tiempo_limite'], 'estado_solver' => $resultado['estado'] ?? null, 'worker_pid' => $workerPid, 'lanzador_pid' => $lanzadorPid, 'resultado_sha256' => $this->hashResultado($resultado)];
    }

    public function coincide(array $evidencia, string $solicitud, string $intento, int $generacion, array $preparada, array $resultado, int $workerPid, int $lanzadorPid): bool
    {
        return hash_equals($this->hashResultado($this->crear($solicitud, $intento, $generacion, $preparada, $resultado, $workerPid, $lanzadorPid)), $this->hashResultado($evidencia));
    }

    public function hashResultado(array $resultado): string
    {
        return hash('sha256', json_encode($this->canonizar($resultado), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function canonizar(array $valor): array
    {
        if (! array_is_list($valor)) {
            ksort($valor, SORT_STRING);
        }
        foreach ($valor as $clave => $elemento) {
            if (is_array($elemento)) {
                $valor[$clave] = $this->canonizar($elemento);
            }
        }

        return $valor;
    }
}
