<?php

namespace Tests\Support\GestionHorarios\Engine;

/** Perfil acotado de laboratorio: no acepta instantáneas institucionales ni activa generación. */
final class AdaptadorMotorFicticio
{
    public function convertir(array $fuente): array
    {
        $errores = [];
        $error = static function (string $codigo, string $entidad = '') use (&$errores): void {
            $errores[] = compact('codigo', 'entidad');
        };
        if (($fuente['contrato'] ?? null) !== 'HORARIOS_DATOS_FICTICIOS_V1' || ($fuente['origen'] ?? null) !== 'PRUEBA_FICTICIA' || empty($fuente['periodo']) || ($fuente['politica'] ?? null) !== 'SECCION_Y_COMPARTIDOS_V4' || ! is_int($fuente['tiempo_limite'] ?? null) || $fuente['tiempo_limite'] < 1 || $fuente['tiempo_limite'] > 150) {
            $error('CONTRATO_O_POLITICA_INVALIDA');
        }
        foreach (['secciones', 'actividades', 'docentes', 'ambientes', 'disponibilidades', 'bloqueos', 'sesiones', 'ofertas', 'asociaciones'] as $clave) {
            if (! isset($fuente[$clave]) || ! is_array($fuente[$clave]) || ! array_is_list($fuente[$clave])) {
                $error('COLECCION_INVALIDA', $clave);
            }
        }
        $cal = $fuente['configuracion']['calendario'] ?? null;
        if (! is_array($cal) || empty($cal['dias']) || ! is_int($cal['hora_inicial'] ?? null) || ! is_int($cal['hora_final'] ?? null) || $cal['hora_inicial'] >= $cal['hora_final'] || ! isset($cal['receso']['inicio'], $cal['receso']['fin'], $cal['sabados_por_ciclo'], $cal['bloques_hora_base']) || ! is_array($cal['sabados_por_ciclo']) || ! is_array($cal['bloques_hora_base'])) {
            $error('CALENDARIO_INCOMPLETO');
        } elseif (! is_array($cal['dias']) || array_diff($cal['dias'], ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO']) || ! is_int($cal['receso']['inicio']) || ! is_int($cal['receso']['fin']) || $cal['receso']['inicio'] >= $cal['receso']['fin']) {
            $error('CALENDARIO_INVALIDO');
        }
        if (is_array($cal) && is_array($cal['sabados_por_ciclo'] ?? null)) {
            $modo = $cal['sabados'] ?? null;
            if (! in_array($modo, [null, 'NINGUNO', 'TODOS', 'SELECCIONADOS'], true) || count(array_filter($cal['sabados_por_ciclo'], fn ($permitido) => ! is_bool($permitido))) > 0) {
                $error('SABADO_CONFIGURACION_INVALIDA');
            }
            if ($modo === 'SELECCIONADOS') {
                $alcances = $cal['sabados_alcances'] ?? null;
                if (! is_array($alcances) || ! array_is_list($alcances)) {
                    $error('SABADO_CONFIGURACION_INVALIDA');
                } else {
                    foreach ($alcances as $alcance) {
                        if (! is_array($alcance) || ! is_int($alcance['ciclo'] ?? null) || ! is_array($alcance['secciones'] ?? null) || ! array_is_list($alcance['secciones']) || count(array_filter($alcance['secciones'], fn ($etiqueta) => ! is_string($etiqueta) || $etiqueta === '')) > 0) {
                            $error('SABADO_CONFIGURACION_INVALIDA');
                        }
                    }
                }
            }
        }
        if (($fuente['bloqueos'] ?? null) !== [] || ($fuente['sesiones'] ?? null) !== []) {
            $error('BLOQUEOS_O_SESIONES_FIJAS_NO_SOPORTADOS');
        }
        if ($errores) {
            return ['compatible' => false, 'diagnosticos' => $errores, 'entrada' => null];
        }
        $indices = [];
        foreach (['secciones', 'docentes', 'ambientes', 'actividades', 'ofertas'] as $coleccion) {
            foreach ($fuente[$coleccion] as $fila) {
                $id = is_array($fila) ? ($fila['id'] ?? null) : null;
                if (! is_string($id) || $id === '' || isset($indices[$coleccion][$id])) {
                    $error('IDENTIFICADOR_INVALIDO_O_DUPLICADO', $coleccion);
                } else {
                    $indices[$coleccion][$id] = $fila;
                }
            }
        }
        foreach ($fuente['ambientes'] as $ambiente) {
            if (! in_array($ambiente['tipo'] ?? null, ['AULA', 'LAB'], true) || ! is_int($ambiente['capacidad'] ?? null) || $ambiente['capacidad'] <= 0 || ! is_bool($ambiente['activo'] ?? null) || empty($ambiente['codigo'])) {
                $error('AMBIENTE_INVALIDO', (string) ($ambiente['id'] ?? ''));
            }
        }
        $vinculos = [];
        foreach ($fuente['asociaciones'] as $vinculo) {
            $sid = $vinculo['seccion_id'] ?? '';
            $oid = $vinculo['oferta_id'] ?? '';
            $repeticion = is_string($sid) && ($indices['secciones'][$sid]['repeticion'] ?? false) === true;
            $tipoEsperado = $repeticion ? 'REPETICION' : 'REGULAR';
            if (! is_string($sid) || ! is_string($oid) || ! isset($indices['secciones'][$sid], $indices['ofertas'][$oid]) || isset($vinculos[$sid]) || ($indices['ofertas'][$oid]['periodo'] ?? null) !== $fuente['periodo'] || ($indices['ofertas'][$oid]['tipo'] ?? null) !== $tipoEsperado || (string) ($indices['ofertas'][$oid]['curso_id'] ?? '') !== (string) ($indices['secciones'][$sid]['curso_id'] ?? '')) {
                $error('ASOCIACION_INVALIDA', is_string($sid) ? $sid : '');

                continue;
            }
            $vinculos[$sid] = $oid;
        }
        if (array_key_exists('prioridad_docente', $fuente)) {
            $prioridad = $fuente['prioridad_docente'];
            $orden = is_array($prioridad) ? ($prioridad['orden'] ?? null) : null;
            if (! is_array($prioridad) || ($prioridad['politica'] ?? null) !== 'SOFT_P01_LEXICOGRAFICA_V1' || ! is_int($prioridad['version'] ?? null) || $prioridad['version'] < 0 || ! is_array($orden) || ! array_is_list($orden) || ! $orden) {
                $error('PRIORIDAD_DOCENTE_INVALIDA');
            } else {
                $docentesPrioritarios = [];
                foreach ($orden as $posicion => $fila) {
                    $id = is_array($fila) ? ($fila['docente_id'] ?? null) : null;
                    if (! is_string($id) || ! isset($indices['docentes'][$id]) || isset($docentesPrioritarios[$id]) || ($fila['posicion'] ?? null) !== $posicion + 1) {
                        $error('PRIORIDAD_DOCENTE_INVALIDA');
                    } else {
                        $docentesPrioritarios[$id] = true;
                    }
                }
            }
        }
        $ambito = $fuente['ambito_ficticio'] ?? null;
        if (array_key_exists('ambito_ficticio', $fuente)) {
            $escuela = is_array($ambito) ? ($ambito['escuela_id'] ?? null) : null;
            $planes = is_array($ambito) ? ($ambito['planes'] ?? null) : null;
            if (! is_string($escuela) || $escuela === '' || ! is_array($planes) || ! array_is_list($planes) || ! $planes || count(array_unique($planes, SORT_REGULAR)) !== count($planes) || count(array_filter($planes, fn ($id) => is_string($id) && $id !== '')) !== count($planes)) {
                $error('AMBITO_FICTICIO_INVALIDO');
            } else {
                $planesPorCurso = [];
                foreach ($fuente['secciones'] as $seccion) {
                    $sid = $seccion['id'] ?? '';
                    $plan = $seccion['plan_id'] ?? null;
                    $oferta = $indices['ofertas'][$vinculos[$sid] ?? ''] ?? null;
                    if (! is_string($plan) || ! in_array($plan, $planes, true) || ($oferta['plan_id'] ?? null) !== $plan) {
                        $error('PLAN_OFERTA_SECCION_INCONSISTENTE', (string) $sid);
                    }
                    $curso = (string) ($seccion['curso_id'] ?? '');
                    if ($curso !== '' && isset($planesPorCurso[$curso]) && $planesPorCurso[$curso] !== $plan) {
                        $error('CURSO_ENTRE_PLANES_NO_RESUELTO', (string) $sid);
                    } elseif ($curso !== '' && is_string($plan)) {
                        $planesPorCurso[$curso] = $plan;
                    }
                }
            }
        }
        foreach ($fuente['secciones'] as $seccion) {
            $sid = $seccion['id'] ?? '';
            if (array_key_exists('repeticion', $seccion) && ! is_bool($seccion['repeticion'])) {
                $error('SECCION_REPETICION_INVALIDA', (string) $sid);
            }
            $esRepeticion = ($seccion['repeticion'] ?? false) === true;
            $etiqueta = $seccion['etiqueta'] ?? null;
            $etiquetaValida = $esRepeticion
                ? is_string($etiqueta) && str_starts_with(strtoupper(trim($etiqueta)), 'REP')
                : in_array($etiqueta, ['A', 'B'], true);
            if (! isset($vinculos[$sid]) || empty($seccion['estudiantes']) || ! is_array($seccion['estudiantes']) || ! is_int($seccion['capacidad'] ?? null) || $seccion['capacidad'] <= 0 || count($seccion['estudiantes']) > $seccion['capacidad'] || ! is_int($seccion['ciclo'] ?? null) || $seccion['ciclo'] < 1 || $seccion['ciclo'] > 10 || ! $etiquetaValida || ! in_array($seccion['estado'] ?? null, ['PROPUESTA', 'AUTORIZADA', 'ACTIVA'], true) || empty($seccion['nombre']) || ! is_array($seccion['horas'] ?? null)) {
                $error('SECCION_INCOMPLETA', (string) $sid);

                continue;
            }
            foreach (['T', 'P', 'LAB'] as $tipo) {
                if (! is_int($seccion['horas'][$tipo] ?? null) || $seccion['horas'][$tipo] < 0) {
                    $error('HORAS_INCOMPLETAS', (string) $sid);
                }
            }
            $alumnos = [];
            foreach ($seccion['estudiantes'] as $estudiante) {
                $alumno = $estudiante['estudiante_id'] ?? null;
                if (! is_string($alumno) || $alumno === '' || isset($alumnos[$alumno])) {
                    $error('POBLACION_INVALIDA', (string) $sid);
                } else {
                    $alumnos[$alumno] = true;
                }
            }
        }
        // Un docente de repetición puede declarar sábado aunque esté fuera
        // del calendario regular. No extiende el calendario de sus clases regulares.
        $docentesRepeticion = [];
        foreach ($fuente['actividades'] as $actividad) {
            if (($actividad['repeticion'] ?? false) === true && is_string($actividad['docente_id'] ?? null)) {
                $docentesRepeticion[$actividad['docente_id']] = true;
            }
        }
        $disponibilidad = [];
        foreach ($fuente['disponibilidades'] as $d) {
            $id = $d['docente_id'] ?? '';
            if (! is_string($id) || ! isset($indices['docentes'][$id]) || isset($disponibilidad[$id]) || ! in_array($d['estado'] ?? null, ['DECLARADA', 'SIN_DECLARAR'], true) || ! is_array($d['franjas'] ?? null) || (($d['estado'] ?? null) === 'SIN_DECLARAR' && $d['franjas'] !== [])) {
                $error('DISPONIBILIDAD_INVALIDA', (string) $id);
            } else {
                $disponibilidad[$id] = true;
                foreach ($d['franjas'] as $franja) {
                    $dia = $franja['dia'] ?? null;
                    $diaPermitido = in_array($dia, $cal['dias'], true) || ($dia === 'SABADO' && isset($docentesRepeticion[$id]));
                    if (! $diaPermitido || ! is_int($franja['inicio'] ?? null) || ! is_int($franja['fin'] ?? null) || $franja['inicio'] >= $franja['fin'] || $franja['inicio'] < $cal['hora_inicial'] || $franja['fin'] > $cal['hora_final']) {
                        $error('FRANJA_INVALIDA', $id);
                    }
                }
            }
        }
        // Contrato optativo y exclusivo de pruebas: una actividad y un patrón
        // representan UNA sesión física, aunque participen varias secciones.
        $fisicas = [];
        $identidadesFisicas = [];
        if (array_key_exists('sesiones_fisicas_compartidas', $fuente)) {
            $solicitudes = $fuente['sesiones_fisicas_compartidas'];
            if (! is_array($solicitudes) || ! array_is_list($solicitudes)) {
                $error('SESIONES_FISICAS_INVALIDAS');
            } elseif ($solicitudes) {
                if (! is_array($ambito) || empty($ambito['escuela_id']) || empty($ambito['planes'])) {
                    $error('AMBITO_COMPARTIDO_INCOMPLETO');
                } else {
                    $contexto = ['origen' => 'PRUEBA_FICTICIA', 'periodo' => $fuente['periodo'],
                        'escuela_id' => $ambito['escuela_id'], 'planes' => $ambito['planes'],
                        'docentes' => $fuente['docentes'], 'ambientes' => $fuente['ambientes'],
                        'secciones' => [], 'actividades' => []];
                    foreach ($fuente['secciones'] as $seccion) {
                        $sid = $seccion['id'] ?? '';
                        if (($seccion['periodo'] ?? null) !== $fuente['periodo'] ||
                            ($seccion['escuela_id'] ?? null) !== $ambito['escuela_id']) {
                            $error('AMBITO_DE_SECCION_INCONSISTENTE', (string) $sid);
                        }
                        $contexto['secciones'][] = [
                            'id' => $sid, 'periodo' => $fuente['periodo'], 'escuela_id' => $ambito['escuela_id'],
                            'plan_id' => $seccion['plan_id'] ?? null,
                            'curso_plan_id' => $seccion['curso_plan_id'] ?? null,
                            'oferta_id' => $vinculos[$sid] ?? null,
                            'estudiantes' => array_column(is_array($seccion['estudiantes'] ?? null) ? $seccion['estudiantes'] : [], 'estudiante_id'),
                        ];
                    }
                    foreach ($solicitudes as $fisica) {
                        $aid = is_array($fisica) ? ($fisica['actividad_id'] ?? null) : null;
                        $a = is_string($aid) ? ($indices['actividades'][$aid] ?? null) : null;
                        $participantes = is_array($fisica) ? ($fisica['participantes'] ?? null) : null;
                        if (! $a || ! is_array($participantes) || ! array_is_list($participantes)) {
                            $error('SESION_FISICA_SIN_ACTIVIDAD', is_string($aid) ? $aid : '');

                            continue;
                        }
                        $tipo = ['TEORIA' => 'T', 'PRACTICA' => 'P', 'LABORATORIO' => 'LAB'][$a['tipo_academico'] ?? ''] ?? null;
                        $lista = array_column($participantes, 'seccion_id');
                        $duraciones = $a['duraciones_minutos'] ?? null;
                        if (isset($fisicas[$aid]) || ! is_string($fisica['id'] ?? null) || isset($identidadesFisicas[$fisica['id']]) ||
                            ! in_array($tipo, ['T', 'P'], true) || ! is_array($duraciones) || count($duraciones) !== 1 ||
                            $duraciones[0] !== ($fisica['duracion_minutos'] ?? null) ||
                            ($a['docente_id'] ?? null) !== ($fisica['docente_id'] ?? null) ||
                            ($a['seccion_id'] ?? null) !== ($lista[0] ?? null) ||
                            ($fisica['tipo'] ?? null) !== $tipo || ($a['repeticion'] ?? true) !== false) {
                            $error('SESION_FISICA_NO_COMPATIBLE', is_string($aid) ? $aid : '');

                            continue;
                        }
                        $contexto['actividades'][] = ['id' => $aid, 'secciones' => $lista, 'docente_id' => $a['docente_id'],
                            'tipo' => $tipo, 'duracion_minutos' => $duraciones[0]];
                        $fisicas[$aid] = $fisica;
                        $identidadesFisicas[$fisica['id']] = true;
                    }
                    if (! $errores) {
                        $comprobacion = (new PrevalidarSesionesCompartidasFicticias)->comprobar($contexto, $solicitudes);
                        foreach ($comprobacion['diagnosticos'] as $diagnostico) {
                            $error($diagnostico['codigo'], $diagnostico['entidad']);
                        }
                        if (! $errores) {
                            foreach ($solicitudes as $fisica) {
                                $aid = $fisica['actividad_id'];
                                $original = $indices['actividades'][$aid];
                                $union = array_values(array_unique(array_merge(...array_column($fisica['participantes'], 'estudiantes'))));
                                if (count($original['estudiantes'] ?? []) !== count($union) ||
                                    array_diff($original['estudiantes'], $union) ||
                                    ! in_array($fisica['ambiente_id'], array_column($fuente['ambientes'], 'id'), true)) {
                                    $error('POBLACION_O_RECURSO_FISICO_INCONSISTENTE', $aid);
                                }
                            }
                        }
                    }
                }
            }
        }
        $actividades = [];
        $sumas = [];
        $horasSubgrupos = [];
        $tipos = ['TEORIA' => 'T', 'PRACTICA' => 'P', 'LABORATORIO' => 'LAB'];
        foreach ($fuente['actividades'] as $actividad) {
            $id = $actividad['id'] ?? '';
            if (array_key_exists('secciones_ids', $actividad) || array_key_exists('secciones_participantes', $actividad) || array_key_exists('sesion_fisica_id', $actividad)) {
                $error('SESION_COMPARTIDA_NO_SOPORTADA', (string) $id);

                continue;
            }
            $tipo = $tipos[$actividad['tipo_academico'] ?? ''] ?? null;
            $minutos = $actividad['duraciones_minutos'] ?? null;
            $seccion = $indices['secciones'][$actividad['seccion_id'] ?? ''] ?? null;
            $esRepeticion = ($actividad['repeticion'] ?? false) === true;
            if (! $tipo || ! $seccion || ! isset($indices['docentes'][$actividad['docente_id'] ?? ''], $disponibilidad[$actividad['docente_id'] ?? '']) || ! is_array($minutos) || ! $minutos || ! is_array($actividad['estudiantes'] ?? null) || ! $actividad['estudiantes'] || ! is_bool($actividad['repeticion'] ?? null) || $esRepeticion !== (($seccion['repeticion'] ?? false) === true) || ($esRepeticion && (! is_int($actividad['ciclo_asistente'] ?? null) || $actividad['ciclo_asistente'] < 1 || $actividad['ciclo_asistente'] > 10)) || empty($actividad['fuente_asignacion'])) {
                $error('ACTIVIDAD_INCOMPLETA', (string) $id);

                continue;
            }
            $horas = [];
            foreach ($minutos as $minuto) {
                if (! is_int($minuto) || $minuto <= 0 || $minuto % 60 !== 0) {
                    $error('DURACION_INVALIDA', (string) $id);

                    continue 2;
                }
                $horas[] = intdiv($minuto, 60);
            }
            $padron = array_column($indices['secciones'][$actividad['seccion_id']]['estudiantes'], 'estudiante_id');
            if (count(array_unique($actividad['estudiantes'])) !== count($actividad['estudiantes']) ||
                (! isset($fisicas[$id]) && array_diff($actividad['estudiantes'], $padron)) ||
                ($tipo === 'LAB' && (! is_string($actividad['subgrupo'] ?? null) || $actividad['subgrupo'] === ''))) {
                $error('POBLACION_ACTIVIDAD_INVALIDA', (string) $id);

                continue;
            }
            $fisica = $fisicas[$id] ?? null;
            if ($fisica) {
                $participantes = $fisica['participantes'];
                $ids = array_column($participantes, 'seccion_id');
                $padronesPorSeccion = array_column($participantes, 'estudiantes', 'seccion_id');
                $union = array_values(array_unique(array_merge(...array_values($padronesPorSeccion))));
                // La población de la actividad es la unión; no debe ser un
                // subconjunto del padrón de la sección principal solamente.
                if (count($union) !== count($actividad['estudiantes']) || array_diff($union, $actividad['estudiantes']) ||
                    array_diff($actividad['estudiantes'], $union)) {
                    $error('POBLACION_FISICA_INVALIDA', (string) $id);

                    continue;
                }
                $actividad['sesion_fisica_id'] = $fisica['id'];
                $actividad['secciones_participantes'] = $ids;
                $actividad['estudiantes_por_seccion'] = $padronesPorSeccion;
                $actividad['ambiente_asignado_id'] = $fisica['ambiente_id'];
            }
            if ($tipo === 'LAB' && ($fuente['configuracion']['politica_laboratorios'] ?? null) === 'PARTICION_EXPLICITA_V1') {
                $grupo = $actividad['subgrupo'];
                $horasSubgrupos[$actividad['seccion_id']][$grupo] = ($horasSubgrupos[$actividad['seccion_id']][$grupo] ?? 0) + array_sum($horas);
            } else {
                foreach (($actividad['secciones_participantes'] ?? [$actividad['seccion_id']]) as $sid) {
                    $sumas[$sid][$tipo] = ($sumas[$sid][$tipo] ?? 0) + array_sum($horas);
                }
            }
            unset($actividad['tipo_academico'], $actividad['duraciones_minutos']);
            $actividad['tipo'] = $tipo;
            $actividad['duraciones'] = $horas;
            $actividad['horas'] = array_sum($horas);
            $actividades[] = $actividad;
        }
        foreach ($fuente['secciones'] as $seccion) {
            foreach (['T', 'P', 'LAB'] as $tipo) {
                if ($tipo === 'LAB' && ($fuente['configuracion']['politica_laboratorios'] ?? null) === 'PARTICION_EXPLICITA_V1') {
                    $grupos = $horasSubgrupos[$seccion['id']] ?? [];
                    if ((! $grupos && ($seccion['horas']['LAB'] ?? null) !== 0) || array_filter($grupos, fn ($h) => $h !== ($seccion['horas']['LAB'] ?? null))) {
                        $error('HORAS_DE_ACTIVIDAD_INCOMPATIBLES', (string) $seccion['id']);
                    }

                    continue;
                }
                if (($seccion['horas'][$tipo] ?? null) !== ($sumas[$seccion['id']][$tipo] ?? 0)) {
                    $error('HORAS_DE_ACTIVIDAD_INCOMPATIBLES', (string) $seccion['id']);
                }
            }
        }
        if ($errores) {
            return ['compatible' => false, 'diagnosticos' => $errores, 'entrada' => null];
        }
        $config = $fuente['configuracion'];
        $config['politica_conflictos'] = $fuente['politica'];
        $entrada = ['contrato' => 'HORARIOS_MOTOR_V1', 'periodo' => $fuente['periodo'], 'configuracion' => $config, 'secciones' => $fuente['secciones'], 'actividades' => $actividades, 'docentes' => $fuente['docentes'], 'ambientes' => $fuente['ambientes'], 'disponibilidades' => $fuente['disponibilidades'], 'bloqueos' => [], 'sesiones' => [], 'alcance' => [], 'tiempo_limite' => $fuente['tiempo_limite']];
        if ($ambito !== null) {
            $entrada['ambito_ficticio'] = $ambito;
        }
        if (array_key_exists('prioridad_docente', $fuente)) {
            $entrada['prioridad_docente'] = $fuente['prioridad_docente'];
        }

        return ['compatible' => true, 'diagnosticos' => [], 'entrada' => $entrada, 'huella_sha256' => hash('sha256', json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))];
    }
}
