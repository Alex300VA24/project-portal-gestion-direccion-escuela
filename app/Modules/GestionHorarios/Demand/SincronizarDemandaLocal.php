<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Shared\Contracts\FuenteDemandaPrematricula;
use App\Shared\Contracts\CierreDemandaPrematricula;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

/** Sincronización del periodo completo bajo los bloqueos de ambos módulos. */
final class SincronizarDemandaLocal
{
    public function __construct(private readonly ConnectionInterface $db, private readonly FuenteDemandaPrematricula $fuente) {}

    public function ejecutar(int $periodo, bool $simular = false): array
    {
        if (config('gestion_horarios_integracion.habilitada') !== true || $this->db->getDriverName() !== 'pgsql'
            || $this->db !== DB::connection() || ! app()->bound(CierreDemandaPrematricula::class)) {
            throw new RuntimeException('Sincronización institucional no habilitada.');
        }
        if ($periodo < 1 || $this->db->transactionLevel() !== 0) {
            throw new RuntimeException('Periodo o transacción inválidos.');
        }
        $repo = new RepositorioDemandaPostgres($this->db);
        if (! $repo->disponible()) {
            throw new RuntimeException('Faltan las migraciones de demanda.');
        }

        return $this->db->transaction(function () use ($periodo, $simular, $repo) {
            // Serializar lectura de origen y versionado con las demás escrituras de demanda.
            $this->db->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['gestion_horarios.demanda:'.$periodo]);
            if (! $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->exists()) {
                throw new RuntimeException('Periodo inexistente.');
            }
            return app(CierreDemandaPrematricula::class)->conBloqueo(function () use ($periodo, $simular, $repo) {
                $cursos = $this->fuente->cursos($periodo);
                $decision = self::decidir($periodo, $cursos, $repo->ultima($periodo));
                $captura = $decision['captura'];
                $estado = $decision['igual'] ? 'sin_cambios' : ($simular ? 'vista_previa' : (new ProcesadorInstitucional($this->db))->procesar($captura));

                return [
                    'periodo' => $periodo,
                    'version_local' => $captura->version,
                    'estado' => $estado,
                    'cursos_con_demanda' => count(array_filter($captura->cursos, fn ($c) => $c['total'] > 0)),
                    'solicitudes' => array_sum(array_column($captura->cursos, 'total')),
                ];
            });
        }, 3);
    }

    /** Versionado idempotente de la captura institucional. */
    public static function decidir(int $periodo, array $cursos, ?array $ultima): array
    {
        $version = $ultima['version'] ?? 1;
        $captura = InstantaneaDemanda::desdeArray(compact('periodo', 'version', 'cursos'));
        $igual = $ultima !== null && hash_equals($ultima['hash'], $captura->hash());
        if ($ultima !== null && ! $igual) {
            ++$version;
            $captura = InstantaneaDemanda::desdeArray(compact('periodo', 'version', 'cursos'));
        }

        return compact('captura', 'igual');
    }
}
