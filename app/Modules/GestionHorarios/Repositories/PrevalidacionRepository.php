<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;

final class PrevalidacionRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function periodoExiste(int $periodo): bool
    {
        return $this->db->table('core.periodo_academico')->where('id_periodo', $periodo)->exists();
    }

    public function conteos(int $periodo): array
    {
        $db = $this->db;

        return [
            'bloques' => $db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->count(),
            'bloques_invalidos' => $db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->where(fn ($q) => $q->whereNull('hora_inicio')->orWhereNull('hora_fin')->orWhereColumn('hora_inicio', '>=', 'hora_fin'))->count(),
            'secciones' => $db->table('core.seccion')->where('id_periodo', $periodo)->count(),
            'capacidad_invalida' => $db->table('core.seccion')->where('id_periodo', $periodo)->where(fn ($q) => $q->whereNull('capacidad')->orWhere('capacidad', '<=', 0))->count(),
            'seccion_sin_curso' => $db->table('core.seccion as s')->leftJoin('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')->where('s.id_periodo', $periodo)->whereNull('cp.id_curso_plan')->count(),
            'oferta_sin_curso' => $db->table('gestion_horarios.oferta_curso as o')->leftJoin('core.curso_plan as cp', 'cp.id_curso_plan', '=', 'o.id_curso_plan')->where('o.id_periodo', $periodo)->whereNull('cp.id_curso_plan')->count(),
            'actividad_invalida' => $db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->where(fn ($q) => $q->whereNull('a.hora_inicio')->orWhereNull('a.hora_fin')->orWhereColumn('a.hora_inicio', '>=', 'a.hora_fin'))->count(),
            'docente_inexistente' => $db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->leftJoin('core.docente as d', 'd.id_docente', '=', 'ad.id_docente')->where('s.id_periodo', $periodo)->whereNull('d.id_docente')->count(),
            'ambiente_inexistente' => $db->table('gestion_horarios.detalle_horario as d')->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')->leftJoin('core.ambiente as a', 'a.id_ambiente', '=', 'd.id_ambiente')->where('h.id_periodo', $periodo)->whereNotNull('d.id_ambiente')->whereNull('a.id_ambiente')->count(),
            'actividad_inexistente' => $db->table('gestion_horarios.detalle_horario as d')->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')->leftJoin('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')->where('h.id_periodo', $periodo)->whereNull('a.id_actividad')->count(),
            'periodo_inconsistente' => $db->table('gestion_horarios.detalle_horario as d')->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('h.id_periodo', $periodo)->whereColumn('s.id_periodo', '<>', 'h.id_periodo')->count(),
            'asignaciones_duplicadas' => $db->query()->fromSub($db->table('gestion_horarios.asignacion_docente as ad')->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->where('ad.estado', 'CONFIRMADA')->groupBy('ad.id_actividad', 'ad.id_docente')->havingRaw('COUNT(*) > 1')->select('ad.id_actividad', 'ad.id_docente'), 'duplicadas')->count(),
            'actividad_sin_docente' => $db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->whereNotExists(fn ($q) => $q->selectRaw('1')->from('gestion_horarios.asignacion_docente as ad')->whereColumn('ad.id_actividad', 'a.id_actividad')->whereIn('ad.estado', ['PROPUESTA', 'CONFIRMADA']))->count(),
            'aprobados_publicados' => $db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->whereIn('estado', ['APROBADO', 'PUBLICADO'])->count(),
        ];
    }
}
