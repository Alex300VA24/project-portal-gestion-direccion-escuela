<?php

namespace App\Modules\SeguimientoEstudiante\Services\Nivelacion;

use App\Shared\Models\Core\Estudiante;
use Illuminate\Support\Facades\DB;

final class IngresoNivelacionService
{
    public function __construct(private readonly DocumentosNivelacionService $documentos) {}

    public function consultar(int $persona, ?int $perfilSeleccionado = null): array
    {
        $estudiante = Estudiante::query()->where('id_persona', $persona)
            ->when($perfilSeleccionado !== null, fn ($consulta) => $consulta->whereKey($perfilSeleccionado))
            ->orderBy('id_estudiante')->firstOrFail(['id_estudiante']);
        $id = $estudiante->getKey();
        $ahora = now('America/Lima')->toIso8601String();

        $perfil = DB::table('seguimiento_estudiante.perfil_ingreso')
            ->where('id_estudiante', $id)
            ->first(['id_perfil', 'puntaje_ingreso', 'fecha_evaluacion']);
        $brechas = $perfil === null ? collect() : DB::table('seguimiento_estudiante.brecha_ingreso as brecha')
            ->join('seguimiento_estudiante.dimension_brecha as dimension', 'dimension.id_dimension', '=', 'brecha.id_dimension')
            ->where('brecha.id_perfil', $perfil->id_perfil)
            ->orderBy('dimension.nombre')
            ->get(['dimension.nombre', 'brecha.puntaje', 'brecha.nivel']);

        $inscripciones = DB::table('seguimiento_estudiante.nivelacion_participante as participante')
            ->join('seguimiento_estudiante.nivelacion_grupo as grupo', 'grupo.id_grupo', '=', 'participante.id_grupo')
            ->join('seguimiento_estudiante.programa_nivelacion as programa', 'programa.id_programa', '=', 'grupo.id_programa')
            ->join('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'programa.id_periodo')
            ->join('core.estudiante as estudiante', 'estudiante.id_estudiante', '=', 'participante.id_estudiante')
            ->where('participante.id_estudiante', $id)
            ->where('programa.publicado_en', '<=', $ahora)
            ->where(fn ($query) => $query->whereNull('programa.id_escuela')
                ->orWhereColumn('programa.id_escuela', 'estudiante.id_escuela'))
            ->orderByDesc('programa.fecha_inicio')
            ->orderBy('participante.id_participante')
            ->paginate(15, [
                'participante.id_participante', 'participante.estado', 'grupo.codigo as grupo',
                'programa.nombre as programa', 'periodo.codigo as periodo',
            ]);

        $participantes = $inscripciones->pluck('id_participante');
        $calificaciones = DB::table('seguimiento_estudiante.nivelacion_calificacion as calificacion')
            ->join('seguimiento_estudiante.nivelacion_participante as participante', 'participante.id_participante', '=', 'calificacion.id_participante')
            ->join('seguimiento_estudiante.nivelacion_grupo as grupo', 'grupo.id_grupo', '=', 'participante.id_grupo')
            ->join('seguimiento_estudiante.nivelacion_criterio_evaluacion as criterio', function ($join): void {
                $join->on('criterio.id_criterio', '=', 'calificacion.id_criterio')
                    ->on('criterio.id_programa', '=', 'grupo.id_programa');
            })
            ->where('participante.id_estudiante', $id)
            ->whereIn('participante.id_participante', $participantes)
            ->where('calificacion.publicada_en', '<=', $ahora)
            ->orderBy('criterio.nombre')
            ->get(['participante.id_participante', 'criterio.nombre', 'criterio.peso', 'calificacion.nota'])
            ->groupBy('id_participante');

        $sesiones = DB::table('seguimiento_estudiante.nivelacion_participante as participante')
            ->join('seguimiento_estudiante.nivelacion_sesion as sesion', 'sesion.id_grupo', '=', 'participante.id_grupo')
            ->leftJoin('seguimiento_estudiante.nivelacion_asistencia as asistencia', function ($join): void {
                $join->on('asistencia.id_sesion', '=', 'sesion.id_sesion')
                    ->on('asistencia.id_participante', '=', 'participante.id_participante');
            })
            ->where('participante.id_estudiante', $id)
            ->whereIn('participante.id_participante', $participantes)
            ->orderBy('sesion.fecha')->orderBy('sesion.id_sesion')
            ->get(['participante.id_participante', 'sesion.fecha', 'sesion.tema', 'asistencia.estado'])
            ->groupBy('id_participante');

        $materiales = $this->documentos->materiales($persona, $participantes->all())
            ->orderBy('material.titulo')->orderBy('material.id_archivo')
            ->get(['participante.id_participante', 'material.id_archivo', 'material.titulo'])
            ->groupBy('id_participante');

        return compact('perfil', 'brechas', 'inscripciones', 'calificaciones', 'sesiones', 'materiales');
    }
}
