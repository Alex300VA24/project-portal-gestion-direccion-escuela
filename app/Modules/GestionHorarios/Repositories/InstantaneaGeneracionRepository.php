<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;

final class InstantaneaGeneracionRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    private function filas($consulta): array
    {
        return $consulta->get()->map(fn ($fila) => (array) $fila)->all();
    }

    public function leer(int $periodo): array
    {
        $db = $this->db;
        $p = $db->table('core.periodo_academico')->where('id_periodo', $periodo)->first(['id_periodo', 'codigo', 'fecha_inicio', 'fecha_fin', 'estado']);
        $ofertas = $db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->orderBy('id_oferta')->select('id_oferta', 'id_periodo', 'id_curso_plan', 'tipo_oferta', 'docentes_minimos', 'estado');
        $secciones = $db->table('core.seccion')->where('id_periodo', $periodo)->orderBy('id_seccion')->select('id_seccion', 'id_periodo', 'id_curso_plan', 'codigo', 'capacidad', 'id_docente_responsable', 'estado');
        $cursosIds = $db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->select('id_curso_plan')->union($db->table('core.seccion')->where('id_periodo', $periodo)->select('id_curso_plan'));
        $actividadesIds = $db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->select('a.id_actividad');
        $docentesIds = $db->table('gestion_horarios.asignacion_docente')->whereIn('id_actividad', $actividadesIds)->select('id_docente')
            ->union($db->table('gestion_horarios.disponibilidad_docente')->where('id_periodo', $periodo)->select('id_docente'))
            ->union($db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->select('id_docente'))
            ->union($db->table('core.seccion')->where('id_periodo', $periodo)->whereNotNull('id_docente_responsable')->select('id_docente_responsable'));
        $demanda = $db->table('gestion_horarios.demanda_instantanea')->where('id_periodo', $periodo)->orderByDesc('version')->first(['id_demanda', 'version', 'huella', 'recibida_en']);
        if ($demanda) {
            $cursosIds->union($db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda->id_demanda)->select('id_curso_plan'));
        }

        $demandaFilas = $demanda ? $db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda->id_demanda)->orderBy('id_curso_plan')->get(['id_curso_plan','total','manana','tarde']) : collect();
        (new \App\Modules\GestionHorarios\Demand\AjustesDemanda($db))->filas($periodo, $demanda ? (int) $demanda->id_demanda : null, $demandaFilas);

        return [
            'periodo' => $p ? (array) $p : null,
            'cursos' => $this->filas($db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->leftJoin('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')->whereIn('cp.id_curso_plan', $cursosIds)->orderBy('cp.id_curso_plan')->select('cp.id_curso_plan', 'cp.id_curso', 'cp.id_plan', 'pe.id_escuela', 'cp.ciclo', 'c.codigo', 'c.nombre', 'c.horas_teoria', 'c.horas_practica', 'c.horas_laboratorio')),
            'ofertas' => $this->filas($ofertas),
            'secciones' => $this->filas($secciones),
            'oferta_seccion' => $db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion')
                ? $this->filas($db->table('gestion_horarios.oferta_seccion as os')->leftJoin('core.seccion as s', 's.id_seccion', '=', 'os.id_seccion')->leftJoin('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')->where(fn ($q) => $q->where('s.id_periodo', $periodo)->orWhere('o.id_periodo', $periodo)->orWhere(fn ($q) => $q->whereNull('s.id_seccion')->whereNull('o.id_oferta')))->orderBy('os.id_oferta_seccion')->select('os.id_oferta_seccion', 'os.id_oferta', 'os.id_seccion', 'os.estado')) : null,
            'actividades' => $this->filas($db->table('gestion_horarios.actividad_academica as a')->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')->where('s.id_periodo', $periodo)->orderBy('a.id_actividad')->select('a.id_actividad', 'a.id_seccion', 'a.tipo_actividad', 'a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 'a.minutos_sesion')),
            'asignaciones' => $this->filas($db->table('gestion_horarios.asignacion_docente')->whereIn('id_actividad', $actividadesIds)->orderBy('id_asignacion')->select('id_asignacion', 'id_actividad', 'id_docente', 'estado', 'id_apertura')),
            'docentes' => $this->filas($db->table('core.docente')->whereIn('id_docente', $docentesIds)->orderBy('id_docente')->select('id_docente', 'estado', 'horas_maximas')),
            'declaraciones' => $this->filas($db->table('gestion_horarios.declaracion_disponibilidad')->where('id_periodo', $periodo)->orderBy('id_declaracion')->select('id_declaracion', 'id_periodo', 'id_docente', 'estado', 'tratamiento_regular')),
            'disponibilidades' => $this->filas($db->table('gestion_horarios.disponibilidad_docente')->where('id_periodo', $periodo)->orderBy('id_disponibilidad')->select('id_disponibilidad', 'id_periodo', 'id_docente', 'id_declaracion', 'dia_semana', 'id_bloque')),
            'bloques' => $this->filas($db->table('gestion_horarios.bloque_horario')->where('id_periodo', $periodo)->orderBy('id_bloque')->select('id_bloque', 'id_periodo', 'hora_inicio', 'hora_fin')),
            'ambientes' => $this->filas($db->table('core.ambiente')->orderBy('id_ambiente')->select('id_ambiente', 'codigo', 'nombre', 'tipo', 'capacidad', 'estado')),
            'bloqueos' => $this->filas($db->table('gestion_horarios.ambiente_bloqueo')->where('id_periodo', $periodo)->orderBy('id_bloqueo')->select('id_bloqueo', 'id_periodo', 'id_ambiente', 'fecha_inicio', 'fecha_fin', 'dia_semana', 'hora_inicio', 'hora_fin')),
            'restricciones' => $this->filas($db->table('gestion_horarios.restriccion as r')->leftJoin('gestion_horarios.detalle_restriccion as d', 'd.id_restriccion', '=', 'r.id_restriccion')->where(fn ($q) => $q->whereNull('d.id_detalle_restriccion')->orWhereIn('d.ambito', ['GLOBAL', 'DOCENTE', 'AMBIENTE'])->orWhere(fn ($q) => $q->where('d.ambito', 'PERIODO')->where('d.id_periodo', $periodo)))->orderBy('r.id_restriccion')->orderBy('d.id_detalle_restriccion')->select('r.id_restriccion', 'r.codigo', 'r.tipo', 'r.severidad', 'r.activa', 'd.id_detalle_restriccion', 'd.ambito', 'd.id_periodo', 'd.id_docente', 'd.id_ambiente', 'd.prioridad')),
            'demanda' => $demanda ? ['version' => $demanda->version, 'huella' => $demanda->huella, 'recibida_en' => $demanda->recibida_en, 'cursos' => $demandaFilas->map(fn ($fila) => (array) $fila)->all()] : null,
            'horarios' => $this->filas($db->table('gestion_horarios.horario')->where('id_periodo', $periodo)->orderBy('id_horario')->select('id_horario', 'id_periodo', 'estado', 'version', 'id_demanda')),
        ];
    }
}
