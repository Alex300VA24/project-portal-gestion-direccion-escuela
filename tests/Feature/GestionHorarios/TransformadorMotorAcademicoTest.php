<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Engine\AdaptadorPreparacion;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use DomainException;
use PHPUnit\Framework\TestCase;

final class TransformadorMotorAcademicoTest extends TestCase
{
    private function fixture(): array
    {
        $datos = [
            'periodo' => ['id_periodo' => 1],
            'cursos' => [['id_curso_plan' => 10, 'id_curso' => 11, 'id_plan' => 12, 'id_escuela' => 13, 'nombre' => 'Matemática ficticia', 'ciclo' => 2, 'horas_teoria' => 2, 'horas_practica' => 0, 'horas_laboratorio' => 0]],
            'ofertas' => [['id_oferta' => 20, 'id_periodo' => 1, 'id_curso_plan' => 10, 'tipo_oferta' => 'REGULAR', 'estado' => 'AUTORIZADA']],
            'secciones' => [['id_seccion' => 30, 'id_periodo' => 1, 'id_curso_plan' => 10, 'codigo' => 'A', 'capacidad' => 20, 'estado' => 'AUTORIZADA']],
            'oferta_seccion' => [['id_oferta_seccion' => 21, 'id_oferta' => 20, 'id_seccion' => 30, 'estado' => 'VIGENTE']],
            'actividades' => [['id_actividad' => 40, 'id_seccion' => 30, 'tipo_actividad' => 'TEORIA', 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00']],
            'asignaciones' => [['id_asignacion' => 50, 'id_actividad' => 40, 'id_docente' => 60, 'estado' => 'CONFIRMADA']],
            'docentes' => [['id_docente' => 60, 'estado' => 'ACTIVO', 'horas_maximas' => 10]], 'declaraciones' => [['id_declaracion' => 70, 'id_periodo' => 1, 'id_docente' => 60, 'estado' => 'PRESENTADA']],
            'disponibilidades' => [['id_disponibilidad' => 80, 'id_periodo' => 1, 'id_docente' => 60, 'id_declaracion' => 70, 'id_bloque' => 90, 'dia_semana' => 'LUNES']],
            'bloques' => [['id_bloque' => 90, 'id_periodo' => 1, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00']],
            'ambientes' => [['id_ambiente' => 100, 'codigo' => 'A1', 'nombre' => 'Aula ficticia', 'tipo' => 'AULA', 'capacidad' => 20, 'estado' => 'ACTIVO']],
            'bloqueos' => [], 'restricciones' => [], 'demanda' => null, 'horarios' => [],
        ];
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $instantanea = ['contrato' => 'INSTANTANEA_INSTITUCIONAL_V1', 'datos' => $datos, 'huella_sha256' => $hash];
        $modelo = json_decode(file_get_contents(__DIR__.'/python/entrada_ficticia.json'), true, 512, JSON_THROW_ON_ERROR);
        $config = $modelo['configuracion'];
        $config['politica_conflictos'] = 'SECCION_Y_COMPARTIDOS_V4';
        $complementos = ['contrato' => 'COMPLEMENTOS_MOTOR_ACADEMICO_V1', 'fuente' => 'fixture', 'version' => '1', 'periodo' => '1', 'huella_instantanea' => $hash, 'tiempo_limite' => 5,
            'restricciones' => ['estado' => 'SIN_RESTRICCIONES_ADICIONALES_CONFIRMADAS'], 'sesiones_fisicas' => [], 'particiones' => [],
            'poblacion' => ['30' => [['estudiante_id' => 'F1', 'preferencia' => 'MANANA']]], 'participantes_actividad' => ['40' => ['F1']], 'patrones' => ['40' => [120]], 'nombres_docentes' => ['60' => 'Docente ficticio'],
            'disponibilidad' => [['docente_id' => '60', 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]]], 'configuracion' => $config];

        return [$instantanea, $complementos];
    }

    public function test_convierte_y_ejecuta_motor_real(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO con OR-Tools.');
        }
        [$instantanea, $complementos] = $this->fixture();
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->paraMotor($adaptador->convertir($instantanea), $complementos);
        self::assertSame('HORARIOS_MOTOR_V1', $entrada['contrato']);
        self::assertSame('11', $entrada['secciones'][0]['curso_institucional_id']);
        self::assertSame('20', $entrada['secciones'][0]['oferta_id']);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada);
        self::assertSame('OPTIMAL', $resultado['estado']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        self::assertCount(1, $resultado['sesiones']);
        self::assertSame('LUNES', $resultado['sesiones'][0]['dia']);
        self::assertSame(9, $resultado['sesiones'][0]['inicio']);
        self::assertSame(11, $resultado['sesiones'][0]['fin']);
        self::assertSame('100', $resultado['sesiones'][0]['ambiente_id']);
    }

    private function fixtureMultiple(bool $unSoloTurno = false, bool $unAula = false): array
    {
        [$instantanea, $c] = $this->fixture();
        $d = $instantanea['datos'];
        $d['cursos'][] = ['id_curso_plan' => 14, 'id_curso' => 15, 'id_plan' => 12, 'id_escuela' => 13, 'nombre' => 'Física ficticia', 'ciclo' => 2, 'horas_teoria' => 2, 'horas_practica' => 0, 'horas_laboratorio' => 0];
        $d['ofertas'][] = ['id_oferta' => 22, 'id_periodo' => 1, 'id_curso_plan' => 14, 'tipo_oferta' => 'REGULAR', 'estado' => 'AUTORIZADA'];
        $d['secciones'] = $d['oferta_seccion'] = $d['actividades'] = $d['asignaciones'] = [];
        $c['poblacion'] = $c['participantes_actividad'] = $c['patrones'] = [];
        foreach ([[30, 10, 20, 'A', 60], [31, 10, 20, 'B', 61], [32, 14, 22, 'A', 60], [33, 14, 22, 'B', 61]] as $i => [$sid, $curso, $oferta, $etiqueta, $docente]) {
            $actividad = 40 + $i;
            $d['secciones'][] = ['id_seccion' => $sid, 'id_periodo' => 1, 'id_curso_plan' => $curso, 'codigo' => $etiqueta, 'capacidad' => 20, 'estado' => 'AUTORIZADA'];
            $d['oferta_seccion'][] = ['id_oferta_seccion' => 200 + $i, 'id_oferta' => $oferta, 'id_seccion' => $sid, 'estado' => 'VIGENTE'];
            $d['actividades'][] = ['id_actividad' => $actividad, 'id_seccion' => $sid, 'tipo_actividad' => 'TEORIA', 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'];
            $d['asignaciones'][] = ['id_asignacion' => 50 + $i, 'id_actividad' => $actividad, 'id_docente' => $docente, 'estado' => 'CONFIRMADA'];
            $estudiante = 'F'.$etiqueta;
            $c['poblacion'][(string) $sid] = [['estudiante_id' => $estudiante, 'preferencia' => 'MANANA']];
            $c['participantes_actividad'][(string) $actividad] = [$estudiante];
            $c['patrones'][(string) $actividad] = [120];
        }
        $d['docentes'][] = ['id_docente' => 61, 'estado' => 'ACTIVO', 'horas_maximas' => 10];
        $d['declaraciones'][] = ['id_declaracion' => 71, 'id_periodo' => 1, 'id_docente' => 61, 'estado' => 'PRESENTADA'];
        $d['disponibilidades'][] = ['id_disponibilidad' => 81, 'id_periodo' => 1, 'id_docente' => 61, 'id_declaracion' => 71, 'id_bloque' => 90, 'dia_semana' => 'LUNES'];
        $c['nombres_docentes']['61'] = 'Segundo docente ficticio';
        $c['disponibilidad'][] = ['docente_id' => '61', 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]];
        if (! $unSoloTurno) {
            foreach ([60, 61] as $i => $docente) {
                $d['disponibilidades'][] = ['id_disponibilidad' => 82 + $i, 'id_periodo' => 1, 'id_docente' => $docente, 'id_declaracion' => 70 + $i, 'id_bloque' => 90, 'dia_semana' => 'MARTES'];
                $c['disponibilidad'][$i]['franjas'][] = ['dia' => 'MARTES', 'inicio' => 9, 'fin' => 11];
            }
        }
        if (! $unAula) {
            $d['ambientes'][] = ['id_ambiente' => 101, 'codigo' => 'A2', 'nombre' => 'Segunda aula', 'tipo' => 'AULA', 'capacidad' => 20, 'estado' => 'ACTIVO'];
        }
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $instantanea['datos'] = $d;
        $instantanea['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;

        return [$instantanea, $c];
    }

    public function test_multiples_actividades_se_resuelven_y_se_validan_independientemente(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO con OR-Tools.');
        }
        [$instantanea, $complementos] = $this->fixtureMultiple();
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->paraMotor($adaptador->convertir($instantanea), $complementos);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertCount(4, $resultado['sesiones']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        self::assertCount(2, $entrada['docentes']);
        self::assertCount(2, $entrada['ambientes']);
        foreach ([[$this->fixtureMultiple(true, false), 'disponibilidad'], [$this->fixtureOcupacion(true), 'aula']] as [$datos, $causa]) {
            [$i, $c] = $datos;
            $solicitud = $adaptador->paraMotor($adaptador->convertir($i), $c);
            self::assertSame('INFEASIBLE', (new EjecutorPythonPruebas($python))->ejecutarExacto($solicitud)['estado'], $causa);
        }
        [$i, $c] = $this->fixtureOcupacion(false);
        $solicitud = $adaptador->paraMotor($adaptador->convertir($i), $c);
        $control = (new EjecutorPythonPruebas($python))->ejecutarExacto($solicitud);
        self::assertContains($control['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud, $control)['solucion_validada']);
        $control['sesiones'][1]['ambiente_id'] = $control['sesiones'][0]['ambiente_id'];
        try {
            (new ValidarResultadoFicticio)->validar($solicitud, $control);
            self::fail('Debe rechazar el aula duplicada.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }

    public function test_rechaza_docente_inexistente_en_instantanea(): void
    {
        [$instantanea] = $this->fixtureMultiple();
        $instantanea['datos']['asignaciones'][0]['id_docente'] = 999;
        $instantanea['huella_sha256'] = hash('sha256', json_encode($instantanea['datos'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->expectException(DomainException::class);
        (new AdaptadorPreparacion)->convertir($instantanea);
    }

    private function fixtureTeoriaPracticaLaboratorio(): array
    {
        [$instantanea, $c] = $this->fixtureMultiple();
        $d = $instantanea['datos'];
        $d['cursos'][0]['horas_practica'] = 2;
        $d['cursos'][0]['horas_laboratorio'] = 2;
        foreach ([[44, 30, 60, 'PRACTICA'], [45, 30, 60, 'LABORATORIO'], [46, 31, 61, 'PRACTICA'], [47, 31, 61, 'LABORATORIO']] as [$id, $sid, $doc, $tipo]) {
            $d['actividades'][] = ['id_actividad' => $id, 'id_seccion' => $sid, 'tipo_actividad' => $tipo, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'];
            $d['asignaciones'][] = ['id_asignacion' => $id + 10, 'id_actividad' => $id, 'id_docente' => $doc, 'estado' => 'CONFIRMADA'];
            $c['patrones'][(string) $id] = [120];
            $c['participantes_actividad'][(string) $id] = array_column($c['poblacion'][(string) $sid], 'estudiante_id');
            if ($tipo === 'LABORATORIO') {
                $c['grupos_laboratorio'][(string) $id] = 'GRUPO_COMPLETO';
            }
        }
        $d['ambientes'][] = ['id_ambiente' => 102, 'codigo' => 'L1', 'nombre' => 'Laboratorio ficticio', 'tipo' => 'LAB', 'capacidad' => 20, 'estado' => 'ACTIVO'];
        foreach ([60, 61] as $i => $doc) {
            foreach (['MIERCOLES', 'JUEVES', 'VIERNES'] as $j => $dia) {
                $d['disponibilidades'][] = ['id_disponibilidad' => 100 + $i * 3 + $j, 'id_periodo' => 1, 'id_docente' => $doc, 'id_declaracion' => 70 + $i, 'id_bloque' => 90, 'dia_semana' => $dia];
                $c['disponibilidad'][$i]['franjas'][] = ['dia' => $dia, 'inicio' => 9, 'fin' => 11];
            }
        }
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $instantanea['datos'] = $d;
        $instantanea['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;

        return [$instantanea, $c];
    }

    public function test_teoria_practica_y_laboratorio_recorren_motor_real(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO con OR-Tools.');
        }
        [$i, $c] = $this->fixtureTeoriaPracticaLaboratorio();
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->paraMotor($adaptador->convertir($i), $c);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertCount(8, $resultado['sesiones']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        $tipos = array_column($entrada['actividades'], 'tipo', 'id');
        foreach ($resultado['sesiones'] as $sesion) {
            self::assertSame($tipos[$sesion['actividad_id']] === 'LAB' ? '102' : true, $tipos[$sesion['actividad_id']] === 'LAB' ? $sesion['ambiente_id'] : in_array($sesion['ambiente_id'], ['100', '101'], true));
        }
    }

    public function test_rechaza_laboratorio_sin_ambiente_o_capacidad_y_subgrupos(): void
    {
        foreach (['tipo', 'capacidad', 'subgrupos', 'limite', 'duracion'] as $caso) {
            [$i, $c] = $this->fixtureTeoriaPracticaLaboratorio();
            if ($caso === 'tipo') {
                $i['datos']['ambientes'][2]['tipo'] = 'AULA';
            } elseif ($caso === 'capacidad') {
                $i['datos']['ambientes'][2]['capacidad'] = 1;
                $c['poblacion']['30'][] = ['estudiante_id' => 'F2', 'preferencia' => 'MANANA'];
                foreach (['40', '44', '45'] as $actividad) {
                    $c['participantes_actividad'][$actividad][] = 'F2';
                }
            } elseif ($caso === 'subgrupos') {
                $c['particiones'] = ['45' => 'G1'];
            } elseif ($caso === 'limite') {
                $c['configuracion']['capacidad_subgrupo_autorizada'] = 0;
            } else {
                $c['patrones']['45'] = [90];
            }
            $hash = hash('sha256', json_encode($i['datos'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $i['huella_sha256'] = $hash;
            $c['huella_instantanea'] = $hash;
            try {
                $adaptador = new AdaptadorPreparacion;
                $adaptador->paraMotor($adaptador->convertir($i), $c);
                self::fail('Caso no rechazado: '.$caso);
            } catch (DomainException $e) {
                if ($caso === 'subgrupos') {
                    self::assertStringContainsString('LABORATORIO_POLITICA_PARTICION_NO_ACREDITADA', $e->getMessage());
                } elseif ($caso === 'capacidad' || $caso === 'tipo') {
                    self::assertStringContainsString('AMBIENTE_COMPATIBLE_CON_CAPACIDAD_AUSENTE', $e->getMessage());
                } else {
                    self::assertNotSame('', $e->getMessage());
                }
            }
        }
    }

    public function test_docente_ocupado_vuelve_inviable_el_caso_t_p_lab(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO con OR-Tools.');
        }
        [$i, $c] = $this->fixtureTeoriaPracticaLaboratorio();
        $i['datos']['disponibilidades'] = array_values(array_filter($i['datos']['disponibilidades'], fn ($v) => $v['dia_semana'] === 'LUNES'));
        foreach ($c['disponibilidad'] as &$declaracion) {
            $declaracion['franjas'] = array_values(array_filter($declaracion['franjas'], fn ($v) => $v['dia'] === 'LUNES'));
        }
        unset($declaracion);
        $hash = hash('sha256', json_encode($i['datos'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $i['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;
        $adaptador = new AdaptadorPreparacion;
        $solicitud = $adaptador->paraMotor($adaptador->convertir($i), $c);
        self::assertSame('INFEASIBLE', (new EjecutorPythonPruebas($python))->ejecutarExacto($solicitud)['estado']);
    }

    private function fixtureLaboratorioParticionado(bool $unLaboratorio = false): array
    {
        [$i, $c] = $this->fixtureTeoriaPracticaLaboratorio();
        $d = $i['datos'];
        $c['configuracion']['politica_laboratorios'] = 'PARTICION_EXPLICITA_V1';
        $c['configuracion']['capacidad_subgrupo_autorizada'] = 1;
        $c['poblacion']['30'][] = ['estudiante_id' => 'F2', 'preferencia' => 'MANANA'];
        foreach (['40', '44'] as $actividad) {
            $c['participantes_actividad'][$actividad][] = 'F2';
        }
        $c['particiones'] = ['30' => ['id' => 'PART-30', 'seccion_id' => '30', 'curso_plan_id' => '10', 'grupos' => [
            ['id' => 'G1', 'estudiantes' => ['FA']], ['id' => 'G2', 'estudiantes' => ['F2']],
        ]]];
        $c['grupos_laboratorio']['45'] = 'G1';
        $c['grupos_laboratorio']['48'] = 'G2';
        $c['patrones']['48'] = [120];
        $c['participantes_actividad']['48'] = ['F2'];
        $d['actividades'][] = ['id_actividad' => 48, 'id_seccion' => 30, 'tipo_actividad' => 'LABORATORIO', 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00'];
        foreach ([62, 63] as $doc) {
            $d['docentes'][] = ['id_docente' => $doc, 'estado' => 'ACTIVO', 'horas_maximas' => 4];
            $d['declaraciones'][] = ['id_declaracion' => $doc + 10, 'id_periodo' => 1, 'id_docente' => $doc, 'estado' => 'PRESENTADA'];
            $d['disponibilidades'][] = ['id_disponibilidad' => $doc + 50, 'id_periodo' => 1, 'id_docente' => $doc, 'id_declaracion' => $doc + 10, 'id_bloque' => 90, 'dia_semana' => 'LUNES'];
            $c['nombres_docentes'][(string) $doc] = 'Docente laboratorio '.$doc;
            $c['disponibilidad'][] = ['docente_id' => (string) $doc, 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]];
        }
        foreach ($d['asignaciones'] as &$asignacion) {
            if ($asignacion['id_actividad'] === 45) {
                $asignacion['id_docente'] = 62;
            }
        }
        unset($asignacion);
        $d['asignaciones'][] = ['id_asignacion' => 58, 'id_actividad' => 48, 'id_docente' => 63, 'estado' => 'CONFIRMADA'];
        if (! $unLaboratorio) {
            $d['ambientes'][] = ['id_ambiente' => 103, 'codigo' => 'L2', 'nombre' => 'Segundo laboratorio ficticio', 'tipo' => 'LAB', 'capacidad' => 20, 'estado' => 'ACTIVO'];
        }
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $i['datos'] = $d;
        $i['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;

        return [$i, $c];
    }

    public function test_particion_explicita_se_resuelve_y_valida_sin_duplicaciones(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO con OR-Tools.');
        }
        [$i, $c] = $this->fixtureLaboratorioParticionado();
        $adaptador = new AdaptadorPreparacion;
        $entrada = $adaptador->paraMotor($adaptador->convertir($i), $c);
        $resultado = (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        $labs = array_values(array_filter($resultado['sesiones'], fn ($s) => in_array($s['actividad_id'], ['45', '48'], true)));
        self::assertCount(2, $labs);
        self::assertSame($labs[0]['dia'], $labs[1]['dia']);
        self::assertSame($labs[0]['inicio'], $labs[1]['inicio']);
        self::assertNotSame($labs[0]['ambiente_id'], $labs[1]['ambiente_id']);
        $actividades = array_column($entrada['actividades'], null, 'id');
        self::assertArrayNotHasKey('sesion_fisica_id', $actividades['45']);
        self::assertArrayNotHasKey('sesion_fisica_id', $actividades['48']);
        self::assertEqualsCanonicalizing(['FA', 'F2'], array_merge($actividades['45']['estudiantes'], $actividades['48']['estudiantes']));
        $alterada = $entrada;
        $alterada['particiones_academicas']['30']['grupos']['G2'] = ['FA'];
        try {
            (new ValidarResultadoFicticio)->validar($alterada, $resultado);
            self::fail('Debe rechazar padrón duplicado.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        [$i, $c] = $this->fixtureLaboratorioParticionado(true);
        $entrada = $adaptador->paraMotor($adaptador->convertir($i), $c);
        self::assertSame('INFEASIBLE', (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada)['estado']);
        foreach ($i['datos']['disponibilidades'] as &$franja) {
            if ($franja['id_docente'] === 63) {
                $franja['dia_semana'] = 'MARTES';
            }
        }
        unset($franja);
        foreach ($c['disponibilidad'] as &$declaracion) {
            if ($declaracion['docente_id'] === '63') {
                $declaracion['franjas'][0]['dia'] = 'MARTES';
            }
        }
        unset($declaracion);
        $hash = hash('sha256', json_encode($i['datos'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $i['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;
        $entrada = $adaptador->paraMotor($adaptador->convertir($i), $c);
        $distinto = (new EjecutorPythonPruebas($python))->ejecutarExacto($entrada);
        self::assertContains($distinto['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $distinto)['solucion_validada']);
        $labsDistintos = array_values(array_filter($distinto['sesiones'], fn ($s) => in_array($s['actividad_id'], ['45', '48'], true)));
        self::assertNotSame($labsDistintos[0]['dia'], $labsDistintos[1]['dia']);
    }

    public function test_rechaza_particiones_inconsistentes(): void
    {
        foreach (['duplicado', 'omision', 'referencia', 'limite'] as $caso) {
            [$i, $c] = $this->fixtureLaboratorioParticionado();
            match ($caso) {
                'duplicado' => $c['particiones']['30']['grupos'][1]['estudiantes'] = ['FA'],
                'omision' => $c['particiones']['30']['grupos'][1]['estudiantes'] = ['AJENO'],
                'referencia' => $c['particiones']['30']['curso_plan_id'] = '14',
                'limite' => $c['configuracion']['capacidad_subgrupo_autorizada'] = 0,
            };
            try {
                $adaptador = new AdaptadorPreparacion;
                $adaptador->paraMotor($adaptador->convertir($i), $c);
                self::fail('Debe rechazar '.$caso);
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    private function fixtureOcupacion(bool $unAula): array
    {
        [$instantanea, $c] = $this->fixtureMultiple(true, false);
        $d = $instantanea['datos'];
        $d['cursos'][1]['ciclo'] = 4;
        foreach ([2, 3] as $i) {
            $id = 60 + $i;
            $sid = 30 + $i;
            $aid = 40 + $i;
            $alumno = 'F'.$sid;
            $d['docentes'][] = ['id_docente' => $id, 'estado' => 'ACTIVO', 'horas_maximas' => 10];
            $d['declaraciones'][] = ['id_declaracion' => 70 + $i, 'id_periodo' => 1, 'id_docente' => $id, 'estado' => 'PRESENTADA'];
            $d['disponibilidades'][] = ['id_disponibilidad' => 80 + $i, 'id_periodo' => 1, 'id_docente' => $id, 'id_declaracion' => 70 + $i, 'id_bloque' => 90, 'dia_semana' => 'LUNES'];
            $d['asignaciones'][$i]['id_docente'] = $id;
            $c['nombres_docentes'][(string) $id] = 'Docente ficticio '.$id;
            $c['disponibilidad'][] = ['docente_id' => (string) $id, 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]];
            $c['poblacion'][(string) $sid] = [['estudiante_id' => $alumno, 'preferencia' => 'MANANA']];
            $c['participantes_actividad'][(string) $aid] = [$alumno];
        }
        if ($unAula) {
            $d['ambientes'] = [$d['ambientes'][0]];
        } else {
            foreach ([102, 103] as $id) {
                $d['ambientes'][] = ['id_ambiente' => $id, 'codigo' => 'A'.$id, 'nombre' => 'Aula ficticia', 'tipo' => 'AULA', 'capacidad' => 20, 'estado' => 'ACTIVO'];
            }
        }
        $hash = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $instantanea['datos'] = $d;
        $instantanea['huella_sha256'] = $hash;
        $c['huella_instantanea'] = $hash;

        return [$instantanea, $c];
    }

    public function test_rechaza_restricciones_no_soportadas(): void
    {
        [$instantanea, $complementos] = $this->fixture();
        $complementos['restricciones'] = ['estado' => 'CON_RESTRICCIONES'];
        $this->expectException(DomainException::class);
        $adaptador = new AdaptadorPreparacion;
        $adaptador->paraMotor($adaptador->convertir($instantanea), $complementos);
    }

    public function test_rechaza_referencias_y_franjas_incompatibles(): void
    {
        [$instantanea, $complementos] = $this->fixture();
        $adaptador = new AdaptadorPreparacion;
        $preparacion = $adaptador->convertir($instantanea);
        foreach (['referencia', 'franja', 'duracion', 'politica', 'dato_extra'] as $caso) {
            $alterados = $complementos;
            match ($caso) {
                'referencia' => $alterados['participantes_actividad']['40'] = ['OTRO'],
                'franja' => $alterados['disponibilidad'][0]['franjas'][0]['inicio'] = 8,
                'duracion' => $alterados['patrones']['40'] = [90],
                'politica' => $alterados['configuracion']['orden_actividades'] = 'NO_IMPLEMENTADA',
                'dato_extra' => $alterados['patrones']['999'] = [60],
            };
            try {
                $adaptador->paraMotor($preparacion, $alterados);
                self::fail('Debe rechazar '.$caso);
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        $preparacion['datos']['cursos'][0]['nombre'] = 'Dato alterado';
        $this->expectException(DomainException::class);
        $adaptador->paraMotor($preparacion, $complementos);
    }
}
