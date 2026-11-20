<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaAmbientesRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function tipos(): Collection
    {
        return $this->db->table('core.ambiente')->whereNotNull('tipo')->distinct()->orderBy('tipo')->pluck('tipo');
    }

    public function ambientes(string $buscar, string $tipo): Collection
    {
        return $this->db->table('core.ambiente')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->where('codigo', 'ilike', '%'.$buscar.'%')->orWhere('nombre', 'ilike', '%'.$buscar.'%')))
            ->when($tipo !== '', fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('nombre')->orderBy('id_ambiente')->get(['id_ambiente', 'codigo', 'nombre', 'tipo', 'capacidad', 'pabellon', 'piso', 'estado']);
    }

    public function ambiente(int $id): ?object
    {
        return $this->db->table('core.ambiente')->where('id_ambiente', $id)->first(['id_ambiente', 'codigo', 'nombre', 'tipo', 'capacidad', 'pabellon', 'piso', 'estado']);
    }

    public function bloqueos(int $periodo, int $ambiente): Collection
    {
        return $this->db->table('gestion_horarios.ambiente_bloqueo')->where('id_periodo', $periodo)->where('id_ambiente', $ambiente)
            ->orderBy('fecha_inicio')->orderBy('hora_inicio')->get(['id_bloqueo', 'fecha_inicio', 'fecha_fin', 'dia_semana', 'hora_inicio', 'hora_fin', 'motivo']);
    }

    public function actividades(int $periodo, int $ambiente): Collection
    {
        return $this->db->table('gestion_horarios.detalle_horario as d')
            ->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('h.id_periodo', $periodo)->where('d.id_ambiente', $ambiente)
            ->orderBy('h.version')->orderBy('a.dia_semana')->orderBy('a.hora_inicio')->orderBy('d.id_detalle')
            ->get(['d.id_detalle', 'h.id_horario', 'h.version', 'h.ambito', 'h.estado as estado_horario', 'h.id_periodo as periodo_horario', 's.id_periodo as periodo_seccion', 's.codigo as seccion', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 'c.codigo as curso', 'c.nombre']);
    }

    public function bloques(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('hora_inicio')->get(['id_bloque', 'nombre', 'hora_inicio', 'hora_fin']);
    }

    public function restricciones(int $ambiente): Collection
    {
        return $this->db->table('gestion_horarios.detalle_restriccion as d')->join('gestion_horarios.restriccion as r', 'r.id_restriccion', '=', 'd.id_restriccion')
            ->where('d.ambito', 'AMBIENTE')->where('d.id_ambiente', $ambiente)->where('r.activa', true)
            ->orderBy('d.prioridad')->get(['r.nombre', 'r.severidad']);
    }
}
