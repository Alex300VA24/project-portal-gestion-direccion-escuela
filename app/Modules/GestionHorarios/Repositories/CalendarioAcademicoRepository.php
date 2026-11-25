<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;

final class CalendarioAcademicoRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function actividades(int $periodo): array
    {
        return $this->db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->where('s.id_periodo', $periodo)->orderBy('a.id_actividad')
            ->get(['a.id_actividad', 'a.id_seccion', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin'])
            ->map(fn ($fila) => (array) $fila)->all();
    }
}
