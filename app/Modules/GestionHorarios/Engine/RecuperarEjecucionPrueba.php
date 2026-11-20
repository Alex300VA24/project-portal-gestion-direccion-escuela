<?php

namespace App\Modules\GestionHorarios\Engine;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

/** Reencolado explícito para una base y un proveedor de pruebas aislados. */
final class RecuperarEjecucionPrueba
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ProveedorComplementosTransaccional $proveedor) {}

    public function recuperar(string $id, ShouldQueue $trabajo, callable $terminacionVerificada, int $maxIntentos = 2): string
    {
        $reconciliador = new ReconciliarIntentosPrueba($this->db);
        $estado = $reconciliador->consultar($id);
        if ($this->proveedor->conexion() !== $this->db || $this->db->transactionLevel() !== 0 || ! property_exists($trabajo, 'id') || $trabajo->id !== $id || $maxIntentos < 1 || $maxIntentos > 3 || config('queue.connections.database.driver') !== 'database' || config('queue.connections.database.connection') !== $this->db->getName() || (int) config('queue.connections.database.retry_after') < 240) {
            throw new RuntimeException('Cola ficticia o política no autorizada.');
        }
        if (($estado['estado'] ?? null) === 'FINALIZADO' && ($estado['propuesta_existente'] ?? false)) {
            return 'PROPUESTA_YA_EXISTENTE';
        }
        if (($estado['estado'] ?? null) !== 'REQUIERE_REVISION') {
            return 'ESTADO_NO_RECUPERABLE';
        }
        $fila = $this->db->table('prueba.solicitud_cola')->where('id', $id)->first();
        $ultimo = $this->db->table('prueba.intento_cola')->where('solicitud_id', $id)->where('id', $fila->intento)->first();
        // El verificador del entorno aislado debe acreditar proceso y descendientes incluso si no hubo salida normal.
        if (! $ultimo || $ultimo->estado !== 'CERCADO' || ! $terminacionVerificada($ultimo)) {
            return 'PROCESO_NO_VERIFICADO';
        }
        $decision = (new PoliticaReintentoPrueba)->evaluar($ultimo->solver);
        if ($decision !== 'REQUIERE_TERMINACION_VERIFICADA') {
            return $decision;
        }
        $preparada = json_decode($fila->preparada, true, 512, JSON_THROW_ON_ERROR);
        $periodo = $preparada['entrada']['periodo'] ?? null;
        if (! is_string($periodo) || ! ctype_digit($periodo) || (int) $periodo < 1 || (int) $periodo > 2147483647) {
            return 'PREPARACION_INVALIDA';
        }
        $lock = $this->db->selectOne('select pg_try_advisory_lock(?, ?) as adquirido', [ProteccionPropuestaPrueba::PROTOCOLO, (int) $periodo]);
        if (! $lock->adquirido) {
            return 'PROCESO_EN_CURSO';
        }
        try {
            $actual = (new InstantaneaGeneracion($this->db))->construir((int) $periodo);
            $nueva = (new EnsambladorAcademicoPrueba($this->db, $this->proveedor))->construir($actual, $preparada['procedencia']['version'] ?? null);
            if (! ($nueva['ejecutable'] ?? false) || ! hash_equals($fila->academica_sha256, $nueva['huella_academica_sha256'] ?? '') || ! hash_equals($fila->entrada_sha256, $nueva['huella_sha256'] ?? '')) {
                return 'PREPARACION_OBSOLETA_O_NO_VERIFICABLE';
            }

            return $this->db->transaction(function () use ($id, $trabajo, $maxIntentos, $ultimo, $fila): string {
                $actual = $this->db->table('prueba.solicitud_cola')->where('id', $id)->lockForUpdate()->first();
                if (! $actual || $actual->estado !== 'REQUIERE_REVISION' || $actual->intento !== $ultimo->id || $actual->generacion !== $fila->generacion) {
                    return 'ESTADO_NO_RECUPERABLE';
                }
                if ($this->db->table('prueba.propuesta_cpsat')->where('solicitud_sha256', $actual->entrada_sha256)->exists()) {
                    return 'PROPUESTA_YA_EXISTENTE';
                }
                if ($this->db->table('prueba.intento_cola')->where('solicitud_id', $id)->count() >= $maxIntentos) {
                    return 'LIMITE_INTENTOS';
                }
                $job = Queue::connection('database')->pushOn('gh-27b5-prueba', $trabajo);
                $this->db->table('prueba.solicitud_cola')->where('id', $id)->update(['estado' => 'EN_COLA', 'job_id' => $job, 'diagnostico' => null]);

                return 'REENCOLADA';
            });
        } finally {
            $this->db->selectOne('select pg_advisory_unlock(?, ?)', [ProteccionPropuestaPrueba::PROTOCOLO, (int) $periodo]);
        }
    }
}
