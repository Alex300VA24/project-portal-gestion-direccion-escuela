<?php

namespace Tests\Feature\GestionHorarios;

use Tests\Support\GestionHorarios\Engine\AdaptadorMotorFicticio;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use App\Modules\GestionHorarios\Engine\VerificarEvidenciaMotor;
use DomainException;
use PHPUnit\Framework\TestCase;

final class AdaptadorMotorFicticioTest extends TestCase
{
    private function fuente(): array
    {
        $motor = json_decode(file_get_contents(__DIR__.'/python/entrada_ficticia.json'), true, 512, JSON_THROW_ON_ERROR);
        $motor['secciones'][1]['etiqueta'] = 'B';
        $actividades = [];
        foreach ($motor['actividades'] as $actividad) {
            $actividad['tipo_academico'] = ['T' => 'TEORIA', 'P' => 'PRACTICA', 'LAB' => 'LABORATORIO'][$actividad['tipo']];
            $actividad['duraciones_minutos'] = array_map(fn ($h) => $h * 60, $actividad['duraciones']);
            unset($actividad['tipo'], $actividad['duraciones'], $actividad['horas']);
            $actividades[] = $actividad;
        }

        return ['contrato' => 'HORARIOS_DATOS_FICTICIOS_V1', 'origen' => 'PRUEBA_FICTICIA', 'periodo' => 'FICTICIO', 'politica' => 'SECCION_Y_COMPARTIDOS_V4', 'tiempo_limite' => 5, 'configuracion' => $motor['configuracion'], 'secciones' => $motor['secciones'], 'actividades' => $actividades,
            'docentes' => [['id' => 'D1', 'nombre' => 'Docente 1'], ['id' => 'D2', 'nombre' => 'Docente 2']], 'ambientes' => $motor['ambientes'], 'disponibilidades' => $motor['disponibilidades'], 'bloqueos' => [], 'sesiones' => [],
            'ofertas' => [['id' => 'OF1', 'periodo' => 'FICTICIO', 'curso_id' => '901', 'tipo' => 'REGULAR'], ['id' => 'OF2', 'periodo' => 'FICTICIO', 'curso_id' => '902', 'tipo' => 'REGULAR']],
            'asociaciones' => [['seccion_id' => 'S1', 'oferta_id' => 'OF1'], ['seccion_id' => 'S2', 'oferta_id' => 'OF2']]];
    }

    private function motor(): EjecutorPythonPruebas
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Se requiere Python y OR-Tools en entorno aislado.');
        }

        return new EjecutorPythonPruebas($python);
    }

    private function fuenteRepeticion(): array
    {
        $fuente = $this->fuente();
        $fuente['secciones'] = [$fuente['secciones'][0]];
        $fuente['secciones'][0]['repeticion'] = true;
        $fuente['secciones'][0]['etiqueta'] = 'Repetición';
        $fuente['secciones'][0]['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $fuente['actividades'] = [$fuente['actividades'][0]];
        $fuente['actividades'][0]['repeticion'] = true;
        $fuente['actividades'][0]['ciclo_asistente'] = 4;
        $fuente['ofertas'] = [$fuente['ofertas'][0]];
        $fuente['ofertas'][0]['tipo'] = 'REPETICION';
        $fuente['asociaciones'] = [$fuente['asociaciones'][0]];
        $fuente['disponibilidades'] = [
            ['docente_id' => 'D1', 'estado' => 'DECLARADA', 'franjas' => [
                ['dia' => 'SABADO', 'inicio' => 9, 'fin' => 11],
            ]],
        ];
        $fuente['configuracion']['calendario']['dias'] = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'];
        $fuente['configuracion']['calendario']['sabados'] = 'NINGUNO';
        $fuente['configuracion']['calendario']['sabados_por_ciclo'] = ['2' => false, '4' => false];

        return $fuente;
    }

    public function test_repeticion_declarada_sabado_se_adapta_y_valida_con_php(): void
    {
        $preparada = (new AdaptadorMotorFicticio)->convertir($this->fuenteRepeticion());
        self::assertTrue($preparada['compatible'], json_encode($preparada['diagnosticos']));
        $entrada = $preparada['entrada'];
        self::assertTrue($entrada['secciones'][0]['repeticion']);
        self::assertSame(4, $entrada['actividades'][0]['ciclo_asistente']);
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado));
        self::assertSame('SABADO', $resultado['sesiones'][0]['dia']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);

        $alterado = $resultado;
        $alterado['sesiones'][0]['fin']++;
        try {
            (new ValidarResultadoFicticio)->validar($entrada, $alterado);
            self::fail('Una duración alterada de repetición debe rechazarse.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $sinDisponibilidad = $entrada;
        $sinDisponibilidad['disponibilidades'][0]['franjas'] = [];
        try {
            (new ValidarResultadoFicticio)->validar($sinDisponibilidad, $resultado);
            self::fail('El sábado de repetición debe exigir disponibilidad declarada.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }

    public function test_repeticion_rechaza_oferta_incorrecta_ciclo_ausente_y_sesion_compartida(): void
    {
        $fuente = $this->fuenteRepeticion();
        $casos = [];
        $otro = $fuente;
        $otro['ofertas'][0]['tipo'] = 'REGULAR';
        $casos[] = $otro;
        $otro = $fuente;
        unset($otro['actividades'][0]['ciclo_asistente']);
        $casos[] = $otro;
        $otro = $fuente;
        $otro['actividades'][0]['ciclo_asistente'] = 11;
        $casos[] = $otro;
        $otro = $fuente;
        $otro['actividades'][0]['repeticion'] = false;
        $casos[] = $otro;
        $otro = $fuente;
        $otro['secciones'][0]['repeticion'] = false;
        $casos[] = $otro;
        $otro = $fuente;
        $otro['actividades'][0]['secciones_participantes'] = ['S1'];
        $casos[] = $otro;
        foreach ($casos as $caso) {
            self::assertFalse((new AdaptadorMotorFicticio)->convertir($caso)['compatible']);
        }
        $fuente['disponibilidades'][0]['franjas'] = [];
        self::assertTrue((new AdaptadorMotorFicticio)->convertir($fuente)['compatible']);
        $regular = $this->fuente();
        $regular['configuracion']['calendario']['dias'] = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'];
        $regular['disponibilidades'][0]['franjas'] = [['dia' => 'SABADO', 'inicio' => 9, 'fin' => 11]];
        self::assertFalse((new AdaptadorMotorFicticio)->convertir($regular)['compatible']);
    }

    public function test_repeticion_sin_declaracion_solo_tiene_dias_regulares(): void
    {
        $fuente = $this->fuenteRepeticion();
        $fuente['disponibilidades'][0] = ['docente_id' => 'D1', 'estado' => 'SIN_DECLARAR', 'franjas' => []];
        $preparada = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($preparada['compatible']);
        $resultado = $this->motor()->ejecutar($preparada['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertNotSame('SABADO', $resultado['sesiones'][0]['dia']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);
    }

    public function test_repeticion_de_laboratorio_respeta_ambiente_y_subgrupo(): void
    {
        $fuente = $this->fuenteRepeticion();
        $fuente['secciones'][0]['horas'] = ['T' => 0, 'P' => 0, 'LAB' => 2];
        $fuente['actividades'][0] = $this->fuente()['actividades'][1];
        $fuente['actividades'][0]['repeticion'] = true;
        $fuente['actividades'][0]['ciclo_asistente'] = 4;
        $preparada = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($preparada['compatible'], json_encode($preparada['diagnosticos']));
        $resultado = $this->motor()->ejecutar($preparada['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado));
        self::assertSame('SABADO', $resultado['sesiones'][0]['dia']);
        self::assertSame('L1', $resultado['sesiones'][0]['ambiente_id']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);
        $fuente['actividades'][0]['subgrupo'] = null;
        self::assertFalse((new AdaptadorMotorFicticio)->convertir($fuente)['compatible']);
    }

    public function test_repeticion_y_regular_permiten_solapamiento_solo_con_padrones_disjuntos(): void
    {
        $fuente = $this->fuenteRepeticion();
        $regular = $this->fuente();
        $fuente['secciones'][] = $regular['secciones'][1];
        $fuente['secciones'][1]['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $fuente['actividades'][] = $regular['actividades'][2];
        $fuente['ofertas'][] = $regular['ofertas'][1];
        $fuente['asociaciones'][] = $regular['asociaciones'][1];
        $lunes = [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]];
        $fuente['disponibilidades'] = [
            ['docente_id' => 'D1', 'estado' => 'DECLARADA', 'franjas' => $lunes],
            ['docente_id' => 'D2', 'estado' => 'DECLARADA', 'franjas' => $lunes],
        ];
        // La sección de repetición no es la cohorte B del mismo ciclo.
        // Con otros alumnos, ambas clases pueden ocupar aulas distintas.
        $fuente['secciones'][0]['estudiantes'] = array_map(
            fn ($fila) => [...$fila, 'estudiante_id' => 'REP_'.$fila['estudiante_id']],
            $fuente['secciones'][0]['estudiantes']
        );
        $fuente['actividades'][0]['estudiantes'] = array_column($fuente['secciones'][0]['estudiantes'], 'estudiante_id');
        $preparada = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($preparada['compatible'], json_encode($preparada['diagnosticos']));
        $resultado = $this->motor()->ejecutar($preparada['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado));
        self::assertCount(2, $resultado['sesiones']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);

        // Si los mismos alumnos cursan ambas asignaturas, el cruce es HARD.
        $fuente['secciones'][0]['estudiantes'] = $regular['secciones'][0]['estudiantes'];
        $fuente['actividades'][0]['estudiantes'] = $regular['actividades'][0]['estudiantes'];
        $entradaConCruce = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($entradaConCruce['compatible']);
        $sinSolucion = $this->motor()->ejecutar($entradaConCruce['entrada'], 5);
        self::assertSame('INFEASIBLE', $sinSolucion['estado'], json_encode($sinSolucion));
    }

    public function test_adaptador_ejecuta_motor_real_y_valida_resultado(): void
    {
        $preparada = (new AdaptadorMotorFicticio)->convertir($this->fuente());
        self::assertTrue($preparada['compatible']);
        $entrada = $preparada['entrada'];
        self::assertSame('HORARIOS_MOTOR_V1', $entrada['contrato']);
        self::assertSame($preparada['huella_sha256'], hash('sha256', json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertContains($resultado['estado'], ['FEASIBLE', 'OPTIMAL']);
        self::assertArrayHasKey('versiones', $resultado);
        self::assertNotEmpty($resultado['version_motor']);
        (new VerificarEvidenciaMotor)->validar($entrada, $preparada['huella_sha256'], $resultado);
        $alterado = $resultado;
        $alterado['version_motor'] = 'ALTERADA';
        try {
            (new VerificarEvidenciaMotor)->validar($entrada, $preparada['huella_sha256'], $alterado);
            self::fail('Una versión alterada debe rechazarse.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $validacion = (new ValidarResultadoFicticio)->validar($entrada, $resultado);
        self::assertTrue($validacion['solucion_validada']);
        self::assertSame('PERFIL_FICTICIO_PARCIAL', $validacion['alcance']);
        $alteraciones = [];
        $copia = $entrada;
        $copia['actividades'] = [];
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['actividades'][] = $copia['actividades'][0];
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['secciones'][0]['horas']['T']++;
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['bloqueos'] = [['id' => 'NO_VERIFICADO']];
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['ambito_ficticio'] = ['escuela_id' => 'FICTICIA', 'planes' => ['PLAN-A']];
        $copia['secciones'][0]['plan_id'] = 'PLAN-B';
        $alteraciones[] = $copia;
        $lab = array_search('LAB', array_column($entrada['actividades'], 'tipo'), true);
        self::assertNotFalse($lab);
        $copia = $entrada;
        $copia['actividades'][$lab]['subgrupo'] = null;
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['configuracion']['capacidad_subgrupo_autorizada'] = 1;
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['actividades'][$lab]['estudiantes'][] = $copia['actividades'][$lab]['estudiantes'][0];
        $alteraciones[] = $copia;
        $copia = $entrada;
        $copia['actividades'][$lab]['estudiantes'][0] = 'ALUMNO-AJENO';
        $alteraciones[] = $copia;
        $copia = $entrada;
        foreach ($copia['ambientes'] as &$ambiente) {
            if ($ambiente['tipo'] === 'LAB') {
                $ambiente['capacidad'] = 1;
            }
        }
        unset($ambiente);
        $alteraciones[] = $copia;
        foreach ($alteraciones as $alterada) {
            try {
                (new ValidarResultadoFicticio)->validar($alterada, $resultado);
                self::fail('Una solicitud alterada no debe validarse.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        $resultado['sesiones'][0]['fin']++;
        $this->expectException(DomainException::class);
        (new ValidarResultadoFicticio)->validar($entrada, $resultado);
    }

    public function test_infeasible_del_motor_real_no_se_presenta_como_solucion(): void
    {
        $fuente = $this->fuente();
        $fuente['disponibilidades'][0]['franjas'] = [];
        $entrada = (new AdaptadorMotorFicticio)->convertir($fuente)['entrada'];
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertSame('INFEASIBLE', $resultado['estado']);
        (new VerificarEvidenciaMotor)->validar($entrada, hash('sha256', json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $resultado);
        self::assertFalse((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
    }

    public function test_dos_planes_ficticios_comparten_recursos_sin_cruces(): void
    {
        $fuente = $this->fuente();
        $fuente['ambito_ficticio'] = ['escuela_id' => 'ESCUELA-FICTICIA', 'planes' => ['PLAN-A', 'PLAN-B']];
        $fuente['secciones'][0]['plan_id'] = 'PLAN-A';
        $fuente['secciones'][1]['plan_id'] = 'PLAN-B';
        $fuente['ofertas'][0]['plan_id'] = 'PLAN-A';
        $fuente['ofertas'][1]['plan_id'] = 'PLAN-B';
        $fuente['actividades'][2]['docente_id'] = 'D1';
        $preparada = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($preparada['compatible']);
        $entrada = $preparada['entrada'];
        self::assertSame(['PLAN-A', 'PLAN-B'], $entrada['ambito_ficticio']['planes']);
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        $actividades = array_column($entrada['actividades'], null, 'id');
        $secciones = array_column($entrada['secciones'], null, 'id');
        $verificados = 0;
        foreach ($resultado['sesiones'] as $primera) {
            foreach ($resultado['sesiones'] as $segunda) {
                $a = $actividades[$primera['actividad_id']];
                $b = $actividades[$segunda['actividad_id']];
                if ($secciones[$a['seccion_id']]['plan_id'] === $secciones[$b['seccion_id']]['plan_id']) {
                    continue;
                }
                $verificados++;
                $cruzan = $primera['dia'] === $segunda['dia'] && $primera['inicio'] < $segunda['fin'] && $segunda['inicio'] < $primera['fin'];
                if ($cruzan) {
                    self::assertNotSame($a['docente_id'], $b['docente_id']);
                    self::assertNotSame($primera['ambiente_id'], $segunda['ambiente_id']);
                }
            }
        }
        self::assertGreaterThan(0, $verificados);
        $choque = $fuente;
        $choque['actividades'] = [$choque['actividades'][0], $choque['actividades'][2]];
        $choque['secciones'][1]['ciclo'] = 3;
        foreach ($choque['secciones'][1]['estudiantes'] as &$estudiante) {
            $estudiante['estudiante_id'] = 'PLAN-B-'.$estudiante['estudiante_id'];
        }
        unset($estudiante);
        $choque['actividades'][1]['estudiantes'] = array_column($choque['secciones'][1]['estudiantes'], 'estudiante_id');
        foreach ($choque['secciones'] as &$seccion) {
            $seccion['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        }
        unset($seccion);
        foreach ($choque['disponibilidades'] as &$disponibilidad) {
            $disponibilidad['franjas'] = [['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9]];
        }
        unset($disponibilidad);
        $choque['actividades'][1]['docente_id'] = 'D2';
        $solicitud = (new AdaptadorMotorFicticio)->convertir($choque);
        self::assertTrue($solicitud['compatible']);
        self::assertContains($this->motor()->ejecutar($solicitud['entrada'], 5)['estado'], ['OPTIMAL', 'FEASIBLE'], 'El control con dos docentes y dos ambientes debe ser factible.');
        $choque['actividades'][1]['docente_id'] = 'D1';
        $solicitud = (new AdaptadorMotorFicticio)->convertir($choque);
        self::assertTrue($solicitud['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($solicitud['entrada'], 5)['estado'], 'Un docente compartido no puede estar en dos planes a la misma hora.');
        $choque['actividades'][1]['docente_id'] = 'D2';
        $choque['ambientes'] = [$choque['ambientes'][0]];
        $solicitud = (new AdaptadorMotorFicticio)->convertir($choque);
        self::assertTrue($solicitud['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($solicitud['entrada'], 5)['estado'], 'Un ambiente compartido no puede alojar ambas actividades simultáneas.');
        $fuente['ofertas'][1]['plan_id'] = 'PLAN-A';
        $invalida = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($invalida['compatible']);
        self::assertNull($invalida['entrada']);
        self::assertContains('PLAN_OFERTA_SECCION_INCONSISTENTE', array_column($invalida['diagnosticos'], 'codigo'));
        $fuente['ofertas'][1]['plan_id'] = 'PLAN-B';
        $fuente['secciones'][1]['curso_id'] = $fuente['secciones'][0]['curso_id'];
        $fuente['ofertas'][1]['curso_id'] = $fuente['secciones'][0]['curso_id'];
        $ambigua = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($ambigua['compatible']);
        self::assertNull($ambigua['entrada']);
        self::assertContains('CURSO_ENTRE_PLANES_NO_RESUELTO', array_column($ambigua['diagnosticos'], 'codigo'));
    }

    public function test_laboratorios_de_distintos_planes_compiten_por_ambiente(): void
    {
        $fuente = $this->fuente();
        $fuente['ambito_ficticio'] = ['escuela_id' => 'ESCUELA-FICTICIA', 'planes' => ['PLAN-A', 'PLAN-B']];
        foreach ([0, 1] as $indice) {
            $fuente['secciones'][$indice]['plan_id'] = $indice === 0 ? 'PLAN-A' : 'PLAN-B';
            $fuente['ofertas'][$indice]['plan_id'] = $fuente['secciones'][$indice]['plan_id'];
            $fuente['secciones'][$indice]['horas'] = ['T' => 0, 'P' => 0, 'LAB' => 2];
        }
        $fuente['secciones'][1]['ciclo'] = 3;
        foreach ($fuente['secciones'][1]['estudiantes'] as &$estudiante) {
            $estudiante['estudiante_id'] = 'PLAN-B-'.$estudiante['estudiante_id'];
        }
        unset($estudiante);
        $lab1 = $fuente['actividades'][1];
        $lab2 = $lab1;
        $lab2['id'] = 'A-LAB-PLAN-B';
        $lab2['seccion_id'] = 'S2';
        $lab2['docente_id'] = 'D2';
        $lab2['estudiantes'] = array_column($fuente['secciones'][1]['estudiantes'], 'estudiante_id');
        $fuente['actividades'] = [$lab1, $lab2];
        foreach ($fuente['disponibilidades'] as &$disponibilidad) {
            $disponibilidad['franjas'] = [['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9]];
        }
        unset($disponibilidad);
        $ambiente = current(array_filter($fuente['ambientes'], fn ($r) => $r['tipo'] === 'LAB'));
        $otro = $ambiente;
        $otro['id'] = 'LAB-PLAN-B';
        $otro['codigo'] = 'LAB-PLAN-B';
        $fuente['ambientes'][] = $otro;
        $valida = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($valida['compatible']);
        $resultado = $this->motor()->ejecutar($valida['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($valida['entrada'], $resultado)['solucion_validada']);
        self::assertCount(2, $resultado['sesiones']);
        self::assertNotSame($resultado['sesiones'][0]['ambiente_id'], $resultado['sesiones'][1]['ambiente_id']);
        $fuente['ambientes'] = array_values(array_filter($fuente['ambientes'], fn ($r) => $r['id'] !== 'LAB-PLAN-B'));
        $inviable = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($inviable['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($inviable['entrada'], 5)['estado']);
    }

    public function test_cursos_con_aperturas_a_y_b_admiten_cruce_entre_itinerarios(): void
    {
        $fuente = $this->fuente();
        $seccionBase = $fuente['secciones'][0];
        $actividadBase = $fuente['actividades'][0];
        $fuente['secciones'] = $fuente['actividades'] = $fuente['ofertas'] = $fuente['asociaciones'] = $fuente['docentes'] = $fuente['disponibilidades'] = [];
        foreach ([['901', 'A', 7], ['901', 'B', 9], ['902', 'A', 9], ['902', 'B', 7]] as $i => [$curso, $letra, $inicio]) {
            $id = (string) ($i + 1);
            $seccion = $seccionBase;
            $seccion['id'] = 'SEC-'.$id;
            $seccion['curso_id'] = $curso;
            $seccion['etiqueta'] = $letra;
            $seccion['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
            foreach ($seccion['estudiantes'] as &$estudiante) {
                $estudiante['estudiante_id'] = 'S'.$id.'-'.$estudiante['estudiante_id'];
            }
            unset($estudiante);
            $actividad = $actividadBase;
            $actividad['id'] = 'ACT-'.$id;
            $actividad['seccion_id'] = $seccion['id'];
            $actividad['docente_id'] = 'DOC-'.$id;
            $actividad['estudiantes'] = array_column($seccion['estudiantes'], 'estudiante_id');
            $fuente['secciones'][] = $seccion;
            $fuente['actividades'][] = $actividad;
            $fuente['docentes'][] = ['id' => 'DOC-'.$id, 'nombre' => 'Docente ficticio '.$id];
            $fuente['ofertas'][] = ['id' => 'OF-'.$id, 'periodo' => 'FICTICIO', 'curso_id' => $curso, 'tipo' => 'REGULAR'];
            $fuente['asociaciones'][] = ['seccion_id' => $seccion['id'], 'oferta_id' => 'OF-'.$id];
            $fuente['disponibilidades'][] = ['docente_id' => 'DOC-'.$id, 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => $inicio, 'fin' => $inicio + 2]]];
        }
        $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($solicitud['compatible']);
        $resultado = $this->motor()->ejecutar($solicitud['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertCount(4, $resultado['sesiones']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
        $cursoUnico = $fuente;
        foreach (['secciones', 'actividades', 'ofertas', 'asociaciones'] as $coleccion) {
            array_splice($cursoUnico[$coleccion], 1, 1);
        }
        $solicitudUnica = (new AdaptadorMotorFicticio)->convertir($cursoUnico);
        self::assertTrue($solicitudUnica['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($solicitudUnica['entrada'], 5)['estado']);
        $manipulada = $solicitud['entrada'];
        $manipulada['secciones'][1]['etiqueta'] = 'A';
        $this->expectException(DomainException::class);
        (new ValidarResultadoFicticio)->validar($manipulada, $resultado);
    }

    public function test_laboratorios_simultaneos_con_padrones_disjuntos_y_particion_explicita(): void
    {
        $fuente = $this->fuente();
        $fuente['configuracion']['politica_laboratorios'] = 'PARTICION_EXPLICITA_V1';
        $fuente['configuracion']['politica_grupos_laboratorio'] = 'ALTERNATIVAS_SIN_ASIGNACION_V1';
        $fuente['configuracion']['capacidad_subgrupo_autorizada'] = 6;
        $fuente['secciones'] = [$fuente['secciones'][0]];
        $fuente['secciones'][0]['horas'] = ['T' => 0, 'P' => 0, 'LAB' => 2];
        $fuente['ofertas'] = [$fuente['ofertas'][0]];
        $fuente['asociaciones'] = [$fuente['asociaciones'][0]];
        $alumnos = array_column($fuente['secciones'][0]['estudiantes'], 'estudiante_id');
        $g1 = $fuente['actividades'][1];
        $g1['estudiantes'] = array_slice($alumnos, 0, 6);
        $g2 = $g1;
        $g2['id'] = 'LAB-G2';
        $g2['docente_id'] = 'D2';
        $g2['subgrupo'] = 'G2';
        $g2['estudiantes'] = array_slice($alumnos, 6);
        $fuente['actividades'] = [$g1, $g2];
        foreach ($fuente['disponibilidades'] as &$disponibilidad) {
            $disponibilidad['franjas'] = [['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9]];
        }
        unset($disponibilidad);
        $segundo = current(array_filter($fuente['ambientes'], fn ($r) => $r['tipo'] === 'LAB'));
        $segundo['id'] = $segundo['codigo'] = 'LAB-G2';
        $fuente['ambientes'][] = $segundo;
        $entrada = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($entrada['compatible']);
        $resultado = $this->motor()->ejecutar($entrada['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertCount(2, $resultado['sesiones']);
        self::assertSame($resultado['sesiones'][0]['inicio'], $resultado['sesiones'][1]['inicio']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada['entrada'], $resultado)['solucion_validada']);
        $alterado = $resultado;
        $alterado['sesiones'][1]['ambiente_id'] = $alterado['sesiones'][0]['ambiente_id'];
        try {
            (new ValidarResultadoFicticio)->validar($entrada['entrada'], $alterado);
            self::fail('Dos subgrupos no pueden ocupar simultáneamente el mismo laboratorio.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $sinParticion = $entrada['entrada'];
        unset($sinParticion['configuracion']['politica_laboratorios']);
        try {
            (new ValidarResultadoFicticio)->validar($sinParticion, $resultado);
            self::fail('No se permite inferir una partición no declarada.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $docenteCompartido = $fuente;
        $docenteCompartido['actividades'][1]['docente_id'] = 'D1';
        $solicitudInviable = (new AdaptadorMotorFicticio)->convertir($docenteCompartido);
        self::assertTrue($solicitudInviable['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($solicitudInviable['entrada'], 5)['estado']);
        $unAmbiente = $fuente;
        $unAmbiente['ambientes'] = array_values(array_filter($unAmbiente['ambientes'], fn ($r) => $r['id'] !== 'LAB-G2'));
        $solicitudInviable = (new AdaptadorMotorFicticio)->convertir($unAmbiente);
        self::assertTrue($solicitudInviable['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($solicitudInviable['entrada'], 5)['estado']);
        $incompatible = $entrada['entrada'];
        $incompatible['actividades'][1]['docente_id'] = $incompatible['actividades'][0]['docente_id'];
        try {
            (new ValidarResultadoFicticio)->validar($incompatible, $resultado);
            self::fail('Un docente no puede impartir ambos subgrupos simultáneamente.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $incompatible = $entrada['entrada'];
        $incompatible['actividades'][1]['estudiantes'][0] = $incompatible['actividades'][0]['estudiantes'][0];
        $this->expectException(DomainException::class);
        (new ValidarResultadoFicticio)->validar($incompatible, $resultado);
    }

    public function test_motor_rechaza_laboratorio_con_aforo_insuficiente(): void
    {
        $fuente = $this->fuente();
        $fuente['secciones'] = [$fuente['secciones'][0]];
        $fuente['secciones'][0]['horas'] = ['T' => 0, 'P' => 0, 'LAB' => 2];
        $fuente['actividades'] = [$fuente['actividades'][1]];
        $fuente['ofertas'] = [$fuente['ofertas'][0]];
        $fuente['asociaciones'] = [$fuente['asociaciones'][0]];
        foreach ($fuente['ambientes'] as &$ambiente) {
            if ($ambiente['tipo'] === 'LAB') {
                $ambiente['capacidad'] = 1;
            }
        }
        unset($ambiente);
        $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($solicitud['compatible']);
        $resultado = $this->motor()->ejecutar($solicitud['entrada'], 5);
        self::assertSame('INPUT_INVALID', $resultado['estado']);
        self::assertEmpty($resultado['sesiones']);
    }

    public function test_prioridad_docente_ficticia_lexicografica_modifica_la_asignacion(): void
    {
        $fuente = $this->fuente();
        $fuente['tiempo_limite'] = 15;
        $seccionBase = $fuente['secciones'][0];
        $actividadBase = $fuente['actividades'][0];
        $fuente['secciones'] = $fuente['actividades'] = $fuente['ofertas'] = $fuente['asociaciones'] = $fuente['docentes'] = $fuente['disponibilidades'] = [];
        $fuente['ambientes'] = array_values(array_filter($fuente['ambientes'], fn ($r) => $r['tipo'] === 'AULA'));
        $fuente['ambientes'] = [$fuente['ambientes'][0]];
        foreach (['D1', 'D2', 'D3'] as $i => $docente) {
            $seccion = $seccionBase;
            $seccion['id'] = 'SEC-'.$docente;
            $seccion['curso_id'] = 'CURSO-'.$docente;
            $seccion['ciclo'] = 1 + ($i * 2);
            $seccion['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
            foreach ($seccion['estudiantes'] as &$estudiante) {
                $estudiante['estudiante_id'] = $docente.'-'.$estudiante['estudiante_id'];
            }
            unset($estudiante);
            $actividad = $actividadBase;
            $actividad['id'] = 'ACT-'.$docente;
            $actividad['seccion_id'] = $seccion['id'];
            $actividad['docente_id'] = $docente;
            $actividad['estudiantes'] = array_column($seccion['estudiantes'], 'estudiante_id');
            $fuente['secciones'][] = $seccion;
            $fuente['actividades'][] = $actividad;
            $fuente['docentes'][] = ['id' => $docente, 'nombre' => 'Docente ficticio'];
            $fuente['ofertas'][] = ['id' => 'OF-'.$docente, 'periodo' => 'FICTICIO', 'curso_id' => $seccion['curso_id'], 'tipo' => 'REGULAR'];
            $fuente['asociaciones'][] = ['seccion_id' => $seccion['id'], 'oferta_id' => 'OF-'.$docente];
            $fuente['disponibilidades'][] = ['docente_id' => $docente, 'estado' => 'DECLARADA', 'preferencia' => $i === 2 ? 'TARDE' : 'MANANA', 'franjas' => [
                ['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9], ['dia' => 'LUNES', 'inicio' => 14, 'fin' => 18],
            ]];
        }
        foreach ([['D1', 'D2', 'D3'], ['D2', 'D1', 'D3']] as $orden) {
            $fuente['prioridad_docente'] = ['politica' => 'SOFT_P01_LEXICOGRAFICA_V1', 'version' => 1, 'orden' => array_map(fn ($id, $i) => ['docente_id' => $id, 'posicion' => $i + 1], $orden, array_keys($orden))];
            $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
            self::assertTrue($solicitud['compatible']);
            $resultado = $this->motor()->ejecutar($solicitud['entrada'], 15);
            self::assertSame('OPTIMAL', $resultado['estado'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
            self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
            (new VerificarEvidenciaMotor)->validar($solicitud['entrada'], $solicitud['huella_sha256'], $resultado);
            self::assertSame('SOFT_P01_LEXICOGRAFICA_V1', $resultado['prioridad_docente']['politica']);
            self::assertTrue($resultado['prioridad_docente']['jerarquia_certificada']);
            $inicios = array_column($resultado['sesiones'], 'inicio', 'actividad_id');
            self::assertSame(7, $inicios['ACT-'.$orden[0]]);
            self::assertGreaterThanOrEqual(14, $inicios['ACT-'.$orden[1]]);
            self::assertSame(0, $resultado['prioridad_docente']['calidad_final'][$orden[0]]['preferencias']);
            self::assertSame(2, $resultado['prioridad_docente']['calidad_final'][$orden[1]]['preferencias']);
        }
        $fuente['prioridad_docente']['orden'][1]['docente_id'] = $fuente['prioridad_docente']['orden'][0]['docente_id'];
        self::assertFalse((new AdaptadorMotorFicticio)->convertir($fuente)['compatible']);
    }

    public function test_preferencia_docente_soft_elige_entre_dos_turnos_factibles(): void
    {
        $fuente = $this->fuente();
        $fuente['secciones'] = [$fuente['secciones'][0]];
        $fuente['secciones'][0]['ciclo'] = 1;
        $fuente['secciones'][0]['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $fuente['actividades'] = [$fuente['actividades'][0]];
        $fuente['ofertas'] = [$fuente['ofertas'][0]];
        $fuente['asociaciones'] = [$fuente['asociaciones'][0]];
        $fuente['disponibilidades'] = [$fuente['disponibilidades'][0]];
        $fuente['disponibilidades'][0]['franjas'] = [
            ['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9],
            ['dia' => 'LUNES', 'inicio' => 14, 'fin' => 16],
        ];
        foreach (['MANANA', 'TARDE'] as $preferencia) {
            $fuente['disponibilidades'][0]['preferencia'] = $preferencia;
            $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
            self::assertTrue($solicitud['compatible']);
            $resultado = $this->motor()->ejecutar($solicitud['entrada'], 5);
            self::assertSame('OPTIMAL', $resultado['estado']);
            self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
            self::assertSame(0, $resultado['horas_estudiante_fuera_preferencia']);
            self::assertCount(1, $resultado['sesiones']);
            self::assertSame($preferencia === 'MANANA' ? 7 : 14, $resultado['sesiones'][0]['inicio']);
        }
    }

    public function test_sabado_regular_explicito_se_resuelve_y_valida_independientemente(): void
    {
        $fuente = $this->fuente();
        $fuente['secciones'] = [$fuente['secciones'][0]];
        $fuente['secciones'][0]['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $fuente['actividades'] = [$fuente['actividades'][0]];
        $fuente['ofertas'] = [$fuente['ofertas'][0]];
        $fuente['asociaciones'] = [$fuente['asociaciones'][0]];
        $fuente['disponibilidades'] = [$fuente['disponibilidades'][0]];
        $fuente['disponibilidades'][0]['franjas'] = [['dia' => 'SABADO', 'inicio' => 9, 'fin' => 11]];
        $fuente['configuracion']['calendario']['dias'] = ['SABADO'];
        $fuente['configuracion']['calendario']['sabados'] = 'SELECCIONADOS';
        $fuente['configuracion']['calendario']['sabados_por_ciclo'] = ['2' => true];
        $fuente['configuracion']['calendario']['sabados_alcances'] = [['ciclo' => 2, 'secciones' => ['A']]];
        $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($solicitud['compatible'], json_encode($solicitud['diagnosticos']));
        $resultado = $this->motor()->ejecutar($solicitud['entrada'], 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertSame('SABADO', $resultado['sesiones'][0]['dia']);
        self::assertSame(9, $resultado['sesiones'][0]['inicio']);
        $validador = new ValidarResultadoFicticio;
        self::assertTrue($validador->validar($solicitud['entrada'], $resultado)['solucion_validada']);
        $variantes = [];
        $sinDia = $solicitud['entrada'];
        $sinDia['configuracion']['calendario']['dias'] = ['LUNES'];
        $variantes[] = $sinDia;
        $sinCiclo = $solicitud['entrada'];
        $sinCiclo['configuracion']['calendario']['sabados_por_ciclo']['2'] = false;
        $variantes[] = $sinCiclo;
        $sinSeccion = $solicitud['entrada'];
        $sinSeccion['configuracion']['calendario']['sabados_alcances'][0]['secciones'] = ['B'];
        $variantes[] = $sinSeccion;
        $sinVentana = $solicitud['entrada'];
        $sinVentana['disponibilidades'][0]['franjas'] = [];
        $variantes[] = $sinVentana;
        $repeticion = $solicitud['entrada'];
        $repeticion['actividades'][0]['repeticion'] = true;
        $variantes[] = $repeticion;
        $seccionRepeticion = $solicitud['entrada'];
        $seccionRepeticion['secciones'][0]['repeticion'] = true;
        $variantes[] = $seccionRepeticion;
        foreach ($variantes as $variante) {
            try {
                $validador->validar($variante, $resultado);
                self::fail('La solución alterada debe ser rechazada.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
        $flexible = $fuente;
        $flexible['disponibilidades'][0]['estado'] = 'SIN_DECLARAR';
        $flexible['disponibilidades'][0]['franjas'] = [];
        $sinDeclaracion = (new AdaptadorMotorFicticio)->convertir($flexible);
        self::assertTrue($sinDeclaracion['compatible']);
        $resultadoFlexible = $this->motor()->ejecutar($sinDeclaracion['entrada'], 5);
        self::assertContains($resultadoFlexible['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue($validador->validar($sinDeclaracion['entrada'], $resultadoFlexible)['solucion_validada']);
        $flexible['disponibilidades'][0]['estado'] = 'DECLARADA';
        $declaracionVacia = (new AdaptadorMotorFicticio)->convertir($flexible);
        self::assertTrue($declaracionVacia['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($declaracionVacia['entrada'], 5)['estado']);
        $fuente['configuracion']['calendario']['sabados_por_ciclo']['2'] = false;
        $prohibida = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($prohibida['compatible']);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($prohibida['entrada'], 5)['estado']);
        $fuente['configuracion']['calendario']['sabados_por_ciclo']['2'] = 'false';
        $invalida = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($invalida['compatible']);
        self::assertContains('SABADO_CONFIGURACION_INVALIDA', array_column($invalida['diagnosticos'], 'codigo'));
        $fuente['configuracion']['calendario']['sabados_por_ciclo']['2'] = true;
        $fuente['secciones'][0]['repeticion'] = true;
        $invalida = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($invalida['compatible']);
        self::assertContains('ASOCIACION_INVALIDA', array_column($invalida['diagnosticos'], 'codigo'));
    }

    public function test_datos_faltantes_no_producen_entrada_ejecutable(): void
    {
        $casos = [];
        $x = $this->fuente();
        unset($x['configuracion']['calendario']);
        $casos[] = $x;
        $x = $this->fuente();
        $x['secciones'][0]['estudiantes'] = [];
        $casos[] = $x;
        $x = $this->fuente();
        $x['actividades'][0]['docente_id'] = 'INEXISTENTE';
        $casos[] = $x;
        $x = $this->fuente();
        $x['actividades'][0]['duraciones_minutos'] = [90];
        $casos[] = $x;
        $x = $this->fuente();
        $x['ofertas'][0]['periodo'] = 'OTRO';
        $casos[] = $x;
        $x = $this->fuente();
        $x['politica'] = 'NO_APROBADA';
        $casos[] = $x;
        $x = $this->fuente();
        $x['disponibilidades'] = [];
        $casos[] = $x;
        $x = $this->fuente();
        $x['actividades'][0]['estudiantes'] = ['NO_REGISTRADO'];
        $casos[] = $x;
        $x = $this->fuente();
        $x['actividades'][0]['secciones_participantes'] = ['S1', 'S2'];
        $casos[] = $x;
        $x = $this->fuente();
        $x['prioridad_docente'] = ['politica' => 'SOFT_P01_LEXICOGRAFICA_V1', 'version' => 1, 'orden' => [['docente_id' => 'D-INEXISTENTE', 'posicion' => 1]]];
        $casos[] = $x;
        foreach ($casos as $caso) {
            $r = (new AdaptadorMotorFicticio)->convertir($caso);
            self::assertFalse($r['compatible']);
            self::assertNull($r['entrada']);
            self::assertNotEmpty($r['diagnosticos']);
        }
    }
}
