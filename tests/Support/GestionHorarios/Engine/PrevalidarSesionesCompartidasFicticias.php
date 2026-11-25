<?php

namespace Tests\Support\GestionHorarios\Engine;

/** Diagnóstico aislado; su salida no es una solicitud ejecutable para CP-SAT. */
final class PrevalidarSesionesCompartidasFicticias
{
    public function comprobar(array $contexto, array $sesiones): array
    {
        $errores = [];
        $datos = [];
        $ids = [];
        $diagnostico = static function (string $codigo, string $id = '') use (&$errores): void {
            $errores[] = ['codigo' => $codigo, 'entidad' => $id];
        };
        if (($contexto['origen'] ?? null) !== 'PRUEBA_FICTICIA' || ! is_string($contexto['periodo'] ?? null) || $contexto['periodo'] === '' || ! is_string($contexto['escuela_id'] ?? null) || $contexto['escuela_id'] === '' || ! is_array($contexto['planes'] ?? null) || ! array_is_list($contexto['planes']) || ! $contexto['planes']) {
            return ['estado' => 'INPUT_INVALID', 'diagnosticos' => [['codigo' => 'CONTEXTO_INVALIDO', 'entidad' => '']], 'sesiones' => [], 'motor_ejecutable' => false];
        }
        if (count(array_filter($contexto['planes'], fn ($p) => is_string($p) && $p !== '')) !== count($contexto['planes']) || count(array_unique($contexto['planes'])) !== count($contexto['planes'])) {
            return ['estado' => 'INPUT_INVALID', 'diagnosticos' => [['codigo' => 'PLANES_INVALIDOS', 'entidad' => '']], 'sesiones' => [], 'motor_ejecutable' => false];
        }
        foreach (['secciones', 'actividades', 'docentes', 'ambientes'] as $clave) {
            if (! is_array($contexto[$clave] ?? null) || ! array_is_list($contexto[$clave]) || count(array_filter($contexto[$clave], fn ($fila) => is_array($fila) && is_string($fila['id'] ?? null) && $fila['id'] !== '')) !== count($contexto[$clave]) || count(array_column($contexto[$clave], null, 'id')) !== count($contexto[$clave])) {
                return ['estado' => 'INPUT_INVALID', 'diagnosticos' => [['codigo' => 'CATALOGO_INVALIDO', 'entidad' => $clave]], 'sesiones' => [], 'motor_ejecutable' => false];
            }
        }
        $secciones = array_column($contexto['secciones'], null, 'id');
        $actividades = array_column($contexto['actividades'], null, 'id');
        $docentes = array_column($contexto['docentes'], null, 'id');
        $ambientes = array_column($contexto['ambientes'], null, 'id');
        if (! array_is_list($sesiones) || ! $sesiones) {
            $diagnostico('SESIONES_INVALIDAS');
        }
        foreach ($sesiones as $sesion) {
            if (! is_array($sesion)) {
                $diagnostico('SESION_INVALIDA');

                continue;
            }
            $id = $sesion['id'] ?? null;
            if (! is_string($id) || $id === '' || isset($ids[$id])) {
                $diagnostico('ID_SESION_INVALIDO_O_DUPLICADO', is_string($id) ? $id : '');

                continue;
            }
            $ids[$id] = true;
            $participantes = $sesion['participantes'] ?? null;
            $docente = $sesion['docente_id'] ?? null;
            $ambiente = $sesion['ambiente_id'] ?? null;
            $tipo = $sesion['tipo'] ?? null;
            $duracion = $sesion['duracion_minutos'] ?? null;
            $aid = $sesion['actividad_id'] ?? null;
            if (! is_string($aid) || ! isset($actividades[$aid]) || ! is_string($docente) || ! isset($docentes[$docente]) || ! is_string($ambiente) || ! isset($ambientes[$ambiente]) || ! in_array($tipo, ['T', 'P', 'LAB'], true) || ! is_int($duracion) || $duracion <= 0 || $duracion % 60 !== 0 || ! is_array($participantes) || ! array_is_list($participantes) || count($participantes) < 2) {
                $diagnostico('SESION_INCOMPLETA', $id);

                continue;
            }
            $vistos = [];
            $alumnos = [];
            $planes = [];
            $cursoPlan = [];
            $ofertas = [];
            foreach ($participantes as $participante) {
                $sid = is_array($participante) ? ($participante['seccion_id'] ?? null) : null;
                $poblacion = is_array($participante) ? ($participante['estudiantes'] ?? null) : null;
                if (! is_string($sid) || ! isset($secciones[$sid]) || isset($vistos[$sid]) || ! is_array($poblacion) || ! array_is_list($poblacion) || ! $poblacion || count(array_filter($poblacion, fn ($x) => is_string($x) && $x !== '')) !== count($poblacion) || count(array_unique($poblacion)) !== count($poblacion)) {
                    $diagnostico('PARTICIPANTE_INVALIDO', $id);

                    continue;
                }
                $vistos[$sid] = true;
                $sec = $secciones[$sid];
                $plan = $sec['plan_id'] ?? null;
                $padron = $sec['estudiantes'] ?? null;
                if (($sec['periodo'] ?? null) !== $contexto['periodo'] || ($sec['escuela_id'] ?? null) !== $contexto['escuela_id'] || ! is_string($plan) || ! in_array($plan, $contexto['planes'], true) || ! is_string($sec['curso_plan_id'] ?? null) || $sec['curso_plan_id'] === '' || ! is_string($sec['oferta_id'] ?? null) || $sec['oferta_id'] === '' || ! is_array($padron) || ! array_is_list($padron) || count(array_filter($padron, fn ($x) => is_string($x) && $x !== '')) !== count($padron) || count(array_unique($padron)) !== count($padron) || array_diff($poblacion, $padron)) {
                    $diagnostico('REFERENCIA_O_POBLACION_INVALIDA', $sid);

                    continue;
                }
                $planes[$plan] = true;
                $cursoPlan[$sec['curso_plan_id']] = true;
                $ofertas[$sec['oferta_id']] = true;
                foreach ($poblacion as $estudiante) {
                    $alumnos[$estudiante] = true;
                }
            }
            if (count($vistos) < 2) {
                $diagnostico('SECCIONES_INSUFICIENTES', $id);
            }
            if (count($planes) > 1) {
                $diagnostico('IDENTIDAD_ENTRE_PLANES_NO_RESUELTA', $id);
            }
            $actividad = $actividades[$aid];
            $esperados = $actividad['secciones'] ?? null;
            if (count($cursoPlan) !== 1 || count($ofertas) !== 1 || ! is_array($esperados) || ! array_is_list($esperados) || count(array_filter($esperados, fn ($x) => is_string($x) && $x !== '')) !== count($esperados) || count(array_unique($esperados)) !== count($esperados) || count($esperados) !== count($vistos) || array_diff($esperados, array_keys($vistos)) || ($actividad['docente_id'] ?? null) !== $docente || ($actividad['tipo'] ?? null) !== $tipo || ($actividad['duracion_minutos'] ?? null) !== $duracion) {
                $diagnostico('ACTIVIDAD_O_PARTICIPANTES_INCONSISTENTES', $id);
            }
            $recurso = $ambientes[$ambiente];
            if (($recurso['activo'] ?? null) !== true || ($recurso['tipo'] ?? null) !== ($tipo === 'LAB' ? 'LAB' : 'AULA') || ! is_int($recurso['capacidad'] ?? null) || $recurso['capacidad'] < count($alumnos)) {
                $diagnostico('AMBIENTE_INCOMPATIBLE', $id);
            }
            $datos[] = ['id' => $id, 'actividad_id' => $sesion['actividad_id'], 'secciones' => array_keys($vistos), 'planes' => array_keys($planes), 'poblacion_unica' => count($alumnos), 'duracion_minutos' => $duracion, 'tipo' => $tipo, 'docente_id' => $docente, 'ambiente_id' => $ambiente];
        }

        return ['estado' => $errores ? 'INPUT_INVALID' : 'PREVALIDADA', 'diagnosticos' => $errores, 'sesiones' => $errores ? [] : $datos, 'motor_ejecutable' => false];
    }
}
