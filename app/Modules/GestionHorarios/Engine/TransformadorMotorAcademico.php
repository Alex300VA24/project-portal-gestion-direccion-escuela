<?php

namespace App\Modules\GestionHorarios\Engine;

use DomainException;

/** Perfil regular T/P/LAB con particiones de laboratorio explícitas. */
final class TransformadorMotorAcademico
{
    public function convertir(array $preparacion, array $complementos): array
    {
        (new ValidarEntradaPreparacion)->validar($preparacion);
        $original = $preparacion['datos'];
        foreach ($original['actividades'] as &$actividadOriginal) {
            unset($actividadOriginal['duracion_minutos']);
        }
        unset($actividadOriginal);
        $huella = hash('sha256', json_encode($original, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (! hash_equals($preparacion['huella_instantanea'], $huella)) {
            throw new DomainException('Los datos de preparación no corresponden a la instantánea.');
        }
        $m = (new MapeoAcademicoParcial)->convertir($preparacion);
        $d = $preparacion['datos'];
        $campos = ['contrato', 'fuente', 'version', 'periodo', 'huella_instantanea', 'tiempo_limite', 'restricciones', 'sesiones_fisicas', 'particiones', 'poblacion', 'participantes_actividad', 'patrones', 'nombres_docentes', 'disponibilidad', 'configuracion', 'grupos_laboratorio'];
        if (array_diff(array_keys($complementos), $campos) || $d['demanda'] !== null || $d['horarios'] !== []) {
            throw new DomainException('Existen datos académicos no representados por este perfil.');
        }
        if (($complementos['contrato'] ?? null) !== 'COMPLEMENTOS_MOTOR_ACADEMICO_V1'
            || ! is_string($complementos['fuente'] ?? null) || $complementos['fuente'] === ''
            || ! is_string($complementos['version'] ?? null) || $complementos['version'] === ''
            || ($complementos['periodo'] ?? null) !== (string) $d['periodo']['id_periodo']
            || ($complementos['huella_instantanea'] ?? null) !== $preparacion['huella_instantanea']
            || ! is_int($complementos['tiempo_limite'] ?? null) || $complementos['tiempo_limite'] < 1 || $complementos['tiempo_limite'] > 150) {
            throw new DomainException('Procedencia, versión, periodo o huella de complementos incompatibles.');
        }
        if (($complementos['restricciones'] ?? null) !== ['estado' => 'SIN_RESTRICCIONES_ADICIONALES_CONFIRMADAS']
            || $d['restricciones'] !== [] || $d['bloqueos'] !== []
            || ($complementos['sesiones_fisicas'] ?? null) !== []) {
            throw new DomainException('Restricciones, bloqueos, particiones o sesiones compartidas no soportados por este perfil.');
        }
        if (! is_array($complementos['particiones'] ?? null)) {
            throw new DomainException('LABORATORIO_PARTICIONES_INVALIDAS');
        }
        foreach (['secciones', 'actividades', 'ofertas', 'cursos', 'docentes', 'ambientes'] as $campo) {
            if (! $m[$campo]) {
                throw new DomainException('Colección académica vacía: '.$campo);
            }
        }
        foreach (['poblacion' => array_column($m['secciones'], 'id'), 'participantes_actividad' => array_column($m['actividades'], 'id'), 'patrones' => array_column($m['actividades'], 'id'), 'nombres_docentes' => array_column($m['docentes'], 'id')] as $campo => $ids) {
            $claves = is_array($complementos[$campo] ?? null) ? array_map('strval', array_keys($complementos[$campo])) : [];
            sort($claves);
            sort($ids);
            if ($claves !== $ids) {
                throw new DomainException('Complementos con referencias ausentes o adicionales: '.$campo);
            }
        }
        $disponibilidad = $complementos['disponibilidad'] ?? null;
        $config = $complementos['configuracion'] ?? null;
        if (! is_array($config) || array_diff(array_keys($config), ['politica_conflictos', 'calendario', 'capacidad_subgrupo_autorizada', 'politica_laboratorios']) || ($config['politica_conflictos'] ?? null) !== 'SECCION_Y_COMPARTIDOS_V4'
            || ! is_array($config['calendario'] ?? null) || ($config['calendario']['receso'] ?? null) !== ['inicio' => 13, 'fin' => 14]
            || count($config['calendario']['bloques_hora_base'] ?? []) !== 12
            || ! is_array($disponibilidad) || ! array_is_list($disponibilidad)) {
            throw new DomainException('Calendario, disponibilidad o ambiente incompatible con el perfil.');
        }
        if (($complementos['particiones'] !== [] && ($config['politica_laboratorios'] ?? null) !== 'PARTICION_EXPLICITA_V1') || (array_key_exists('politica_laboratorios', $config) && $config['politica_laboratorios'] !== 'PARTICION_EXPLICITA_V1')) {
            throw new DomainException('LABORATORIO_POLITICA_PARTICION_NO_ACREDITADA');
        }
        $calendario = $config['calendario'];
        $base = array_map(fn ($hora) => ['inicio' => $hora, 'fin' => $hora + 1], [...range(7, 12), ...range(14, 19)]);
        if (array_diff(array_keys($calendario), ['dias', 'hora_inicial', 'hora_final', 'receso', 'sabados_por_ciclo', 'bloques_hora_base'])
            || ($calendario['hora_inicial'] ?? null) !== 7 || ($calendario['hora_final'] ?? null) !== 20
            || ($calendario['bloques_hora_base'] ?? null) !== $base || ! is_array($calendario['dias'] ?? null)
            || ! $calendario['dias'] || array_diff($calendario['dias'], ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'])
            || ! is_array($calendario['sabados_por_ciclo'] ?? null) || count(array_filter($calendario['sabados_por_ciclo'], fn ($v) => ! is_bool($v))) > 0) {
            throw new DomainException('Calendario no representable o con opciones no soportadas.');
        }
        $cursos = array_column($m['cursos'], null, 'id_curso_plan');
        $secciones = $actividades = $docentes = $ambientes = $cargas = $sumas = [];
        foreach ($m['secciones'] as $s) {
            $curso = $cursos[$s['curso_plan_id']] ?? [];
            $padron = $complementos['poblacion'][$s['id']] ?? null;
            if ($s['oferta_id'] === null || $s['tipo_oferta'] !== 'REGULAR' || ! in_array($s['codigo'], ['A', 'B'], true) || ! is_string($s['plan_id']) || ! is_string($s['escuela_id']) || $s['curso_id'] === null || ! is_int($curso['ciclo'] ?? null) || ! is_string($curso['nombre'] ?? null) || ! in_array($s['registro_institucional']['estado'] ?? null, ['PROPUESTA', 'AUTORIZADA', 'ACTIVA'], true) || ! is_int($s['capacidad']) || $s['capacidad'] < 1 || ! is_array($padron) || ! array_is_list($padron) || ! $padron || count($padron) > $s['capacidad'] || ! is_int($curso['horas_practica'] ?? null) || $curso['horas_practica'] < 0 || ! is_int($curso['horas_laboratorio'] ?? null) || $curso['horas_laboratorio'] < 0 || ! is_int($curso['horas_teoria'] ?? null) || $curso['horas_teoria'] < 0 || $curso['horas_teoria'] + $curso['horas_practica'] + $curso['horas_laboratorio'] < 1) {
                throw new DomainException('Sección o curso incompatible con el perfil T/P/LAB.');
            }
            $ids = array_column($padron, 'estudiante_id');
            if (count($ids) !== count($padron) || count(array_unique($ids)) !== count($ids) || count(array_filter($padron, fn ($p) => is_array($p) && is_string($p['estudiante_id'] ?? null) && $p['estudiante_id'] !== '' && in_array($p['preferencia'] ?? null, ['MANANA', 'TARDE', 'NINGUNA'], true))) !== count($padron)) {
                throw new DomainException('Padrón inválido.');
            }
            $secciones[$s['id']] = ['id' => $s['id'], 'periodo' => (string) $d['periodo']['id_periodo'], 'escuela_id' => $s['escuela_id'], 'plan_id' => $s['plan_id'], 'curso_plan_id' => $s['curso_plan_id'], 'curso_id' => $s['curso_plan_id'], 'curso_institucional_id' => $s['curso_id'], 'oferta_id' => $s['oferta_id'], 'nombre' => $curso['nombre'], 'ciclo' => $curso['ciclo'], 'etiqueta' => $s['codigo'], 'capacidad' => $s['capacidad'], 'estado' => $s['registro_institucional']['estado'], 'horas' => ['T' => $curso['horas_teoria'], 'P' => $curso['horas_practica'], 'LAB' => $curso['horas_laboratorio']], 'estudiantes' => $padron];
        }
        if (count(array_unique(array_column($secciones, 'escuela_id'))) !== 1 || count(array_unique(array_column($secciones, 'plan_id'))) !== 1) {
            throw new DomainException('El perfil requiere una sola escuela y un solo plan.');
        }
        $particiones = [];
        $identidadesParticion = [];
        foreach ($complementos['particiones'] as $sid => $particion) {
            $sid = (string) $sid;
            $sec = $secciones[$sid] ?? null;
            if (! $sec || ! is_array($particion) || array_diff(array_keys($particion), ['id', 'seccion_id', 'curso_plan_id', 'grupos']) || ! is_string($particion['id'] ?? null) || $particion['id'] === '' || ($particion['seccion_id'] ?? null) !== $sid || ($particion['curso_plan_id'] ?? null) !== $sec['curso_plan_id'] || ! is_array($particion['grupos'] ?? null) || ! array_is_list($particion['grupos']) || count($particion['grupos']) < 2) {
                throw new DomainException('LABORATORIO_PARTICION_REFERENCIA_INVALIDA');
            }
            if (isset($identidadesParticion[$particion['id']])) {
                throw new DomainException('LABORATORIO_PARTICION_ID_DUPLICADO');
            }
            $identidadesParticion[$particion['id']] = true;
            $grupos = $vistos = [];
            foreach ($particion['grupos'] as $grupo) {
                if (! is_array($grupo) || array_diff(array_keys($grupo), ['id', 'estudiantes']) || ! is_string($grupo['id'] ?? null) || $grupo['id'] === '' || isset($grupos[$grupo['id']]) || ! is_array($grupo['estudiantes'] ?? null) || ! array_is_list($grupo['estudiantes']) || ! $grupo['estudiantes']) {
                    throw new DomainException('LABORATORIO_GRUPO_INVALIDO');
                }
                foreach ($grupo['estudiantes'] as $alumno) {
                    if (! is_string($alumno) || $alumno === '' || isset($vistos[$alumno])) {
                        throw new DomainException('LABORATORIO_PARTICION_DUPLICADA');
                    }
                    $vistos[$alumno] = true;
                }
                $grupos[$grupo['id']] = $grupo['estudiantes'];
            }
            $esperados = array_column($sec['estudiantes'], 'estudiante_id');
            if (count($vistos) !== count($esperados) || array_diff(array_keys($vistos), $esperados) || array_diff($esperados, array_keys($vistos))) {
                throw new DomainException('LABORATORIO_PARTICION_COBERTURA_INVALIDA');
            }
            $particiones[$sid] = ['id' => $particion['id'], 'grupos' => $grupos];
            $secciones[$sid]['cantidad_grupos_laboratorio'] = count($grupos);
        }
        foreach ($m['actividades'] as $a) {
            $patron = $complementos['patrones'][$a['id']] ?? null;
            $sec = $secciones[$a['seccion_id']] ?? null;
            $asignaciones = array_values(array_filter($m['asignaciones'], fn ($v) => (string) $v['id_actividad'] === $a['id'] && ($v['estado'] ?? null) === 'CONFIRMADA'));
            if (! $sec || ! in_array($a['tipo'], ['T', 'P', 'LAB'], true) || count($a['docentes_ids']) !== 1 || count($asignaciones) !== 1 || ! is_array($patron) || ! array_is_list($patron) || count($patron) !== 1 || ! is_int($patron[0]) || $patron[0] < 60 || $patron[0] % 60 !== 0 || $patron[0] !== $a['duracion_minutos'] || (($a['registro_institucional']['minutos_sesion'] ?? $patron[0]) !== $patron[0])) {
                throw new DomainException('Actividad, asignación o patrón incompatible.');
            }
            $grupo = $a['tipo'] === 'LAB' ? ($complementos['grupos_laboratorio'][$a['id']] ?? null) : null;
            $ids = $a['tipo'] === 'LAB' && isset($particiones[$a['seccion_id']]) ? ($particiones[$a['seccion_id']]['grupos'][$grupo ?? ''] ?? null) : array_column($sec['estudiantes'], 'estudiante_id');
            if (! is_array($ids) || ($complementos['participantes_actividad'][$a['id']] ?? null) !== $ids) {
                throw new DomainException('Participantes de la actividad incompatibles con el padrón.');
            }
            $horas = intdiv($patron[0], 60);
            $docenteId = $a['docentes_ids'][0];
            $claveHoras = $a['tipo'] === 'LAB' && isset($particiones[$a['seccion_id']]) ? $grupo : '_TODOS';
            $sumas[$a['seccion_id']][$a['tipo']][$claveHoras] = ($sumas[$a['seccion_id']][$a['tipo']][$claveHoras] ?? 0) + $horas;
            $cargas[$docenteId] = ($cargas[$docenteId] ?? 0) + $horas;
            if ($a['tipo'] === 'LAB') {
                if (! is_string($grupo) || $grupo === '') {
                    throw new DomainException('LABORATORIO_GRUPO_UNICO_NO_ACREDITADO');
                }
                $limite = $config['capacidad_subgrupo_autorizada'] ?? null;
                if (! is_int($limite) || $limite < 1 || count($ids) > $limite) {
                    throw new DomainException('LABORATORIO_SUBGRUPOS_REQUERIDOS_O_LIMITE_AUSENTE');
                }
            }
            $actividades[] = ['id' => $a['id'], 'seccion_id' => $a['seccion_id'], 'docente_id' => $docenteId, 'tipo' => $a['tipo'], 'horas' => $horas, 'duraciones' => [$horas], 'estudiantes' => $ids, 'subgrupo' => $grupo, 'repeticion' => false, 'fuente_asignacion' => (string) $asignaciones[0]['id_asignacion']];
        }
        foreach ($secciones as $id => $sec) {
            foreach (['T', 'P', 'LAB'] as $tipo) {
                if ($tipo === 'LAB' && isset($particiones[$id])) {
                    foreach ($particiones[$id]['grupos'] as $grupo => $_) {
                        if (($sumas[$id]['LAB'][$grupo] ?? 0) !== $sec['horas']['LAB']) {
                            throw new DomainException('LABORATORIO_HORAS_POR_SUBGRUPO_INVALIDAS');
                        }
                    }

                    continue;
                }
                if (($sumas[$id][$tipo]['_TODOS'] ?? 0) !== $sec['horas'][$tipo]) {
                    throw new DomainException('Horas de actividad incompatibles con el curso.');
                }
            }
        }
        $bloques = array_column($d['bloques'], null, 'id_bloque');
        $disponibilidadPorDocente = array_column($disponibilidad, null, 'docente_id');
        if (count($disponibilidadPorDocente) !== count($disponibilidad) || count($disponibilidad) !== count($m['docentes'])) {
            throw new DomainException('Disponibilidad docente duplicada o ausente.');
        }
        foreach ($m['docentes'] as $doc) {
            $datosDocente = $doc['registro_institucional'];
            $declarada = $disponibilidadPorDocente[$doc['id']] ?? null;
            $nombre = $complementos['nombres_docentes'][$doc['id']] ?? null;
            if (($datosDocente['estado'] ?? null) !== 'ACTIVO' || ! isset($cargas[$doc['id']]) || ! is_int($datosDocente['horas_maximas'] ?? null) || $datosDocente['horas_maximas'] < $cargas[$doc['id']] || ! is_string($nombre) || $nombre === '' || ! is_array($declarada) || ($declarada['estado'] ?? null) !== 'DECLARADA' || ! is_array($declarada['franjas'] ?? null)) {
                throw new DomainException('Docente o disponibilidad incompatibles.');
            }
            $registradas = [];
            $presentadas = array_column(array_filter($doc['declaraciones'], fn ($v) => ($v['estado'] ?? null) === 'PRESENTADA'), 'id_declaracion');
            foreach ($doc['bloques_declarados'] as $fila) {
                $bloque = $bloques[$fila['id_bloque']] ?? null;
                if (! $bloque || ! preg_match('/^(\d\d):00:00$/', $bloque['hora_inicio'], $ini) || ! preg_match('/^(\d\d):00:00$/', $bloque['hora_fin'], $fin) || ! in_array($fila['id_declaracion'] ?? null, $presentadas, true)) {
                    throw new DomainException('Franja docente no representable o sin declaración.');
                }
                $registradas[] = ['dia' => $fila['dia_semana'], 'inicio' => (int) $ini[1], 'fin' => (int) $fin[1]];
            }
            if (! $presentadas || ! (new ConciliarFranjasDocentes)->coinciden($registradas, $declarada['franjas'])) {
                throw new DomainException('Disponibilidad declarada no coincide con la instantánea.');
            }
            $docentes[] = ['id' => $doc['id'], 'nombre' => $nombre];
        }
        foreach ($m['ambientes'] as $amb) {
            if (! in_array($amb['tipo'] ?? null, ['AULA', 'LAB'], true) || strtoupper((string) ($amb['estado'] ?? '')) !== 'ACTIVO' || ! is_int($amb['capacidad'] ?? null) || $amb['capacidad'] < 1 || empty($amb['codigo']) || empty($amb['nombre'])) {
                throw new DomainException('Ambiente incompatible.');
            }
            $ambientes[] = ['id' => (string) $amb['id_ambiente'], 'codigo' => $amb['codigo'], 'nombre' => $amb['nombre'], 'tipo' => $amb['tipo'], 'capacidad' => $amb['capacidad'], 'activo' => true];
        }
        $grupos = $complementos['grupos_laboratorio'] ?? [];
        if (! is_array($grupos)) {
            throw new DomainException('LABORATORIO_REFERENCIAS_DE_GRUPO_INVALIDAS');
        }
        $labs = array_values(array_filter($actividades, fn ($a) => $a['tipo'] === 'LAB'));
        $claves = array_map('strval', array_keys($grupos));
        $idsLabs = array_column($labs, 'id');
        sort($claves);
        sort($idsLabs);
        if ($claves !== $idsLabs) {
            throw new DomainException('LABORATORIO_REFERENCIAS_DE_GRUPO_INVALIDAS');
        }
        $grupoPorSeccion = [];
        foreach ($labs as $lab) {
            $sid = $lab['seccion_id'];
            if (! isset($particiones[$sid]) && isset($grupoPorSeccion[$sid]) && $grupoPorSeccion[$sid] !== $lab['subgrupo']) {
                throw new DomainException('LABORATORIO_SUBGRUPOS_NO_SOPORTADOS');
            }
            $grupoPorSeccion[$sid] = $lab['subgrupo'];
        }
        foreach ($actividades as $actividad) {
            $tipo = $actividad['tipo'] === 'LAB' ? 'LAB' : 'AULA';
            if (! array_filter($ambientes, fn ($amb) => $amb['tipo'] === $tipo && $amb['capacidad'] >= count($actividad['estudiantes']))) {
                throw new DomainException('AMBIENTE_COMPATIBLE_CON_CAPACIDAD_AUSENTE: '.$actividad['id']);
            }
        }
        if (count($m['asignaciones']) !== count($actividades) || count($m['ofertas']) !== count(array_unique(array_column($secciones, 'oferta_id'))) || count($m['cursos']) !== count(array_unique(array_column($secciones, 'curso_plan_id')))) {
            throw new DomainException('Ofertas, cursos o asignaciones adicionales no representadas.');
        }

        $entrada = ['contrato' => 'HORARIOS_MOTOR_V1', 'periodo' => (string) $d['periodo']['id_periodo'], 'configuracion' => $config,
            'secciones' => array_values($secciones), 'actividades' => $actividades, 'docentes' => $docentes, 'ambientes' => $ambientes,
            'disponibilidades' => $disponibilidad, 'bloqueos' => [], 'sesiones' => [], 'alcance' => [], 'tiempo_limite' => $complementos['tiempo_limite']];
        if ($particiones !== []) {
            $entrada['particiones_academicas'] = $particiones;
        }

        return $entrada;
    }
}
