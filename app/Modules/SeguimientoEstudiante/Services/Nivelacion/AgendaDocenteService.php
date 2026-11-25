<?php

namespace App\Modules\SeguimientoEstudiante\Services\Nivelacion;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AgendaDocenteService
{
    public function sesiones(int $persona): LengthAwarePaginator
    {
        $hoy = now('America/Lima');

        return DB::table('seguimiento_estudiante.nivelacion_sesion as sesion')
            ->join('seguimiento_estudiante.nivelacion_grupo as grupo', 'grupo.id_grupo', '=', 'sesion.id_grupo')
            ->join('core.docente as docente', 'docente.id_docente', '=', 'grupo.id_docente')
            ->join('seguimiento_estudiante.programa_nivelacion as programa', 'programa.id_programa', '=', 'grupo.id_programa')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'programa.id_periodo')
            ->where('docente.id_persona', $persona)->where('docente.estado', 'Activo')
            ->where('programa.publicado_en', '<=', $hoy->toIso8601String())
            ->whereIn(DB::raw('upper(programa.estado)'), ['PUBLICADO', 'EN_EJECUCION', 'EN EJECUCIÓN', 'EN EJECUCION'])
            ->where('sesion.fecha', '>=', $hoy->toDateString())
            ->whereColumn('sesion.fecha', '>=', 'programa.fecha_inicio')
            ->whereColumn('sesion.fecha', '<=', 'programa.fecha_fin')
            ->orderBy('sesion.fecha')->orderBy('sesion.id_sesion')
            ->paginate(25, ['programa.nombre as programa', 'periodo.codigo as periodo', 'grupo.codigo as grupo', 'sesion.fecha', 'sesion.tema']);
    }
}
