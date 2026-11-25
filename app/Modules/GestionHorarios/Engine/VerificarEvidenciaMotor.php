<?php

namespace App\Modules\GestionHorarios\Engine;

use DomainException;

/** Verifica versiones y huellas del paquete local; no acredita vigencia institucional. */
final class VerificarEvidenciaMotor
{
    public function validar(array $entrada, string $huella, array $resultado): void
    {
        $calculada = hash('sha256', json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (! hash_equals($huella, $calculada) || ($entrada['contrato'] ?? null) !== 'HORARIOS_MOTOR_V1' || ($resultado['contrato'] ?? null) !== 'HORARIOS_RESULTADO_V1' || ($resultado['version_adaptador'] ?? null) !== 'HORARIOS_MOTOR_V1' || ($resultado['version_motor'] ?? null) !== 'SOFT_VARIANTS_V1') {
            throw new DomainException('Evidencia de contrato o entrada incompatible.');
        }
        $raiz = __DIR__.'/python';
        $puente = hash_file('sha256', $raiz.'/scripts/motor_aplicacion.py');
        if (! is_string($resultado['sha256_puente'] ?? null) || ! hash_equals($puente, $resultado['sha256_puente']) || ! is_array($resultado['codigo_sha256'] ?? null)) {
            throw new DomainException('Evidencia del puente inválida.');
        }
        $esperados = [];
        foreach (['cpsat_hard', 'cpsat_soft'] as $directorio) {
            foreach (glob($raiz.'/'.$directorio.'/*.py') ?: [] as $archivo) {
                $esperados[$directorio.'/'.basename($archivo)] = hash_file('sha256', $archivo);
            }
        }
        ksort($esperados);
        $recibidos = [];
        foreach ($resultado['codigo_sha256'] as $ruta => $hash) {
            $normalizada = str_replace('\\', '/', $ruta);
            if (isset($recibidos[$normalizada])) {
                throw new DomainException('Evidencia de código duplicada.');
            }
            $recibidos[$normalizada] = $hash;
        }
        ksort($recibidos);
        if (! $esperados || $recibidos !== $esperados || ! is_string($resultado['versiones']['python'] ?? null) || ! is_string($resultado['versiones']['ortools'] ?? null)) {
            throw new DomainException('Evidencia de versión o código inválida.');
        }
    }
}
