<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaDisponibilidadDocenteRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function declaraciones(int $periodo, int $docente): Collection
    {
        return $this->db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->where('id_docente', $docente)
            ->get(['id_declaracion', 'estado', 'tratamiento_regular', 'presentado_en']);
    }

    public function bloques(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('hora_inicio')->get(['id_bloque', 'nombre', 'hora_inicio', 'hora_fin']);
    }

    public function registros(int $periodo, int $docente): Collection
    {
        return $this->db->table('gestion_horarios.disponibilidad_docente as d')->leftJoin('gestion_horarios.bloque_horario as b', 'b.id_bloque', '=', 'd.id_bloque')
            ->where('d.id_periodo', $periodo)->where('d.id_docente', $docente)
            ->orderBy('d.id_disponibilidad')->get(['d.id_disponibilidad', 'd.id_declaracion', 'd.id_bloque', 'd.dia_semana', 'b.id_periodo as periodo_bloque', 'b.hora_inicio', 'b.hora_fin']);
    }

    public function actividades(int $periodo, int $docente): Collection
    {
        return $this->db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->where('s.id_periodo', $periodo)->where('ad.id_docente', $docente)
            ->orderBy('a.dia_semana')->orderBy('a.hora_inicio')->get(['ad.id_asignacion', 'ad.estado', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 's.codigo as seccion', 'c.codigo as curso', 'c.nombre']);
    }
}
