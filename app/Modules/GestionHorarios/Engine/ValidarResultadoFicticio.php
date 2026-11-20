<?php

namespace App\Modules\GestionHorarios\Engine;

use DomainException;

/** Reglas verificadas para el perfil ficticio; no acredita toda la política del motor. */
final class ValidarResultadoFicticio
{
    public function validar(array $entrada, array $resultado): array
    {
        $estado = $resultado['estado'] ?? null;
        if (($resultado['contrato'] ?? null) !== 'HORARIOS_RESULTADO_V1' || ! in_array($estado, ['OPTIMAL', 'FEASIBLE', 'INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID', 'INPUT_INVALID'], true) || ! is_array($resultado['sesiones'] ?? null)) {
            throw new DomainException('Resultado del motor inválido.');
        }
        if (! in_array($estado, ['OPTIMAL', 'FEASIBLE'], true)) {
            if ($resultado['sesiones'] !== []) {
                throw new DomainException('Resultado sin solución contiene sesiones.');
            }

            return ['estado' => $estado, 'solucion_validada' => false, 'alcance' => 'SIN_SOLUCION_VALIDADA'];
        }
        if (($entrada['contrato'] ?? null) !== 'HORARIOS_MOTOR_V1' || ! is_string($entrada['periodo'] ?? null) || $entrada['periodo'] === '' || ($entrada['configuracion']['politica_conflictos'] ?? null) !== 'SECCION_Y_COMPARTIDOS_V4' || ($entrada['bloqueos'] ?? null) !== [] || ($entrada['sesiones'] ?? null) !== []) {
            throw new DomainException('La solicitud no corresponde al perfil ficticio validable.');
        }
        foreach (['actividades', 'secciones', 'ambientes', 'disponibilidades'] as $coleccion) {
            if (! is_array($entrada[$coleccion] ?? null) || ! array_is_list($entrada[$coleccion]) || ! $entrada[$coleccion]) {
                throw new DomainException('La solicitud carece de datos para validar la solución.');
            }
        }
        $actividades = array_column($entrada['actividades'], null, 'id');
        $secciones = array_column($entrada['secciones'], null, 'id');
        $ambientes = array_column($entrada['ambientes'], null, 'id');
        $disponibilidades = array_column($entrada['disponibilidades'], null, 'docente_id');
        if (count($actividades) !== count($entrada['actividades']) || count($secciones) !== count($entrada['secciones']) || count($ambientes) !== count($entrada['ambientes']) || count($disponibilidades) !== count($entrada['disponibilidades'])) {
            throw new DomainException('La solicitud contiene identificadores duplicados o ausentes.');
        }
        if (array_key_exists('ambito_ficticio', $entrada)) {
            $ambito = $entrada['ambito_ficticio'];
            if (! is_array($ambito) || ! is_string($ambito['escuela_id'] ?? null) || $ambito['escuela_id'] === '' || ! is_array($ambito['planes'] ?? null) || ! array_is_list($ambito['planes']) || ! $ambito['planes'] || count(array_unique($ambito['planes'], SORT_REGULAR)) !== count($ambito['planes'])) {
                throw new DomainException('El ámbito ficticio de la solicitud es inválido.');
            }
            $planesPorCurso = [];
            foreach ($secciones as $seccion) {
                if (! is_string($seccion['plan_id'] ?? null) || ! in_array($seccion['plan_id'], $ambito['planes'], true)) {
                    throw new DomainException('La sección no pertenece a un plan de la solicitud.');
                }
                $curso = (string) ($seccion['curso_id'] ?? '');
                if ($curso === '' || (isset($planesPorCurso[$curso]) && $planesPorCurso[$curso] !== $seccion['plan_id'])) {
                    throw new DomainException('La identidad de curso entre planes no está resuelta.');
                }
                $planesPorCurso[$curso] = $seccion['plan_id'];
            }
        }
        $cal = $entrada['configuracion']['calendario'];
        if (array_key_exists('particiones_academicas', $entrada)) {
            $particiones = $entrada['particiones_academicas'];
            if (! is_array($particiones) || ($entrada['configuracion']['politica_laboratorios'] ?? null) !== 'PARTICION_EXPLICITA_V1') {
                throw new DomainException('Particiones académicas inválidas.');
            }
            $idsParticion = [];
            foreach ($particiones as $sid => $particion) {
                $sec = $secciones[(string) $sid] ?? null;
                if (! $sec || ! is_array($particion) || ! is_string($particion['id'] ?? null) || $particion['id'] === '' || isset($idsParticion[$particion['id']]) || ! is_array($particion['grupos'] ?? null) || count($particion['grupos']) < 2 || ($sec['cantidad_grupos_laboratorio'] ?? null) !== count($particion['grupos'])) {
                    throw new DomainException('Partición académica incompleta.');
                }
                $idsParticion[$particion['id']] = true;
                $vistos = [];
                foreach ($particion['grupos'] as $grupo => $alumnos) {
                    if (! is_string($grupo) || $grupo === '' || ! is_array($alumnos) || ! array_is_list($alumnos) || ! $alumnos || count(array_unique($alumnos)) !== count($alumnos)) {
                        throw new DomainException('Padrón de subgrupo inválido.');
                    }
                    foreach ($alumnos as $alumno) {
                        if (! is_string($alumno) || $alumno === '' || isset($vistos[$alumno])) {
                            throw new DomainException('Estudiante duplicado en la partición.');
                        }
                        $vistos[$alumno] = true;
                    }
                    $actividadesGrupo = array_filter($actividades, fn ($a) => $a['tipo'] === 'LAB' && $a['seccion_id'] === (string) $sid && ($a['subgrupo'] ?? null) === $grupo);
                    if (! $actividadesGrupo) {
                        throw new DomainException('Subgrupo sin actividad.');
                    }
                    foreach ($actividadesGrupo as $a) {
                        if (count($a['estudiantes']) !== count($alumnos) || array_diff($a['estudiantes'], $alumnos) || array_diff($alumnos, $a['estudiantes'])) {
                            throw new DomainException('Participantes del subgrupo inconsistentes.');
                        }
                    }
                }
                $padron = array_column($sec['estudiantes'], 'estudiante_id');
                if (count($vistos) !== count($padron) || array_diff(array_keys($vistos), $padron) || array_diff($padron, array_keys($vistos))) {
                    throw new DomainException('Cobertura de partición inválida.');
                }
                foreach ($actividades as $a) {
                    if ($a['tipo'] === 'LAB' && $a['seccion_id'] === (string) $sid && ! array_key_exists($a['subgrupo'], $particion['grupos'])) {
                        throw new DomainException('Actividad de subgrupo desconocido.');
                    }
                }
            }
        }
        foreach ($secciones as $seccion) {
            $etiqueta = $seccion['etiqueta'] ?? null;
            $repeticion = ($seccion['repeticion'] ?? false) === true;
            if ((array_key_exists('repeticion', $seccion) && ! is_bool($seccion['repeticion'])) ||
                ($repeticion && (! is_string($etiqueta) || ! str_starts_with(strtoupper(trim($etiqueta)), 'REP'))) ||
                (! $repeticion && ! in_array($etiqueta, ['A', 'B'], true))) {
                throw new DomainException('La identidad de sección regular o repetición es inválida.');
            }
        }
        $esperadas = [];
        $horas = [];
        $horasSubgrupos = [];
        $identidadesFisicas = [];
        foreach ($actividades as $a) {
            $repeticion = $a['repeticion'] ?? null;
            $seccionActividad = $secciones[$a['seccion_id'] ?? ''] ?? null;
            if (! is_bool($repeticion) || ! $seccionActividad || $repeticion !== (($seccionActividad['repeticion'] ?? false) === true) ||
                ($repeticion && (! is_int($a['ciclo_asistente'] ?? null) || $a['ciclo_asistente'] < 1 || $a['ciclo_asistente'] > 10))) {
                throw new DomainException('La actividad y su sección de repetición son incompatibles.');
            }
            if (array_key_exists('secciones_ids', $a)) {
                throw new DomainException('Participación académica no reconocida.');
            }
            $compartida = array_key_exists('sesion_fisica_id', $a);
            $participantes = $a['secciones_participantes'] ?? [$a['seccion_id'] ?? null];
            if ($compartida) {
                $fisica = $a['sesion_fisica_id'];
                $padronesParticipantes = $a['estudiantes_por_seccion'] ?? null;
                if (! is_string($fisica) || $fisica === '' || isset($identidadesFisicas[$fisica]) ||
                    ! in_array($a['tipo'] ?? null, ['T', 'P'], true) || ($a['repeticion'] ?? true) !== false ||
                    ! is_array($participantes) || ! array_is_list($participantes) || count($participantes) < 2 ||
                    count(array_unique($participantes)) !== count($participantes) ||
                    ($participantes[0] ?? null) !== ($a['seccion_id'] ?? null) ||
                    ! is_array($padronesParticipantes) || count($padronesParticipantes) !== count($participantes) ||
                    count($a['duraciones'] ?? []) !== 1 || ! is_string($a['ambiente_asignado_id'] ?? null) ||
                    ! isset($ambientes[$a['ambiente_asignado_id']])) {
                    throw new DomainException('La sesión física compartida tiene datos inválidos.');
                }
                $base = $secciones[$a['seccion_id']] ?? null;
                if (! $base || ! is_array($entrada['ambito_ficticio'] ?? null) ||
                    ! is_string($base['plan_id'] ?? null) || $base['plan_id'] === '' ||
                    ! is_string($base['curso_plan_id'] ?? null) || $base['curso_plan_id'] === '') {
                    throw new DomainException('Falta el ámbito, plan o curso-plan de la sesión física.');
                }
                $union = [];
                foreach ($participantes as $secId) {
                    $sec = $secciones[$secId] ?? null;
                    $padron = $padronesParticipantes[$secId] ?? null;
                    if (! $sec || ! is_array($padron) || ! array_is_list($padron) || ! $padron ||
                        count(array_unique($padron)) !== count($padron) ||
                        count(array_filter($padron, fn ($id) => is_string($id) && $id !== '')) !== count($padron) ||
                        array_diff($padron, array_column($sec['estudiantes'], 'estudiante_id')) ||
                        ($sec['curso_id'] ?? null) !== ($base['curso_id'] ?? null) ||
                        ($sec['periodo'] ?? null) !== ($entrada['periodo'] ?? null) ||
                        ($sec['escuela_id'] ?? null) !== ($entrada['ambito_ficticio']['escuela_id'] ?? null) ||
                        ($sec['ciclo'] ?? null) !== ($base['ciclo'] ?? null) ||
                        ($sec['plan_id'] ?? null) !== ($base['plan_id'] ?? null) ||
                        ($sec['curso_plan_id'] ?? null) !== ($base['curso_plan_id'] ?? null)) {
                        throw new DomainException('El padrón o la identidad académica participante no coinciden.');
                    }
                    foreach ($padron as $estudiante) {
                        $union[$estudiante] = true;
                    }
                }
                if (count($union) !== count($a['estudiantes'] ?? []) ||
                    array_diff(array_keys($union), $a['estudiantes']) ||
                    array_diff($a['estudiantes'], array_keys($union))) {
                    throw new DomainException('La población física debe coincidir con la unión de participantes.');
                }
                $identidadesFisicas[$fisica] = true;
            } elseif (array_key_exists('secciones_participantes', $a) || array_key_exists('estudiantes_por_seccion', $a)) {
                throw new DomainException('Falta identidad de sesión física.');
            }
            if (! isset($secciones[$a['seccion_id'] ?? ''], $disponibilidades[$a['docente_id'] ?? '']) || ! in_array($a['tipo'] ?? null, ['T', 'P', 'LAB'], true) || ! is_array($a['duraciones'] ?? null) || ! array_is_list($a['duraciones']) || ! $a['duraciones']) {
                throw new DomainException('La actividad no está completa para su validación.');
            }
            $alumnos = $a['estudiantes'] ?? null;
            $padron = $secciones[$a['seccion_id']]['estudiantes'] ?? null;
            if (! is_array($alumnos) || ! array_is_list($alumnos) || ! $alumnos || ! is_array($padron) || ! array_is_list($padron) || count(array_filter($alumnos, fn ($id) => is_string($id) && $id !== '')) !== count($alumnos) || count(array_unique($alumnos)) !== count($alumnos) || (! $compartida && array_diff($alumnos, array_column($padron, 'estudiante_id')))) {
                throw new DomainException('La población de la actividad no coincide con la sección.');
            }
            if ($a['tipo'] === 'LAB') {
                $subgrupo = $a['subgrupo'] ?? null;
                $limite = $entrada['configuracion']['capacidad_subgrupo_autorizada'] ?? null;
                if (! is_string($subgrupo) || $subgrupo === '' || ($limite !== null && (! is_int($limite) || $limite <= 0 || count($alumnos) > $limite))) {
                    throw new DomainException('El subgrupo de laboratorio o su capacidad es inválido.');
                }
            }
            foreach ($a['duraciones'] as $i => $duracion) {
                if (! is_int($duracion) || $duracion <= 0) {
                    throw new DomainException('La duración de la actividad es inválida.');
                }
                $esperadas[$a['id'].':'.($i + 1)] = [$a, $duracion];
                if ($a['tipo'] === 'LAB' && ($entrada['configuracion']['politica_laboratorios'] ?? null) === 'PARTICION_EXPLICITA_V1') {
                    $horasSubgrupos[$a['seccion_id']][$a['subgrupo']] = ($horasSubgrupos[$a['seccion_id']][$a['subgrupo']] ?? 0) + $duracion;
                } else {
                    foreach ($participantes as $secId) {
                        $horas[$secId][$a['tipo']] = ($horas[$secId][$a['tipo']] ?? 0) + $duracion;
                    }
                }
            }
        }
        foreach ($secciones as $seccion) {
            foreach (['T', 'P', 'LAB'] as $tipo) {
                if ($tipo === 'LAB' && ($entrada['configuracion']['politica_laboratorios'] ?? null) === 'PARTICION_EXPLICITA_V1') {
                    $grupos = $horasSubgrupos[$seccion['id']] ?? [];
                    if (! is_int($seccion['horas']['LAB'] ?? null) || (! $grupos && $seccion['horas']['LAB'] !== 0) || array_filter($grupos, fn ($h) => $h !== $seccion['horas']['LAB'])) {
                        throw new DomainException('Las horas de los subgrupos no coinciden con las horas de laboratorio.');
                    }

                    continue;
                }
                if (! is_int($seccion['horas'][$tipo] ?? null) || $seccion['horas'][$tipo] !== ($horas[$seccion['id']][$tipo] ?? 0)) {
                    throw new DomainException('Las horas de sección no coinciden con las actividades.');
                }
            }
        }
        $aperturas = [];
        foreach ($secciones as $seccion) {
            if (! in_array($seccion['estado'] ?? null, ['PROPUESTA', 'AUTORIZADA', 'ACTIVA'], true) || ($seccion['repeticion'] ?? false)) {
                continue;
            }
            $clave = (string) $seccion['ciclo'].'|'.(string) $seccion['curso_id'];
            $etiqueta = ($seccion['es_grupo_asesoria'] ?? false) ? 'ASESORIA' : strtoupper(trim($seccion['etiqueta']));
            $aperturas[$clave][$etiqueta] = true;
        }
        $vistas = [];
        $sesiones = [];
        foreach ($resultado['sesiones'] as $s) {
            $sid = $s['sesion_motor'] ?? '';
            if (! isset($esperadas[$sid]) || isset($vistas[$sid])) {
                throw new DomainException('Sesión desconocida o duplicada.');
            }
            [$a, $duracion] = $esperadas[$sid];
            if (isset($a['sesion_fisica_id'])) {
                if (($s['sesion_fisica_id'] ?? null) !== $a['sesion_fisica_id'] ||
                    ($s['secciones_participantes'] ?? null) !== $a['secciones_participantes']) {
                    throw new DomainException('La sesión física devuelta no coincide con sus participantes.');
                }
            } elseif (isset($s['sesion_fisica_id']) || isset($s['secciones_participantes'])) {
                throw new DomainException('El resultado contiene una sesión física no declarada.');
            }
            $r = $ambientes[$s['ambiente_id'] ?? ''] ?? null;
            $seccion = $secciones[$a['seccion_id']] ?? null;
            $dia = $s['dia'] ?? null;
            $inicio = $s['inicio'] ?? null;
            $fin = $s['fin'] ?? null;
            $diaRegular = in_array($dia, $cal['dias'], true);
            $sabadoRepeticion = $dia === 'SABADO' && $a['repeticion'] === true;
            if (($s['actividad_id'] ?? null) !== $a['id'] || ! is_int($inicio) || ! is_int($fin) || $fin - $inicio !== $duracion || (! $diaRegular && ! $sabadoRepeticion) || ! $seccion || ($dia === 'SABADO' && ! $a['repeticion'] && ! $this->sabadoRegularPermitido($cal, $seccion)) || $inicio < $cal['hora_inicial'] || $fin > $cal['hora_final'] || ($inicio < $cal['receso']['fin'] && $cal['receso']['inicio'] < $fin) || ! $r || ! ($r['activo'] ?? false) || ($r['tipo'] ?? null) !== ($a['tipo'] === 'LAB' ? 'LAB' : 'AULA') || ($r['capacidad'] ?? 0) < count($a['estudiantes'])) {
                throw new DomainException('Sesión con intervalo o recurso incompatible.');
            }
            if (isset($a['sesion_fisica_id']) && $s['ambiente_id'] !== $a['ambiente_asignado_id']) {
                throw new DomainException('La sesión física cambió de ambiente sin autorización.');
            }
            if ($dia === 'SABADO' && ! $a['repeticion']) {
                foreach (($a['secciones_participantes'] ?? [$a['seccion_id']]) as $participante) {
                    if (! isset($secciones[$participante]) || ! $this->sabadoRegularPermitido($cal, $secciones[$participante])) {
                        throw new DomainException('Sábado no autorizado para una sección participante.');
                    }
                }
            }
            $franjas = array_values(array_filter($disponibilidades[$a['docente_id']]['franjas'] ?? [], fn ($franja) => $franja['dia'] === $dia));
            usort($franjas, fn ($a, $b) => $a['inicio'] <=> $b['inicio']);
            $cubiertoHasta = $inicio;
            foreach ($franjas as $franja) {
                if ($franja['inicio'] > $cubiertoHasta) {
                    break;
                }
                $cubiertoHasta = max($cubiertoHasta, $franja['fin']);
                if ($cubiertoHasta >= $fin) {
                    break;
                }
            }
            $estadoDisponibilidad = $disponibilidades[$a['docente_id']]['estado'] ?? null;
            // Sin declaración se permite el calendario regular, incluido su sábado autorizado.
            // La excepción del sábado de repetición siempre requiere una ventana declarada.
            $disponible = $estadoDisponibilidad === 'SIN_DECLARAR' && ($disponibilidades[$a['docente_id']]['franjas'] ?? null) === [] && (! $a['repeticion'] || $dia !== 'SABADO');
            $disponible = $disponible || ($estadoDisponibilidad === 'DECLARADA' && $cubiertoHasta >= $fin);
            if (! $disponible) {
                throw new DomainException('Sesión fuera de disponibilidad.');
            }
            foreach ($sesiones as $previa) {
                if ($previa['dia'] !== $dia || $previa['inicio'] >= $fin || $inicio >= $previa['fin']) {
                    continue;
                }
                $otra = $actividades[$previa['actividad_id']];
                $otraSeccion = $secciones[$otra['seccion_id']];
                $mismaSeccion = (bool) array_intersect(
                    $otra['secciones_participantes'] ?? [$otra['seccion_id']],
                    $a['secciones_participantes'] ?? [$a['seccion_id']]
                );
                $alumnosComunes = array_intersect($otra['estudiantes'], $a['estudiantes']);
                $labsDisjuntos = $mismaSeccion && $otra['tipo'] === 'LAB' && $a['tipo'] === 'LAB'
                    && ($entrada['configuracion']['politica_laboratorios'] ?? null) === 'PARTICION_EXPLICITA_V1'
                    && is_string($otra['subgrupo'] ?? null) && is_string($a['subgrupo'] ?? null)
                    && $otra['subgrupo'] !== $a['subgrupo'] && ! $alumnosComunes
                    && (empty($otra['grupo_id']) || empty($a['grupo_id']) || $otra['grupo_id'] !== $a['grupo_id']);
                $letraOtra = ($otraSeccion['es_grupo_asesoria'] ?? false) ? 'ASESORIA' : strtoupper(trim($otraSeccion['etiqueta']));
                $letraActual = ($seccion['es_grupo_asesoria'] ?? false) ? 'ASESORIA' : strtoupper(trim($seccion['etiqueta']));
                $mismaLetra = $letraOtra === $letraActual;
                $claveOtra = (string) $otraSeccion['ciclo'].'|'.(string) $otraSeccion['curso_id'];
                $claveActual = (string) $seccion['ciclo'].'|'.(string) $seccion['curso_id'];
                $conflictoV4 = false;
                foreach (($otra['secciones_participantes'] ?? [$otra['seccion_id']]) as $otraSid) {
                    foreach (($a['secciones_participantes'] ?? [$a['seccion_id']]) as $actualSid) {
                        $otraParticipante = $secciones[$otraSid];
                        $actualParticipante = $secciones[$actualSid];
                        $letraOtraParticipante = ($otraParticipante['es_grupo_asesoria'] ?? false) ? 'ASESORIA' : strtoupper(trim($otraParticipante['etiqueta']));
                        $letraActualParticipante = ($actualParticipante['es_grupo_asesoria'] ?? false) ? 'ASESORIA' : strtoupper(trim($actualParticipante['etiqueta']));
                        $claveOtraParticipante = (string) $otraParticipante['ciclo'].'|'.(string) $otraParticipante['curso_id'];
                        $claveActualParticipante = (string) $actualParticipante['ciclo'].'|'.(string) $actualParticipante['curso_id'];
                        if (! $otra['repeticion'] && ! $a['repeticion'] && $otraParticipante['ciclo'] === $actualParticipante['ciclo'] &&
                            (string) $otraParticipante['curso_id'] !== (string) $actualParticipante['curso_id'] &&
                            ($letraOtraParticipante === $letraActualParticipante ||
                                count($aperturas[$claveOtraParticipante] ?? []) === 1 ||
                                count($aperturas[$claveActualParticipante] ?? []) === 1)) {
                            $conflictoV4 = true;
                        }
                    }
                }
                if ($previa['ambiente_id'] === $s['ambiente_id'] || $otra['docente_id'] === $a['docente_id'] || ($mismaSeccion && ! $labsDisjuntos) || $alumnosComunes || $conflictoV4) {
                    throw new DomainException('Cruce académico o de recurso.');
                }
            }
            $vistas[$sid] = true;
            $sesiones[] = $s;
        }
        if (count($vistas) !== count($esperadas)) {
            throw new DomainException('Faltan sesiones.');
        }

        return ['estado' => $estado, 'solucion_validada' => true, 'alcance' => 'PERFIL_FICTICIO_PARCIAL'];
    }

    private function sabadoRegularPermitido(array $calendario, array $seccion): bool
    {
        $ciclo = $seccion['ciclo'] ?? null;
        $etiqueta = $seccion['etiqueta'] ?? null;
        $politica = $calendario['sabados'] ?? null;
        $porCiclo = $calendario['sabados_por_ciclo'] ?? null;
        if (! is_int($ciclo) || ! is_string($etiqueta) || ! is_array($porCiclo) || ($porCiclo[$ciclo] ?? null) !== true || ! in_array($politica, [null, 'NINGUNO', 'TODOS', 'SELECCIONADOS'], true) || $politica === 'NINGUNO') {
            return false;
        }
        if ($politica !== 'SELECCIONADOS') {
            return true;
        }
        $alcances = $calendario['sabados_alcances'] ?? null;
        if (! is_array($alcances) || ! array_is_list($alcances)) {
            return false;
        }
        foreach ($alcances as $alcance) {
            if (is_array($alcance) && ($alcance['ciclo'] ?? null) === $ciclo && is_array($alcance['secciones'] ?? null) && array_is_list($alcance['secciones']) && ($alcance['secciones'] === [] || in_array($etiqueta, $alcance['secciones'], true))) {
                return true;
            }
        }

        return false;
    }
}
