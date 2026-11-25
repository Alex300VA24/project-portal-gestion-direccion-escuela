<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Modules\GestionHorarios\Contracts\RepositorioDemanda;
use Illuminate\Database\ConnectionInterface;
use LogicException;

final class RepositorioDemandaPostgres implements RepositorioDemanda
{
    private ?int $periodoBloqueado = null;

    public function __construct(private readonly ConnectionInterface $db) {}

    public function conBloqueo(int $periodo, callable $operacion): mixed
    {
        if ($this->db->getDriverName() !== 'pgsql') {
            throw new LogicException('El repositorio requiere PostgreSQL.');
        }

        if ($this->periodoBloqueado !== null) {
            throw new LogicException('El repositorio ya tiene un periodo bloqueado.');
        }

        return $this->db->transaction(function () use ($periodo, $operacion) {
            $this->db->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['gestion_horarios.demanda:'.$periodo]);
            $this->periodoBloqueado = $periodo;
            try {
                return $operacion();
            } finally {
                $this->periodoBloqueado = null;
            }
        }, 3);
    }

    public function ultima(int $periodo): ?array
    {
        $fila = $this->db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $periodo)->orderByDesc('version')->first(['version', 'huella']);

        return $fila ? ['version' => (int) $fila->version, 'hash' => $fila->huella] : null;
    }

    public function disponible(): bool
    {
        $schema = $this->db->getSchemaBuilder();

        return $schema->hasTable('gestion_horarios.demanda_instantanea') && $schema->hasTable('gestion_horarios.demanda_curso');
    }

    public function vigente(int $periodo): ?object
    {
        if (! $this->disponible()) {
            return null;
        }


        return $this->db->table('gestion_horarios.demanda_instantanea')
            ->where('id_periodo', $periodo)->orderByDesc('version')
            ->first(['id_demanda', 'version', 'recibida_en', 'requiere_revision']);
    }

    public function guardar(InstantaneaDemanda $instantanea): void
    {
        if ($this->db->transactionLevel() < 1 || $this->periodoBloqueado !== $instantanea->periodo) {
            throw new LogicException('La demanda debe guardarse bajo bloqueo transaccional.');
        }
        $requiereRevision = $this->db->table('gestion_horarios.horario')->where('id_periodo', $instantanea->periodo)->exists();
        $id = $this->db->table('gestion_horarios.demanda_instantanea')->insertGetId([
            'id_periodo' => $instantanea->periodo,
            'version' => $instantanea->version,
            'huella' => $instantanea->hash(),
            'requiere_revision' => $requiereRevision,
        ], 'id_demanda');
        foreach ($instantanea->cursos as $curso) {
            $this->db->table('gestion_horarios.demanda_curso')->insert(['id_demanda' => $id, ...$curso]);
        }
    }
}
