<?php

namespace Tests\Feature\GestionHorarios;

use Tests\Support\GestionHorarios\Engine\AdaptadorMotorFicticio;
use Tests\Support\GestionHorarios\Engine\EjecutorPythonPruebas;
use Tests\Support\GestionHorarios\Engine\ValidarResultadoFicticio;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SesionesFisicasCompartidasTest extends TestCase
{
    private function fuente(): array
    {
        $motor = json_decode(file_get_contents(__DIR__.'/python/entrada_ficticia.json'), true, 512, JSON_THROW_ON_ERROR);
        $a = $motor['secciones'][0];
        $a['plan_id'] = 'PLAN-A';
        $a['curso_plan_id'] = 'CP1';
        $a['periodo'] = 'FICTICIO';
        $a['escuela_id'] = 'E1';
        $a['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $b = $a;
        $b['id'] = 'S2';
        $b['etiqueta'] = 'B';
        foreach ($b['estudiantes'] as $i => &$estudiante) {
            if ($i >= 6) {
                $estudiante['estudiante_id'] = 'B-'.$estudiante['estudiante_id'];
            }
        }
        unset($estudiante);
        $actividad = $motor['actividades'][0];
        $actividad['tipo_academico'] = 'TEORIA';
        $actividad['duraciones_minutos'] = [120];
        unset($actividad['tipo'], $actividad['duraciones'], $actividad['horas']);
        $roster = array_column($a['estudiantes'], 'estudiante_id');
        $rosterB = array_column($b['estudiantes'], 'estudiante_id');
        $actividad['estudiantes'] = array_values(array_unique(array_merge($roster, $rosterB)));
        $ambiente = current(array_filter($motor['ambientes'], fn ($r) => $r['tipo'] === 'AULA'));

        return [
            'contrato' => 'HORARIOS_DATOS_FICTICIOS_V1', 'origen' => 'PRUEBA_FICTICIA', 'periodo' => 'FICTICIO', 'politica' => 'SECCION_Y_COMPARTIDOS_V4', 'tiempo_limite' => 5,
            'ambito_ficticio' => ['escuela_id' => 'E1', 'planes' => ['PLAN-A']], 'configuracion' => $motor['configuracion'],
            'secciones' => [$a, $b], 'actividades' => [$actividad], 'docentes' => [['id' => 'D1', 'nombre' => 'Docente ficticio']],
            'ambientes' => [$ambiente], 'disponibilidades' => [['docente_id' => 'D1', 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]]],
            'bloqueos' => [], 'sesiones' => [], 'ofertas' => [['id' => 'OF1', 'periodo' => 'FICTICIO', 'curso_id' => '901', 'tipo' => 'REGULAR', 'plan_id' => 'PLAN-A']],
            'asociaciones' => [['seccion_id' => 'S1', 'oferta_id' => 'OF1'], ['seccion_id' => 'S2', 'oferta_id' => 'OF1']],
            'sesiones_fisicas_compartidas' => [['id' => 'F1', 'actividad_id' => 'A1', 'tipo' => 'T', 'duracion_minutos' => 120, 'docente_id' => 'D1', 'ambiente_id' => $ambiente['id'], 'participantes' => [['seccion_id' => 'S1', 'estudiantes' => $roster], ['seccion_id' => 'S2', 'estudiantes' => $rosterB]]]],
        ];
    }

    private function ejecutar(array $entrada): array
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere Python aislado con OR-Tools.');
        }

        return (new EjecutorPythonPruebas($python))->ejecutar($entrada, 5);
    }

    private function conConflicto(string $letra, string $recurso): array
    {
        $fuente = $this->fuente();
        $seccion = $fuente['secciones'][0];
        $seccion['id'] = 'OTRA';
        $seccion['curso_id'] = 'CURSO-OTRO';
        $seccion['curso_plan_id'] = 'CP2';
        $seccion['etiqueta'] = $letra;
        if ($recurso !== 'seccion') {
            $seccion['ciclo'] = 3;
        }
        foreach ($seccion['estudiantes'] as &$estudiante) {
            $estudiante['estudiante_id'] = 'OTRO-'.$estudiante['estudiante_id'];
        }
        unset($estudiante);
        $actividad = $fuente['actividades'][0];
        $actividad['id'] = 'A2';
        $actividad['seccion_id'] = 'OTRA';
        $actividad['docente_id'] = $recurso === 'docente' ? 'D1' : 'D2';
        $actividad['estudiantes'] = array_column($seccion['estudiantes'], 'estudiante_id');
        $fuente['secciones'][] = $seccion;
        $fuente['actividades'][] = $actividad;
        $fuente['ofertas'][] = ['id' => 'OF2', 'periodo' => 'FICTICIO', 'curso_id' => 'CURSO-OTRO', 'tipo' => 'REGULAR', 'plan_id' => 'PLAN-A'];
        $fuente['asociaciones'][] = ['seccion_id' => 'OTRA', 'oferta_id' => 'OF2'];
        $fuente['docentes'][] = ['id' => 'D2', 'nombre' => 'Docente ficticio 2'];
        $fuente['disponibilidades'][] = ['docente_id' => 'D2', 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11]]];
        if ($recurso !== 'ambiente') {
            $segundo = $fuente['ambientes'][0];
            $segundo['id'] = $segundo['codigo'] = 'R2';
            $fuente['ambientes'][] = $segundo;
        }

        return $fuente;
    }

    public function test_sesion_compartida_se_ejecuta_y_valida_independientemente(): void
    {
        $preparada = (new AdaptadorMotorFicticio)->convertir($this->fuente());
        self::assertTrue($preparada['compatible'], json_encode($preparada['diagnosticos']));
        $resultado = $this->ejecutar($preparada['entrada']);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertCount(1, $resultado['sesiones']);
        self::assertSame('F1', $resultado['sesiones'][0]['sesion_fisica_id']);
        self::assertSame(['S1', 'S2'], $resultado['sesiones'][0]['secciones_participantes']);
        self::assertCount(18, $preparada['entrada']['actividades'][0]['estudiantes']);
        self::assertSame('LUNES', $resultado['sesiones'][0]['dia']);
        self::assertSame(9, $resultado['sesiones'][0]['inicio']);
        self::assertSame(11, $resultado['sesiones'][0]['fin']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado)['solucion_validada']);
        $resultado['sesiones'][0]['secciones_participantes'] = ['S1'];
        $this->expectException(DomainException::class);
        (new ValidarResultadoFicticio)->validar($preparada['entrada'], $resultado);
    }

    public function test_sabado_compartido_exige_autorizacion_para_ambas_secciones(): void
    {
        $fuente = $this->fuente();
        $fuente['configuracion']['calendario']['dias'] = ['SABADO'];
        $fuente['configuracion']['calendario']['sabados'] = 'SELECCIONADOS';
        $fuente['configuracion']['calendario']['sabados_por_ciclo'] = ['2' => true];
        $fuente['configuracion']['calendario']['sabados_alcances'] = [['ciclo' => 2, 'secciones' => ['A', 'B']]];
        $fuente['disponibilidades'][0]['franjas'] = [['dia' => 'SABADO', 'inicio' => 9, 'fin' => 11]];
        $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($solicitud['compatible'], json_encode($solicitud['diagnosticos']));
        $resultado = $this->ejecutar($solicitud['entrada']);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertSame('SABADO', $resultado['sesiones'][0]['dia']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
        $fuente['configuracion']['calendario']['sabados_alcances'][0]['secciones'] = ['A'];
        $incompatible = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($incompatible['compatible']);
        self::assertSame('INFEASIBLE', $this->ejecutar($incompatible['entrada'])['estado']);
        try {
            (new ValidarResultadoFicticio)->validar($incompatible['entrada'], $resultado);
            self::fail('La sección B no autoriza el sábado.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }

    public function test_conflictos_de_cada_seccion_y_de_recursos(): void
    {
        foreach ([['A', 'seccion'], ['B', 'seccion'], ['A', 'docente'], ['B', 'ambiente']] as [$letra, $recurso]) {
            $solicitud = (new AdaptadorMotorFicticio)->convertir($this->conConflicto($letra, $recurso));
            self::assertTrue($solicitud['compatible'], json_encode($solicitud['diagnosticos']));
            $resultado = $this->ejecutar($solicitud['entrada']);
            self::assertSame('INFEASIBLE', $resultado['estado'], $letra.' '.$recurso.' '.json_encode($resultado, JSON_UNESCAPED_UNICODE));
            self::assertFalse((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
        }
    }

    public function test_capacidad_e_identificadores_invalidos_se_rechazan_antes_del_solver(): void
    {
        $fuente = $this->fuente();
        $fuente['ambientes'][0]['capacidad'] = 1;
        $resultado = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($resultado['compatible']);
        self::assertNull($resultado['entrada']);
        self::assertContains('AMBIENTE_INCOMPATIBLE', array_column($resultado['diagnosticos'], 'codigo'));
        $fuente = $this->fuente();
        unset($fuente['secciones'][1]['escuela_id']);
        $resultado = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($resultado['compatible']);
        self::assertContains('AMBITO_DE_SECCION_INCONSISTENTE', array_column($resultado['diagnosticos'], 'codigo'));
        $fuente = $this->fuente();
        $fuente['sesiones_fisicas_compartidas'][] = $fuente['sesiones_fisicas_compartidas'][0];
        $resultado = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertFalse($resultado['compatible']);
        self::assertNull($resultado['entrada']);
    }

    public function test_validador_rechaza_resultados_y_datos_manipulados(): void
    {
        $solicitud = (new AdaptadorMotorFicticio)->convertir($this->fuente());
        self::assertTrue($solicitud['compatible']);
        $entrada = $solicitud['entrada'];
        $resultado = $this->ejecutar($entrada);
        self::assertTrue((new ValidarResultadoFicticio)->validar($entrada, $resultado)['solucion_validada']);
        $casos = [];
        $r = $resultado;
        $r['sesiones'][] = $r['sesiones'][0];
        $casos[] = [$entrada, $r];
        $r = $resultado;
        $r['sesiones'][0]['secciones_participantes'][] = 'DESCONOCIDA';
        $casos[] = [$entrada, $r];
        $r = $resultado;
        $r['sesiones'][0]['sesion_fisica_id'] = 'OTRA';
        $casos[] = [$entrada, $r];
        $r = $resultado;
        $r['sesiones'][0]['fin']--;
        $casos[] = [$entrada, $r];
        $e = $entrada;
        $e['secciones'][1]['horas']['T']++;
        $casos[] = [$e, $resultado];
        $e = $entrada;
        $e['actividades'][0]['estudiantes_por_seccion']['S2'][0] = 'AJENO';
        $casos[] = [$e, $resultado];
        $e = $entrada;
        $e['ambientes'][0]['capacidad'] = 1;
        $casos[] = [$e, $resultado];
        $e = $entrada;
        $e['actividades'][0]['docente_id'] = 'DESCONOCIDO';
        $casos[] = [$e, $resultado];
        foreach ($casos as [$e, $r]) {
            try {
                (new ValidarResultadoFicticio)->validar($e, $r);
                self::fail('Una sesión alterada debe rechazarse.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_validador_rechaza_cruces_manipulados_en_ambas_secciones(): void
    {
        foreach (['A', 'B'] as $letra) {
            $fuente = $this->conConflicto($letra, 'seccion');
            $fuente['disponibilidades'][1]['franjas'][] = ['dia' => 'LUNES', 'inicio' => 14, 'fin' => 16];
            $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
            self::assertTrue($solicitud['compatible']);
            $resultado = $this->ejecutar($solicitud['entrada']);
            self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
            self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
            foreach ($resultado['sesiones'] as &$sesion) {
                if ($sesion['actividad_id'] === 'A2') {
                    $sesion['inicio'] = 9;
                    $sesion['fin'] = 11;
                }
            }
            unset($sesion);
            try {
                (new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado);
                self::fail('El cruce de la sección '.$letra.' debe rechazarse.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_mismo_codigo_visible_en_planes_distintos_con_identidad_explicita(): void
    {
        $fuente = $this->conConflicto('A', 'seccion');
        $fuente['ambito_ficticio']['planes'][] = 'PLAN-B';
        $fuente['secciones'][0]['codigo'] = $fuente['secciones'][2]['codigo'] = 'MAT101';
        $fuente['secciones'][2]['nombre'] = $fuente['secciones'][0]['nombre'];
        $fuente['secciones'][2]['plan_id'] = 'PLAN-B';
        $fuente['secciones'][2]['ciclo'] = 3;
        $fuente['ofertas'][1]['plan_id'] = 'PLAN-B';
        $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
        self::assertTrue($solicitud['compatible'], json_encode($solicitud['diagnosticos']));
        $resultado = $this->ejecutar($solicitud['entrada']);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE'], json_encode($resultado, JSON_UNESCAPED_UNICODE));
        self::assertCount(2, $resultado['sesiones']);
        self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
        self::assertCount(2, array_unique(array_column($resultado['sesiones'], 'actividad_id')));
    }

    public function test_validador_rechaza_recursos_manipulados_en_sesion_compartida(): void
    {
        foreach (['docente', 'ambiente'] as $recurso) {
            $fuente = $this->conConflicto('A', $recurso);
            $indice = $recurso === 'docente' ? 0 : 1;
            $fuente['disponibilidades'][$indice]['franjas'][] = ['dia' => 'LUNES', 'inicio' => 14, 'fin' => 16];
            $solicitud = (new AdaptadorMotorFicticio)->convertir($fuente);
            self::assertTrue($solicitud['compatible']);
            $resultado = $this->ejecutar($solicitud['entrada']);
            self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
            self::assertTrue((new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado)['solucion_validada']);
            $fisica = current(array_filter($resultado['sesiones'], fn ($s) => $s['actividad_id'] === 'A1'));
            foreach ($resultado['sesiones'] as &$sesion) {
                if ($sesion['actividad_id'] === 'A2') {
                    $sesion['inicio'] = $fisica['inicio'];
                    $sesion['fin'] = $fisica['fin'];
                }
            }
            unset($sesion);
            try {
                (new ValidarResultadoFicticio)->validar($solicitud['entrada'], $resultado);
                self::fail('Debe detectarse el conflicto de '.$recurso.'.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_ambito_alterado_se_rechaza_en_python_y_php(): void
    {
        $solicitud = (new AdaptadorMotorFicticio)->convertir($this->fuente());
        self::assertTrue($solicitud['compatible']);
        $resultadoValido = $this->ejecutar($solicitud['entrada']);
        self::assertContains($resultadoValido['estado'], ['OPTIMAL', 'FEASIBLE']);
        $alterada = $solicitud['entrada'];
        $alterada['secciones'][1]['periodo'] = 'OTRO';
        self::assertSame('INPUT_INVALID', $this->ejecutar($alterada)['estado']);
        $this->expectException(DomainException::class);
        (new ValidarResultadoFicticio)->validar($alterada, $resultadoValido);
    }
}
