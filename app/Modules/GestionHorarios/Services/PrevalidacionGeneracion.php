<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\CalendarioAcademicoRepository;
use App\Modules\GestionHorarios\Repositories\ConsultaPeriodosRepository;
use App\Modules\GestionHorarios\Repositories\PrevalidacionRepository;
use Illuminate\Database\ConnectionInterface;

final class PrevalidacionGeneracion
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /** Diagnóstico de lectura; no valida factibilidad ni autoriza generar. */
    public function evaluar(int $periodo): array
    {
        $repo = new PrevalidacionRepository($this->db);
        if ($periodo <= 0 || ! $repo->periodoExiste($periodo)) {
            return [['nivel' => 'ERROR', 'codigo' => 'PERIODO_INEXISTENTE', 'mensaje' => 'Seleccione un periodo institucional existente.']];
        }
        $c = $repo->conteos($periodo);
        $periodos = new ConsultaPeriodosRepository($this->db);
        $calendario = (new CalendarioAcademico)->evaluar((array) $periodos->detalle($periodo), $periodos->bloques($periodo)->map(fn ($b) => (array) $b)->all(), (new CalendarioAcademicoRepository($this->db))->actividades($periodo));
        $resultados = [];
        $agregar = static function (string $nivel, string $codigo, string $mensaje) use (&$resultados): void {
            $resultados[] = compact('nivel', 'codigo', 'mensaje');
        };
        if ($c['bloques'] === 0) {
            $agregar('ERROR', 'SIN_BLOQUES', 'No hay bloques horarios registrados para el periodo.');
        }
        if (in_array('FECHAS_INVALIDAS', $calendario['diagnosticos'], true)) {
            $agregar('ERROR', 'CALENDARIO_FECHAS_INVALIDAS', 'Las fechas del periodo no son válidas o están incompletas.');
        }
        if (in_array('BLOQUES_SUPERPUESTOS', $calendario['diagnosticos'], true) || in_array('BLOQUE_DUPLICADO', $calendario['diagnosticos'], true)) {
            $agregar('ERROR', 'BLOQUES_SUPERPUESTOS', 'Hay bloques horarios duplicados o superpuestos; revise las franjas del periodo.');
        }
        $agregar('ADVERTENCIA', 'CALENDARIO_PARCIAL', 'Faltan días académicos, recesos y reglas de sábado confirmados para el periodo.');
        if ($calendario['patrones_pendientes'] > 0) {
            $agregar('ADVERTENCIA', 'PATRONES_PENDIENTES', 'Las actividades requieren patrones de sesiones confirmados antes de generar.');
        }
        foreach ([
            'bloques_invalidos' => 'Hay bloques horarios con duración inválida.',
            'capacidad_invalida' => 'Hay secciones sin capacidad válida.',
            'seccion_sin_curso' => 'Hay secciones con curso-plan inexistente.',
            'oferta_sin_curso' => 'Hay ofertas con curso-plan inexistente.',
            'actividad_invalida' => 'Hay actividades con horario incompleto o duración inválida.',
            'docente_inexistente' => 'Hay asignaciones con docente inexistente.',
            'ambiente_inexistente' => 'Hay detalles con ambiente inexistente.',
            'actividad_inexistente' => 'Hay detalles con actividad inexistente.',
            'periodo_inconsistente' => 'Hay detalles cuyo horario y sección pertenecen a periodos distintos.',
            'asignaciones_duplicadas' => 'Hay asignaciones confirmadas duplicadas para una actividad y docente.',
        ] as $clave => $mensaje) {
            if ($c[$clave] > 0) {
                $agregar('ERROR', strtoupper($clave), $mensaje.' Registros: '.$c[$clave].'.');
            }
        }
        if ($c['secciones'] === 0) {
            $agregar('ADVERTENCIA', 'SIN_SECCIONES', 'No hay secciones registradas para el periodo; revise la preparación académica.');
        }
        $conciliacion = (new ConciliacionOfertaSeccion($this->db))->evaluar($periodo);
        if (! $conciliacion['comprobable']) {
            $agregar('ADVERTENCIA', 'OFERTA_SECCION_NO_COMPROBABLE', 'No se puede comprobar la asociación de ofertas y secciones: falta la actualización del módulo.');
        } else {
            if ($conciliacion['secciones_sin_vigente']) {
                $agregar('ADVERTENCIA', 'OFERTA_SECCION_AMBIGUA', 'Hay secciones sin asociación vigente con una oferta. Registros: '.count($conciliacion['secciones_sin_vigente']).'.');
            }
            foreach ($conciliacion['asociaciones'] as $asociacion) {
                if ($asociacion['estado'] === 'VIGENTE' && $asociacion['causas']) {
                    $mensajes = ['SECCION_INEXISTENTE' => 'la sección ya no existe', 'OFERTA_INEXISTENTE' => 'la oferta ya no existe', 'PERIODO_INCOMPATIBLE' => 'los periodos no coinciden', 'CURSO_INCOMPATIBLE' => 'los cursos del plan no coinciden', 'PERIODO_NO_COMPROBABLE' => 'falta información para comprobar el periodo', 'CURSO_NO_COMPROBABLE' => 'falta información para comprobar el curso'];
                    $causas = array_map(fn ($causa) => $mensajes[$causa] ?? 'hay datos que no se pueden comprobar', $asociacion['causas']);
                    $agregar('ERROR', 'OFERTA_SECCION_INCONSISTENTE', 'La asociación '.$asociacion['id_asociacion'].' requiere revisión: '.implode('; ', $causas).'.');
                }
            }
            if ($conciliacion['ofertas_sin_secciones_validas']) {
                $agregar('INFORMACION', 'OFERTA_SIN_SECCIONES', 'Hay ofertas sin secciones asociadas válidas. Registros: '.count($conciliacion['ofertas_sin_secciones_validas']).'.');
            }
        }
        if ($c['actividad_sin_docente'] > 0) {
            $agregar('ADVERTENCIA', 'ACTIVIDAD_SIN_DOCENTE', 'Hay actividades sin docente propuesto o confirmado. Revise las asignaciones.');
        }
        if ($c['aprobados_publicados'] > 0) {
            $agregar('ADVERTENCIA', 'HORARIO_PROTEGIDO', 'Existen horarios aprobados o publicados; se necesita un procedimiento de revisión antes de generar.');
        }
        if ($c['secciones'] > 0) {
            $agregar('ADVERTENCIA', 'POBLACION_NO_CONFIRMADA', 'No se ha recibido una fuente institucional confirmada de población por sección. La demanda estimada y la capacidad no indican cuántos estudiantes pertenecen a cada sección.');
        }
        $agregar('ADVERTENCIA', 'POLITICAS_PENDIENTES', 'Las cohortes, subgrupos, calendario, disponibilidad efectiva y restricciones aún requieren validación académica.');
        $agregar('INFORMACION', 'ALCANCE', 'Esta comprobación es parcial; no acredita factibilidad ni habilita la generación.');

        return $resultados;
    }
}
