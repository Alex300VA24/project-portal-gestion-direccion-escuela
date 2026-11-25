<?php

namespace Tests\Support\GestionHorarios\Engine;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/** Reconciliación conservadora exclusivamente para el esquema ficticio aislado. */
final class ReconciliarIntentosPrueba
{
    public function __construct(private readonly ConnectionInterface $db) {}

    private function verificar(): void
    {
        $cfg = $this->db->getConfig();
        if (! app()->environment('testing') || $this->db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('Reconciliación permitida únicamente en la base aislada.');
        }
        $real = $this->db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('Identidad PostgreSQL de pruebas no válida.');
        }
    }

    /** El lease es una autorización temporal, no prueba que el proceso siga vivo. */
    public function consultar(string $id): array
    {
        $this->verificar();
        $fila = $this->db->selectOne("select s.*, (s.lease_hasta > clock_timestamp()) as lease_vigente, j.reserved_at, j.available_at, extract(epoch from clock_timestamp())::bigint as ahora, exists(select 1 from prueba.propuesta_cpsat p where p.solicitud_sha256=s.entrada_sha256) as propuesta_existente from prueba.solicitud_cola s left join jobs j on j.id=s.job_id and j.queue='gh-27b5-prueba' where s.id=?", [$id]);
        if (! $fila) {
            return ['estado' => 'NO_ENCONTRADO'];
        }
        $lease = $fila->estado === 'EJECUTANDO' ? ($fila->lease_hasta === null ? 'LEASE_NO_DISPONIBLE' : ($fila->lease_vigente ? 'VIGENTE_SIN_PRUEBA_DE_VIDA' : 'EXPIRADO_POSIBLE_ABANDONO')) : 'NO_APLICA';
        $mensaje = $fila->job_id === null ? 'NO_IDENTIFICADO' : ($fila->reserved_at === null ? ($fila->available_at === null ? 'NO_ENCONTRADO_O_CONFIRMADO' : ((int) $fila->available_at <= (int) $fila->ahora ? 'DISPONIBLE' : 'DIFERIDO')) : ((int) $fila->reserved_at + 240 <= (int) $fila->ahora ? 'RESERVA_EXPIRADA' : 'RESERVADO'));

        return ['estado' => $fila->estado, 'intento' => $fila->intento, 'generacion' => (int) $fila->generacion, 'lease' => $lease, 'mensaje' => $mensaje, 'propuesta_existente' => (bool) $fila->propuesta_existente];
    }

    /** Invalida el derecho de finalizar; no mata procesos ni reencola. */
    public function cercarExpirado(string $id, string $intento, int $generacion): bool
    {
        $this->verificar();

        return $this->db->transaction(function () use ($id, $intento, $generacion): bool {
            $cercado = $this->db->update("update prueba.solicitud_cola set estado='REQUIERE_REVISION', generacion=generacion+1, diagnostico='LEASE_EXPIRADO_SIN_VERIFICAR_PROCESO' where id=? and estado='EJECUTANDO' and intento=? and generacion=? and lease_hasta <= clock_timestamp()", [$id, $intento, $generacion]) === 1;
            if ($cercado) {
                $this->db->table('prueba.intento_cola')->where('solicitud_id', $id)->where('id', $intento)->where('generacion', $generacion)->where('estado', 'EJECUTANDO')->update(['estado' => 'CERCADO', 'finalizado_en' => $this->db->raw('clock_timestamp()'), 'diagnostico' => 'LEASE_EXPIRADO']);
            }

            return $cercado;
        });
    }

    /** No revive un lease vencido ni un titular cercado. */
    public function renovar(string $id, string $intento, int $generacion): bool
    {
        $this->verificar();

        return $this->db->transaction(function () use ($id, $intento, $generacion): bool {
            $renovado = $this->db->update("update prueba.solicitud_cola set lease_hasta=clock_timestamp()+interval '210 seconds' where id=? and estado='EJECUTANDO' and intento=? and generacion=? and lease_hasta > clock_timestamp()", [$id, $intento, $generacion]) === 1;
            if (! $renovado) {
                return false;
            }
            if ($this->db->table('prueba.intento_cola')->where('solicitud_id', $id)->where('id', $intento)->where('generacion', $generacion)->where('estado', 'EJECUTANDO')->update(['renovado_en' => $this->db->raw('clock_timestamp()'), 'lease_hasta' => $this->db->raw("clock_timestamp()+interval '210 seconds'"), 'renovaciones' => $this->db->raw('renovaciones+1')]) !== 1) {
                throw new RuntimeException('Falta el historial del intento vigente.');
            }

            return true;
        });
    }
}
