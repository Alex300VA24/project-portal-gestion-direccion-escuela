<?php

namespace App\Modules\GestionHorarios\Engine;

/** Representación diagnóstica; no es una solicitud ejecutable del motor. */
final class MapeoAcademicoParcial
{
    public function convertir(array $preparacion): array
    {
        (new ValidarEntradaPreparacion)->validar($preparacion);
        $datos = $preparacion['datos'];
        $diagnosticos = [];
        $secciones = [];
        $cursosPorId = [];
        $planes = [];
        foreach ($datos['cursos'] as $curso) {
            $id = (string) $curso['id_curso_plan'];
            $cursosPorId[$id] = $curso;
            $plan = $curso['id_plan'] ?? null;
            $escuela = $curso['id_escuela'] ?? null;
            if ($plan !== null) {
                $planes[(string) $plan] = ['id' => (string) $plan, 'escuela_id' => $escuela === null ? null : (string) $escuela];
            }
            if ($plan === null || $escuela === null) {
                $diagnosticos[] = ['entidad' => 'curso_plan', 'id' => $id, 'codigo' => 'PLAN_O_ESCUELA_NO_VERIFICADOS', 'categoria' => 'AUSENTE'];
            }
        }
        $asociaciones = $datos['oferta_seccion'] ?? [];
        $erroresAsociacion = (new AnalizarAsociacionesOfertaSeccion)->evaluar($datos['ofertas'], $datos['secciones'], $asociaciones);
        $invalidos = array_fill_keys(array_column($erroresAsociacion, 'indice'), true);
        foreach ($erroresAsociacion as $error) {
            $fila = $asociaciones[$error['indice']];
            if (($fila['estado'] ?? null) === 'VIGENTE') {
                $diagnosticos[] = ['entidad' => 'asociacion', 'id' => (string) ($fila['id_oferta_seccion'] ?? ''), 'codigo' => $error['codigo']];
            }
        }
        foreach ($datos['secciones'] as $seccion) {
            $relaciones = [];
            foreach ($asociaciones as $indice => $asociacion) {
                if (($asociacion['estado'] ?? null) === 'VIGENTE' && (string) ($asociacion['id_seccion'] ?? '') === (string) $seccion['id_seccion']) {
                    $relaciones[$indice] = $asociacion;
                }
            }
            $oferta = null;
            if (count($relaciones) === 1 && ! isset($invalidos[array_key_first($relaciones)])) {
                $idOferta = (string) reset($relaciones)['id_oferta'];
                foreach ($datos['ofertas'] as $candidata) {
                    if ((string) $candidata['id_oferta'] === $idOferta) {
                        $oferta = $candidata;
                    }
                }
            }
            $curso = $cursosPorId[(string) $seccion['id_curso_plan']] ?? [];
            $secciones[] = ['id' => (string) $seccion['id_seccion'], 'periodo_id' => (string) $seccion['id_periodo'], 'curso_plan_id' => (string) $seccion['id_curso_plan'], 'curso_id' => isset($curso['id_curso']) ? (string) $curso['id_curso'] : null, 'plan_id' => isset($curso['id_plan']) ? (string) $curso['id_plan'] : null, 'escuela_id' => isset($curso['id_escuela']) ? (string) $curso['id_escuela'] : null, 'ciclo' => $curso['ciclo'] ?? null, 'codigo' => $seccion['codigo'] ?? null, 'capacidad' => $seccion['capacidad'] ?? null, 'oferta_id' => $oferta ? (string) $oferta['id_oferta'] : null, 'tipo_oferta' => $oferta['tipo_oferta'] ?? null, 'registro_institucional' => $seccion];
            if (! $oferta) {
                $diagnosticos[] = ['entidad' => 'seccion', 'id' => (string) $seccion['id_seccion'], 'codigo' => $relaciones ? 'OFERTA_INCONSISTENTE' : 'OFERTA_NO_CONFIRMADA'];
            }
            $diagnosticos[] = ['entidad' => 'seccion', 'id' => (string) $seccion['id_seccion'], 'codigo' => 'POBLACION_NO_CONFIRMADA'];
        }
        $tipos = ['TEORIA' => 'T', 'PRACTICA' => 'P', 'LABORATORIO' => 'LAB'];
        $actividades = [];
        foreach ($datos['actividades'] as $actividad) {
            $tipo = $tipos[$actividad['tipo_actividad']] ?? null;
            if ($tipo === null) {
                $diagnosticos[] = ['entidad' => 'actividad', 'id' => (string) $actividad['id_actividad'], 'codigo' => 'TIPO_NO_MAPEADO'];
            }
            $asignaciones = array_values(array_filter($datos['asignaciones'], fn ($a) => (string) $a['id_actividad'] === (string) $actividad['id_actividad'] && ($a['estado'] ?? null) === 'CONFIRMADA'));
            $actividades[] = ['id' => (string) $actividad['id_actividad'], 'seccion_id' => (string) $actividad['id_seccion'], 'docentes_ids' => array_map(fn ($a) => (string) $a['id_docente'], $asignaciones), 'tipo' => $tipo, 'tipo_institucional' => $actividad['tipo_actividad'], 'duracion_minutos' => $actividad['duracion_minutos'], 'registro_institucional' => $actividad];
            if (! $asignaciones) {
                $diagnosticos[] = ['entidad' => 'actividad', 'id' => (string) $actividad['id_actividad'], 'codigo' => 'DOCENTE_CONFIRMADO_AUSENTE', 'categoria' => 'AUSENTE'];
            } elseif (count($asignaciones) > 1) {
                $diagnosticos[] = ['entidad' => 'actividad', 'id' => (string) $actividad['id_actividad'], 'codigo' => 'ASIGNACIONES_MULTIPLES_SIN_REGLA', 'categoria' => 'NO_SOPORTADA'];
            }
            $diagnosticos[] = ['entidad' => 'actividad', 'id' => (string) $actividad['id_actividad'], 'codigo' => 'PATRON_NO_CONFIRMADO'];
        }
        $docentes = [];
        foreach ($datos['docentes'] as $docente) {
            $id = (string) $docente['id_docente'];
            $declaraciones = array_values(array_filter($datos['declaraciones'], fn ($d) => (string) $d['id_docente'] === $id));
            $franjas = array_values(array_filter($datos['disponibilidades'], fn ($d) => (string) $d['id_docente'] === $id));
            $docentes[] = ['id' => $id, 'registro_institucional' => $docente, 'declaraciones' => $declaraciones, 'bloques_declarados' => $franjas];
            $diagnosticos[] = ['entidad' => 'docente', 'id' => $id, 'codigo' => count($declaraciones) ? (count($franjas) ? 'DISPONIBILIDAD_REGISTRADA_NO_INTERPRETADA' : 'DECLARACION_SIN_FRANJAS') : 'SIN_DECLARACION'];
        }
        $diagnosticos[] = ['entidad' => 'periodo', 'id' => (string) $datos['periodo']['id_periodo'], 'codigo' => 'CALENDARIO_Y_POLITICAS_NO_CONFIRMADOS'];

        foreach ($datos['ofertas'] as $oferta) {
            $id = (string) $oferta['id_oferta'];
            $vinculadas = array_values(array_filter($secciones, fn ($s) => $s['oferta_id'] === $id));
            if (! $vinculadas) {
                $diagnosticos[] = ['entidad' => 'oferta', 'id' => $id, 'codigo' => 'OFERTA_SIN_SECCION_VIGENTE', 'categoria' => 'AUSENTE'];
            } elseif (! array_filter($actividades, fn ($a) => in_array($a['seccion_id'], array_column($vinculadas, 'id'), true))) {
                $diagnosticos[] = ['entidad' => 'oferta', 'id' => $id, 'codigo' => 'OFERTA_SIN_ACTIVIDADES', 'categoria' => 'AUSENTE'];
            }
        }
        foreach ($datos['cursos'] as $curso) {
            $id = (string) $curso['id_curso_plan'];
            if (! array_filter($secciones, fn ($s) => $s['curso_plan_id'] === $id)) {
                $diagnosticos[] = ['entidad' => 'curso_plan', 'id' => $id, 'codigo' => 'CURSO_SIN_SECCION', 'categoria' => 'AUSENTE'];
            }
        }
        foreach ($datos['restricciones'] as $restriccion) {
            if (($restriccion['activa'] ?? false) === true) {
                $diagnosticos[] = ['entidad' => 'restriccion', 'id' => (string) $restriccion['id_restriccion'], 'codigo' => 'RESTRICCION_SIN_EQUIVALENCIA_CONFIRMADA', 'categoria' => 'NO_SOPORTADA'];
            }
        }
        if (! $datos['ambientes']) {
            $diagnosticos[] = ['entidad' => 'periodo', 'id' => (string) $datos['periodo']['id_periodo'], 'codigo' => 'AMBIENTES_AUSENTES', 'categoria' => 'AUSENTE'];
        }
        $diagnosticos[] = ['entidad' => 'periodo', 'id' => (string) $datos['periodo']['id_periodo'], 'codigo' => 'SESION_FISICA_COMPARTIDA_SIN_FUENTE', 'categoria' => 'NO_SOPORTADA'];
        $clasificacion = array_map(static function (array $diagnostico): array {
            if (! isset($diagnostico['categoria'])) {
                $diagnostico['categoria'] = match ($diagnostico['codigo']) {
                    'REFERENCIA_O_ESTADO_INVALIDO', 'PERIODO_DISTINTO', 'CURSO_PLAN_DISTINTO', 'SECCION_CON_VARIAS_VIGENTES', 'OFERTA_INCONSISTENTE' => 'INVALIDA',
                    'TIPO_NO_MAPEADO', 'CALENDARIO_Y_POLITICAS_NO_CONFIRMADOS', 'DISPONIBILIDAD_REGISTRADA_NO_INTERPRETADA' => 'NO_SOPORTADA',
                    default => 'AUSENTE',
                };
            }

            return $diagnostico;
        }, $diagnosticos);

        return ['contrato' => 'MAPEO_ACADEMICO_PARCIAL_V1', 'huella_instantanea' => $preparacion['huella_instantanea'], 'ejecutable' => false, 'periodo' => $datos['periodo'], 'planes' => array_values($planes), 'secciones' => $secciones, 'actividades' => $actividades, 'docentes' => $docentes, 'asignaciones' => $datos['asignaciones'], 'bloques' => $datos['bloques'], 'ambientes' => $datos['ambientes'], 'bloqueos' => $datos['bloqueos'], 'restricciones' => $datos['restricciones'], 'ofertas' => $datos['ofertas'], 'cursos' => $datos['cursos'], 'demanda' => $datos['demanda'], 'poblacion_academica' => $datos['poblacion_academica'] ?? null, 'calendario_academico' => $datos['calendario_academico'] ?? null, 'horarios' => $datos['horarios'], 'diagnosticos' => $diagnosticos, 'clasificacion_diagnosticos' => $clasificacion];
    }
}
