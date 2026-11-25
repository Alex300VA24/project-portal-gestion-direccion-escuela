<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaPeriodosRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function listar(string $buscar, string $estado): Collection
    {
        return $this->db->table('core.periodo_academico')
            ->when($buscar !== '', fn ($q) => $q->where('codigo', 'ilike', '%'.$buscar.'%'))
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha_inicio')->get(['id_periodo', 'codigo', 'anio', 'tipo', 'fecha_inicio', 'fecha_fin', 'numero_semanas', 'estado']);
    }

    public function detalle(int $periodo): ?object
    {
        return $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->first(['id_periodo', 'codigo', 'anio', 'tipo', 'fecha_inicio', 'fecha_fin', 'numero_semanas', 'estado']);
    }

    public function bloques(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('hora_inicio')->get(['nombre', 'hora_inicio', 'hora_fin']);
    }

    public function bloqueos(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.ambiente_bloqueo as b')->join('core.ambiente as a', 'a.id_ambiente', '=', 'b.id_ambiente')
            ->where('b.id_periodo', $periodo)->orderBy('b.fecha_inicio')->get(['a.nombre', 'b.fecha_inicio', 'b.fecha_fin', 'b.dia_semana', 'b.hora_inicio', 'b.hora_fin', 'b.motivo']);
    }

    public function restricciones(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.detalle_restriccion as d')->join('gestion_horarios.restriccion as r', 'r.id_restriccion', '=', 'd.id_restriccion')
            ->where('r.activa', true)->where(fn ($q) => $q->where('d.ambito', 'GLOBAL')->orWhere(fn ($q) => $q->where('d.ambito', 'PERIODO')->where('d.id_periodo', $periodo)))
            ->orderBy('d.prioridad')->get(['r.nombre', 'r.severidad', 'd.ambito']);
    }
}
