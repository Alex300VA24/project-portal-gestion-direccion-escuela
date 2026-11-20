<?php

namespace App\Modules\GestionHorarios\Engine;

use App\Modules\GestionHorarios\Contracts\ProveedorComplementosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorComplementosTransaccional;
use App\Modules\GestionHorarios\Services\InstantaneaGeneracion;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use JsonException;
use PDOException;
use RuntimeException;

/** Ensayo aislado: no es un proveedor ni un ejecutor institucional. */
final class EnsambladorAcademicoPrueba
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ProveedorComplementosAcademicos $proveedor) {}

    public function construir(array $instantanea, ?string $versionEsperada = null): array
    {
        $cfg = $this->db->getConfig();
        if (! app()->environment('testing') || $this->db->getDriverName() !== 'pgsql' || ($cfg['host'] ?? null) !== '127.0.0.1' || (int) ($cfg['port'] ?? 0) !== 55471 || ($cfg['database'] ?? null) !== 'horarios_27b2_test' || ($cfg['username'] ?? null) !== 'horarios_test') {
            throw new RuntimeException('El ensamblador ficticio requiere el entorno aislado de pruebas.');
        }
        $real = $this->db->selectOne("select current_database() as base, current_user as usuario, current_setting('server_version_num') as version");
        if ($real->base !== 'horarios_27b2_test' || $real->usuario !== 'horarios_test' || (int) $real->version < 170000 || (int) $real->version >= 180000) {
            throw new RuntimeException('La identidad PostgreSQL de pruebas no coincide.');
        }
        try {
            $preparacion = (new AdaptadorPreparacion)->convertir($instantanea);
            $m = (new MapeoAcademicoParcial)->convertir($preparacion);
        } catch (DomainException|JsonException) {
            return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'INSTANTANEA_INCOMPATIBLE', 'entidad' => 'periodo']], 'entrada' => null];
        }
        $periodo = (string) $m['periodo']['id_periodo'];
        if (! $this->proveedor instanceof ProveedorComplementosTransaccional || $this->proveedor->conexion() !== $this->db) {
            return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'FUENTE_SIN_CONSISTENCIA', 'entidad' => 'complementos']], 'entrada' => null];
        }
        if ($versionEsperada === null || $versionEsperada === '') {
            return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'VERSION_ESPERADA_AUSENTE', 'entidad' => 'complementos']], 'entrada' => null];
        }
        try {
            $captura = (new InstantaneaGeneracion($this->db))->construirConComplementos((int) $periodo, $this->proveedor);
        } catch (QueryException|PDOException) {
            return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'FUENTE_NO_DISPONIBLE', 'entidad' => 'complementos']], 'entrada' => null];
        }
        if (! hash_equals($m['huella_instantanea'], $captura['instantanea']['huella_sha256'])) {
            return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'INSTANTANEA_DESACTUALIZADA', 'entidad' => 'periodo']], 'entrada' => null];
        }
        $c = $captura['complementos'];
        $errores = [];
        $error = static function (string $codigo, string $entidad = '') use (&$errores): void {
            $errores[] = compact('codigo', 'entidad');
        };
        if (($c['contrato'] ?? null) !== 'COMPLEMENTOS_ACADEMICOS_FICTICIOS_V1' || ($c['origen'] ?? null) !== 'PRUEBA_FICTICIA' || ($c['periodo'] ?? null) !== $periodo || ($c['huella_instantanea'] ?? null) !== $m['huella_instantanea'] || ! is_string($c['fuente'] ?? null) || $c['fuente'] === '' || ! is_string($c['version'] ?? null) || $c['version'] === '') {
            $error('PROCEDENCIA_O_INSTANTANEA_INVALIDA');
        }
        if (($c['consistencia'] ?? null) !== 'MISMA_TRANSACCION_POSTGRESQL') {
            $error('FUENTE_SIN_CONSISTENCIA');
        }
        if (($c['version'] ?? null) !== $versionEsperada || ! is_array($c['versiones'] ?? null)) {
            $error('VERSION_INCOMPATIBLE');
        } else {
            foreach (['poblacion', 'participantes', 'particiones', 'sesiones', 'calendario', 'disponibilidad', 'restricciones', 'patrones'] as $fuenteVersionada) {
                if (($c['versiones'][$fuenteVersionada] ?? null) !== $versionEsperada) {
                    $error('VERSION_INCOMPATIBLE', $fuenteVersionada);
                }
            }
        }
        foreach (['poblacion', 'participantes_actividad', 'particiones', 'sesiones_fisicas', 'calendario', 'disponibilidad', 'restricciones', 'patrones', 'nombres_docentes'] as $campo) {
            if (! isset($c[$campo]) || ! is_array($c[$campo])) {
                $error('CONTRATO_AUSENTE', $campo);
            }
        }
        if ($errores) {
            return ['ejecutable' => false, 'diagnosticos' => $errores, 'entrada' => null];
        }
        if (($c['restricciones']['estado'] ?? null) !== 'SIN_RESTRICCIONES_ADICIONALES_CONFIRMADAS' || ! empty($m['restricciones']) || ! empty($m['bloqueos'])) {
            $error('RESTRICCIONES_NO_REPRESENTADAS');
        }
        if (! is_string($c['escuela_id'] ?? null) || $c['escuela_id'] === '' || ! is_int($c['tiempo_limite'] ?? null) || $c['tiempo_limite'] < 1 || $c['tiempo_limite'] > 150 || ($c['politica'] ?? null) !== 'SECCION_Y_COMPARTIDOS_V4') {
            $error('ALCANCE_O_POLITICA_INVALIDOS');
        }
        $cal = $c['calendario']['calendario'] ?? null;
        $bloquesBase = is_array($cal) ? ($cal['bloques_hora_base'] ?? null) : null;
        if (! is_array($cal) || ! is_array($cal['dias'] ?? null) || ! array_is_list($cal['dias']) || ! $cal['dias'] || ! is_array($bloquesBase) || ! array_is_list($bloquesBase) || count($bloquesBase) !== 12 || ($cal['receso']['inicio'] ?? null) !== 13 || ($cal['receso']['fin'] ?? null) !== 14) {
            // Limitación actual de la formulación SOFT, no política institucional.
            $error('CALENDARIO_NO_REPRESENTABLE');
        }
        $cursos = array_column($m['cursos'], null, 'id_curso_plan');
        $bloques = array_column($m['bloques'], null, 'id_bloque');
        foreach ($c['disponibilidad'] as $declarada) {
            $idDocente = (string) ($declarada['docente_id'] ?? '');
            $docente = current(array_filter($m['docentes'], fn ($d) => $d['id'] === $idDocente));
            if (($declarada['estado'] ?? null) === 'SIN_DECLARAR') {
                if (! $docente || $docente['declaraciones'] !== [] || $docente['bloques_declarados'] !== [] || ($declarada['franjas'] ?? null) !== []) {
                    $error('AUSENCIA_DECLARACION_NO_ACREDITADA', $idDocente);
                }

                continue;
            }
            if (! $docente || ! array_filter($docente['declaraciones'], fn ($d) => ($d['estado'] ?? null) === 'PRESENTADA') || ! is_array($declarada['franjas'] ?? null)) {
                $error('DISPONIBILIDAD_SIN_DECLARACION', $idDocente);

                continue;
            }
            $presentadas = array_map('strval', array_column(array_filter($docente['declaraciones'], fn ($d) => ($d['estado'] ?? null) === 'PRESENTADA'), 'id_declaracion'));
            $registradas = [];
            foreach ($docente['bloques_declarados'] as $d) {
                if (! in_array((string) ($d['id_declaracion'] ?? ''), $presentadas, true)) {
                    continue;
                }
                $bloque = $bloques[(string) $d['id_bloque']] ?? null;
                if ($bloque && preg_match('/^(\d{2}):00:00$/', (string) $bloque['hora_inicio'], $ini) && preg_match('/^(\d{2}):00:00$/', (string) $bloque['hora_fin'], $fin)) {
                    $registradas[] = ['dia' => $d['dia_semana'], 'inicio' => (int) $ini[1], 'fin' => (int) $fin[1]];
                } else {
                    $error('FRANJA_NO_REPRESENTABLE', $idDocente);
                }
            }
            if (! (new ConciliarFranjasDocentes)->coinciden($registradas, $declarada['franjas'])) {
                $error('FRANJA_NO_ACREDITADA', $idDocente);
            }
        }
        $secciones = [];
        $planes = [];
        foreach ($m['secciones'] as $s) {
            $curso = $cursos[$s['curso_plan_id']] ?? null;
            $padron = $c['poblacion'][$s['id']] ?? null;
            if (! $curso || $s['oferta_id'] === null || $s['escuela_id'] !== ($c['escuela_id'] ?? null) || ! is_array($padron) || ! $padron || ! in_array($s['tipo_oferta'], ['REGULAR'], true)) {
                $error('SECCION_O_POBLACION_INCOMPLETA', $s['id']);

                continue;
            }
            $planes[$s['plan_id']] = true;
            $secciones[] = ['id' => $s['id'], 'periodo' => $periodo, 'escuela_id' => $s['escuela_id'], 'plan_id' => $s['plan_id'], 'curso_plan_id' => $s['curso_plan_id'], 'curso_id' => $s['curso_plan_id'], 'nombre' => $curso['nombre'] ?? null, 'ciclo' => (int) $curso['ciclo'], 'etiqueta' => $s['codigo'], 'capacidad' => (int) $s['capacidad'], 'estado' => $s['registro_institucional']['estado'], 'horas' => ['T' => $curso['horas_teoria'], 'P' => $curso['horas_practica'], 'LAB' => $curso['horas_laboratorio']], 'estudiantes' => $padron];
        }
        $actividades = [];
        foreach ($m['actividades'] as $a) {
            $id = $a['id'];
            if (count($a['docentes_ids']) !== 1 || ! isset($c['participantes_actividad'][$id], $c['patrones'][$id]) || ! is_array($c['participantes_actividad'][$id]) || ! is_array($c['patrones'][$id])) {
                $error('ACTIVIDAD_SIN_DOCENTE_POBLACION_O_PATRON', $id);

                continue;
            }
            $asignaciones = array_values(array_filter($m['asignaciones'], fn ($v) => (string) $v['id_actividad'] === $id && ($v['estado'] ?? null) === 'CONFIRMADA'));
            $actividades[] = ['id' => $id, 'seccion_id' => $a['seccion_id'], 'docente_id' => $a['docentes_ids'][0], 'tipo_academico' => $a['tipo_institucional'], 'duraciones_minutos' => $c['patrones'][$id], 'estudiantes' => $c['participantes_actividad'][$id], 'subgrupo' => $c['particiones'][$id] ?? null, 'repeticion' => false, 'fuente_asignacion' => (string) $asignaciones[0]['id_asignacion']];
        }
        $docentes = [];
        foreach ($m['docentes'] as $d) {
            $nombre = $c['nombres_docentes'][$d['id']] ?? null;
            if (! is_string($nombre) || $nombre === '') {
                $error('NOMBRE_DOCENTE_AUSENTE', $d['id']);

                continue;
            }
            $docentes[] = ['id' => $d['id'], 'nombre' => $nombre];
        }
        $ambientes = [];
        foreach ($m['ambientes'] as $a) {
            if (! in_array($a['tipo'], ['AULA', 'LAB'], true) || ! in_array(strtoupper((string) $a['estado']), ['ACTIVO', 'INACTIVO'], true) || ! is_numeric($a['capacidad'])) {
                $error('AMBIENTE_SIN_EQUIVALENCIA', (string) $a['id_ambiente']);

                continue;
            }
            $ambientes[] = ['id' => (string) $a['id_ambiente'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'], 'tipo' => $a['tipo'], 'capacidad' => (int) $a['capacidad'], 'activo' => strtoupper($a['estado']) === 'ACTIVO'];
        }
        foreach ($m['ofertas'] as $o) {
            $asociadas = array_filter($m['secciones'], fn ($s) => $s['oferta_id'] === (string) $o['id_oferta']);
            if (! $asociadas) {
                $error('OFERTA_SIN_SECCION', (string) $o['id_oferta']);
            } elseif (! array_filter($m['actividades'], fn ($a) => in_array($a['seccion_id'], array_column($asociadas, 'id'), true))) {
                $error('OFERTA_SIN_ACTIVIDADES', (string) $o['id_oferta']);
            }
        }
        if ($errores) {
            return ['ejecutable' => false, 'diagnosticos' => $errores, 'entrada' => null];
        }
        $ofertas = array_map(fn ($o) => ['id' => (string) $o['id_oferta'], 'periodo' => $periodo, 'curso_id' => (string) $o['id_curso_plan'], 'tipo' => $o['tipo_oferta'], 'plan_id' => (string) $cursos[(string) $o['id_curso_plan']]['id_plan']], $m['ofertas']);
        $asociaciones = array_map(fn ($s) => ['seccion_id' => $s['id'], 'oferta_id' => $s['oferta_id']], $m['secciones']);
        $fuente = ['contrato' => 'HORARIOS_DATOS_FICTICIOS_V1', 'origen' => 'PRUEBA_FICTICIA', 'periodo' => $periodo, 'politica' => $c['politica'], 'tiempo_limite' => $c['tiempo_limite'], 'ambito_ficticio' => ['escuela_id' => $c['escuela_id'], 'planes' => array_map('strval', array_keys($planes))], 'configuracion' => $c['calendario'], 'secciones' => $secciones, 'actividades' => $actividades, 'docentes' => $docentes, 'ambientes' => $ambientes, 'disponibilidades' => $c['disponibilidad'], 'bloqueos' => [], 'sesiones' => [], 'ofertas' => $ofertas, 'asociaciones' => $asociaciones, 'sesiones_fisicas_compartidas' => $c['sesiones_fisicas']];
        if (array_key_exists('prioridad_fuente', $c)) {
            $registro = $c['prioridad_fuente'];
            if (! is_array($registro) || ($registro['escuela_id'] ?? null) !== $c['escuela_id'] || ! is_int($registro['version'] ?? null) || $registro['version'] < 0 || ! is_array($registro['orden'] ?? null) || ! array_is_list($registro['orden'])) {
                return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'PRIORIDAD_FUENTE_INVALIDA', 'entidad' => 'prioridad_docente']], 'entrada' => null];
            }
            $idsPrioridad = [];
            foreach ($registro['orden'] as $indice => $fila) {
                if (! is_array($fila) || ! is_string($fila['docente_id'] ?? null) || $fila['docente_id'] === '' || isset($idsPrioridad[$fila['docente_id']]) || ($fila['posicion'] ?? null) !== $indice + 1) {
                    return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'PRIORIDAD_FUENTE_INVALIDA', 'entidad' => 'prioridad_docente']], 'entrada' => null];
                }
                $idsPrioridad[$fila['docente_id']] = true;
            }
        }
        if (array_key_exists('prioridad_docente', $c)) {
            $prioridad = $c['prioridad_docente'];
            if (! is_array($prioridad) || ($prioridad['escuela_id'] ?? null) !== $c['escuela_id'] || ! isset($registro) || ($prioridad['version'] ?? null) !== $registro['version']) {
                return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'PRIORIDAD_ESCUELA_INVALIDA', 'entidad' => 'prioridad_docente']], 'entrada' => null];
            }
            $presentes = array_fill_keys(array_column($docentes, 'id'), true);
            $esperado = array_values(array_filter($registro['orden'], fn ($fila) => isset($presentes[$fila['docente_id']])));
            foreach ($esperado as $i => &$fila) {
                $fila['posicion'] = $i + 1;
            }
            unset($fila);
            if (($prioridad['orden'] ?? null) !== $esperado) {
                return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'PRIORIDAD_ORDEN_INCOMPATIBLE', 'entidad' => 'prioridad_docente']], 'entrada' => null];
            }
            unset($prioridad['escuela_id']);
            $fuente['prioridad_docente'] = $prioridad;
        } elseif (isset($registro)) {
            $presentes = array_fill_keys(array_column($docentes, 'id'), true);
            foreach ($registro['orden'] as $fila) {
                if (isset($presentes[$fila['docente_id']])) {
                    return ['ejecutable' => false, 'diagnosticos' => [['codigo' => 'PRIORIDAD_ORDEN_INCOMPATIBLE', 'entidad' => 'prioridad_docente']], 'entrada' => null];
                }
            }
        }
        $convertida = (new AdaptadorMotorFicticio)->convertir($fuente);

        $evidencia = ['version' => 'PREPARACION_ACADEMICA_PRUEBA_V2', 'mapeador' => 'MAPEO_ACADEMICO_PARCIAL_V1', 'periodo' => $periodo, 'escuela' => $c['escuela_id'], 'planes' => $fuente['ambito_ficticio']['planes'], 'demanda_version' => $m['demanda']['version'] ?? null, 'instantanea_sha256' => $m['huella_instantanea'], 'fuente' => $c['fuente'], 'version_fuente' => $c['version'], 'versiones' => $c['versiones'], 'complementos' => $c, 'entrada' => $convertida['entrada']];
        // El presupuesto del solver identifica la solicitud, no una modificación académica.
        unset($evidencia['complementos']['tiempo_limite'], $evidencia['entrada']['tiempo_limite']);
        $huellaAcademica = $convertida['compatible'] ? hash('sha256', json_encode(self::canonizar($evidencia), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : null;

        return ['ejecutable' => $convertida['compatible'], 'diagnosticos' => $convertida['diagnosticos'], 'entrada' => $convertida['entrada'], 'huella_sha256' => $convertida['huella_sha256'] ?? null, 'huella_academica_sha256' => $huellaAcademica, 'version_huella_academica' => 'PREPARACION_ACADEMICA_PRUEBA_V2', 'captura_finalizada_en' => now('UTC')->toISOString(), 'procedencia' => ['fuente' => $c['fuente'], 'version' => $c['version'], 'versiones' => $c['versiones'], 'instantanea' => $m['huella_instantanea']]];
    }

    private static function canonizar(array $valor, ?string $campo = null): array
    {
        if (! array_is_list($valor)) {
            ksort($valor, SORT_STRING);
        }
        foreach ($valor as $clave => $elemento) {
            if (is_array($elemento)) {
                $valor[$clave] = self::canonizar($elemento, (string) $clave);
            }
        }
        $coleccionesSinOrden = ['secciones', 'actividades', 'docentes', 'ambientes', 'disponibilidad', 'disponibilidades', 'bloqueos', 'ofertas', 'asociaciones', 'sesiones_fisicas', 'sesiones_fisicas_compartidas'];
        if (array_is_list($valor) && in_array($campo, $coleccionesSinOrden, true) && count(array_filter($valor, 'is_array')) === count($valor)) {
            usort($valor, static fn (array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($b, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        }

        return $valor;
    }
}
