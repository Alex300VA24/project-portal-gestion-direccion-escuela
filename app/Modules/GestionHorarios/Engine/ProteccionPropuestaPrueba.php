<?php

namespace App\Modules\GestionHorarios\Engine;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

/** Protocolo exclusivamente ficticio. Los escritores deben tomar el mismo advisory lock. */
final class ProteccionPropuestaPrueba
{
    public const PROTOCOLO = 724161;

    public function __construct(private readonly ConnectionInterface $db, private readonly ProveedorComplementosTransaccional $proveedor) {}

    public function aceptar(array $preparada, array $resultado, ?callable $reservarIntento = null): array
    {
        $cfg = $this->db->getConfig();
        if (! app()->environment('testing') || $this->db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test' || $this->proveedor->conexion() !== $this->db || $this->db->transactionLevel() !== 0) {
            throw new RuntimeException('La protección requiere la conexión aislada de pruebas, sin transacción previa.');
        }
        $real = $this->db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('La identidad PostgreSQL de pruebas no coincide.');
        }
        $entrada = $preparada['entrada'] ?? null;
        $periodo = is_array($entrada) ? ($entrada['periodo'] ?? null) : null;
        if (($preparada['ejecutable'] ?? false) !== true || ($preparada['version_huella_academica'] ?? null) !== 'PREPARACION_ACADEMICA_PRUEBA_V2' || ! is_string($periodo) || ! ctype_digit($periodo) || (int) $periodo < 1 || (int) $periodo > 2147483647 || ! is_string($preparada['huella_sha256'] ?? null) || ! is_string($preparada['huella_academica_sha256'] ?? null) || ! is_string($preparada['procedencia']['version'] ?? null) || ! is_string($preparada['captura_finalizada_en'] ?? null)) {
            return ['estado' => 'PREPARACION_INVALIDA'];
        }
        try {
            (new VerificarEvidenciaMotor)->validar($entrada, $preparada['huella_sha256'], $resultado);
            $validacion = (new ValidarResultadoFicticio)->validar($entrada, $resultado);
            if (! $validacion['solucion_validada']) {
                return ['estado' => 'RESULTADO_SIN_SOLUCION_VALIDADA'];
            }
        } catch (DomainException|JsonException) {
            return ['estado' => 'RESULTADO_INVALIDO'];
        }

        $lock = $this->db->selectOne('select pg_try_advisory_lock(?, ?) as adquirido', [self::PROTOCOLO, (int) $periodo]);
        if (! $lock->adquirido) {
            return ['estado' => 'PROCESO_EN_CURSO'];
        }
        try {
            // El bloqueo se toma ANTES de la nueva instantánea y se conserva hasta confirmar.
            $actual = (new InstantaneaGeneracion($this->db))->construir((int) $periodo);
            $nueva = (new EnsambladorAcademicoPrueba($this->db, $this->proveedor))->construir($actual, $preparada['procedencia']['version']);
            if (! ($nueva['ejecutable'] ?? false)) {
                return ['estado' => 'FUENTE_NO_VERIFICABLE', 'diagnosticos' => $nueva['diagnosticos'] ?? []];
            }
            if ($preparada['procedencia'] !== $nueva['procedencia'] || ! hash_equals($preparada['huella_academica_sha256'], $nueva['huella_academica_sha256']) || ! hash_equals($preparada['huella_sha256'], $nueva['huella_sha256'])) {
                return ['estado' => 'CAMBIO_DETECTADO'];
            }

            return $this->db->transaction(function () use ($preparada, $entrada, $resultado, $validacion, $periodo, $reservarIntento): array {
                if ($reservarIntento !== null) {
                    $existente = $this->db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparada['huella_sha256'])->first();
                    if ($existente && ($existente->estado_solver !== $resultado['estado'] || json_decode($existente->sesiones, true, 512, JSON_THROW_ON_ERROR) != $resultado['sesiones'])) {
                        return ['estado' => 'RESULTADO_DUPLICADO_DIFERENTE'];
                    }
                }
                if ($reservarIntento !== null && ! $reservarIntento()) {
                    return ['estado' => 'INTENTO_DESACTUALIZADO'];
                }
                $id = (string) Str::uuid();
                $insertados = $this->db->table('prueba.propuesta_cpsat')->insertOrIgnore([
                    'id' => $id,
                    'solicitud_sha256' => $preparada['huella_sha256'],
                    'academica_sha256' => $preparada['huella_academica_sha256'],
                    'periodo' => (int) $periodo,
                    'escuela' => $entrada['ambito_ficticio']['escuela_id'],
                    'planes' => json_encode($entrada['ambito_ficticio']['planes'], JSON_THROW_ON_ERROR),
                    'versiones' => json_encode(['huella_academica' => $preparada['version_huella_academica'], 'fuentes' => $preparada['procedencia']], JSON_THROW_ON_ERROR),
                    'estado_solver' => $resultado['estado'],
                    'sesiones' => json_encode($resultado['sesiones'], JSON_THROW_ON_ERROR),
                    'alcance_validacion' => $validacion['alcance'],
                    'captura_finalizada_en' => $preparada['captura_finalizada_en'],
                    'validada_en' => now('UTC'),
                ]);
                if (! $insertados) {
                    return ['estado' => 'DUPLICADA', 'id' => $this->db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $preparada['huella_sha256'])->value('id')];
                }

                return ['estado' => 'ACEPTADA_PROTOCOLO_FICTICIO', 'id' => $id];
            });
        } catch (Throwable $e) {
            report($e);

            return ['estado' => 'ERROR_CONSULTA'];
        } finally {
            $this->db->selectOne('select pg_advisory_unlock(?, ?)', [self::PROTOCOLO, (int) $periodo]);
        }
    }
}
