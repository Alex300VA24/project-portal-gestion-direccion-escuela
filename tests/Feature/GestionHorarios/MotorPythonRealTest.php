<?php

namespace Tests\Feature\GestionHorarios;

use App\Modules\GestionHorarios\Engine\EjecutorPythonPruebas;
use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

final class MotorPythonRealTest extends TestCase
{
    public function test_puente_rechaza_operaciones_de_exportacion(): void
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere Python aislado.');
        }
        $directorio = dirname(__DIR__, 3).'/app/Modules/GestionHorarios/Engine/python';
        foreach (['EXPORTAR_ACADEMICO', 'EXPORTAR_HORARIO'] as $operacion) {
            $proceso = new Process([$python, '-B', '-m', 'scripts.motor_aplicacion'], $directorio, ['PYTHONPATH' => $directorio]);
            $proceso->setInput(json_encode(['operacion' => $operacion], JSON_THROW_ON_ERROR));
            $proceso->run();
            $respuesta = json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('INPUT_INVALID', $respuesta['estado']);
        }
    }

    private function motor(): EjecutorPythonPruebas
    {
        $python = getenv('HORARIOS_PYTHON_AISLADO');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere HORARIOS_PYTHON_AISLADO y OR-Tools en un entorno aislado.');
        }

        return new EjecutorPythonPruebas($python);
    }

    private function entrada(): array
    {
        return json_decode(file_get_contents(__DIR__.'/python/entrada_ficticia.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_php_ejecuta_motor_real_y_verifica_sesiones(): void
    {
        $entrada = $this->entrada();
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertTrue($resultado['validacion_operativa']['valida']);
        self::assertCount(4, $resultado['sesiones']);
        $actividades = array_column($entrada['actividades'], null, 'id');
        $ambientes = array_column($entrada['ambientes'], null, 'id');
        $vistas = [];
        foreach ($resultado['sesiones'] as $sesion) {
            self::assertArrayHasKey($sesion['actividad_id'], $actividades);
            self::assertArrayHasKey($sesion['ambiente_id'], $ambientes);
            $actividad = $actividades[$sesion['actividad_id']];
            self::assertSame(2, $sesion['fin'] - $sesion['inicio']);
            self::assertGreaterThanOrEqual(count($actividad['estudiantes']), $ambientes[$sesion['ambiente_id']]['capacidad']);
            self::assertContains($sesion['dia'], ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES']);
            self::assertGreaterThanOrEqual(7, $sesion['inicio']);
            self::assertLessThanOrEqual(20, $sesion['fin']);
            if ($actividad['tipo'] === 'LAB') {
                self::assertSame('LAB', $ambientes[$sesion['ambiente_id']]['tipo']);
            }
            foreach ($vistas as $otra) {
                if ($otra['dia'] === $sesion['dia'] && $otra['inicio'] < $sesion['fin'] && $sesion['inicio'] < $otra['fin']) {
                    $anterior = $actividades[$otra['actividad_id']];
                    self::assertNotSame($anterior['docente_id'], $actividad['docente_id']);
                    self::assertNotSame($anterior['seccion_id'], $actividad['seccion_id']);
                    self::assertNotSame($otra['ambiente_id'], $sesion['ambiente_id']);
                }
            }
            $vistas[] = $sesion;
        }
        self::assertEqualsCanonicalizing(array_keys($actividades), array_column($resultado['sesiones'], 'actividad_id'));
    }

    public function test_declaracion_vacia_es_inviable(): void
    {
        $entrada = $this->entrada();
        $entrada['disponibilidades'][0]['franjas'] = [];
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($entrada, 5)['estado']);
    }

    public function test_sin_declaracion_no_equivale_a_declaracion_vacia(): void
    {
        $entrada = $this->entrada();
        $entrada['disponibilidades'] = [];
        self::assertContains($this->motor()->ejecutar($entrada, 5)['estado'], ['OPTIMAL', 'FEASIBLE']);
    }

    public function test_disponibilidad_limitada_produce_inviabilidad_si_no_cubre_la_carga(): void
    {
        $entrada = $this->entrada();
        $entrada['disponibilidades'][0]['franjas'] = [['dia' => 'LUNES', 'inicio' => 7, 'fin' => 9]];
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($entrada, 5)['estado']);
    }

    public function test_sabado_declarado_para_repeticion_no_habilita_sabado_regular(): void
    {
        $entrada = $this->entrada();
        $entrada['secciones'] = [$entrada['secciones'][0]];
        $entrada['secciones'][0]['horas'] = ['T' => 2, 'P' => 0, 'LAB' => 0];
        $entrada['actividades'] = [$entrada['actividades'][0]];
        $entrada['disponibilidades'] = [['docente_id' => 'D1', 'estado' => 'DECLARADA', 'franjas' => [['dia' => 'SABADO', 'inicio' => 9, 'fin' => 11]]]];
        $entrada['configuracion']['calendario']['dias'] = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES'];
        $entrada['configuracion']['calendario']['sabados'] = 'NINGUNO';
        $entrada['configuracion']['calendario']['sabados_alcances'] = [];
        $entrada['configuracion']['calendario']['sabados_por_ciclo'] = array_fill_keys(range(1, 10), false);
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($entrada, 5)['estado']);
        $entrada['secciones'][0]['repeticion'] = true;
        $entrada['secciones'][0]['etiqueta'] = 'Repetición';
        $entrada['actividades'][0]['repeticion'] = true;
        $entrada['actividades'][0]['ciclo_asistente'] = 4;
        $resultado = $this->motor()->ejecutar($entrada, 5);
        self::assertContains($resultado['estado'], ['OPTIMAL', 'FEASIBLE']);
        self::assertSame('SABADO', $resultado['sesiones'][0]['dia']);
        $sinVentana = $entrada;
        $sinVentana['disponibilidades'][0]['franjas'] = [];
        self::assertSame('INFEASIBLE', $this->motor()->ejecutar($sinVentana, 5)['estado']);
        $sinSabadoDeclarado = $entrada;

        $sinSabadoDeclarado['disponibilidades'][0]['franjas'] = [
            ['dia' => 'LUNES', 'inicio' => 9, 'fin' => 11],
        ];

        $resultadoSinSabado = $this->motor()->ejecutar($sinSabadoDeclarado, 5);

        self::assertContains(
            $resultadoSinSabado['estado'],
            ['OPTIMAL', 'FEASIBLE']
        );

        self::assertSame(
            'LUNES',
            $resultadoSinSabado['sesiones'][0]['dia']
        );
    }

    public function test_rechaza_contrato_institucional_parcial(): void
    {
        $this->expectException(DomainException::class);
        $this->motor()->ejecutar(['contrato' => 'HORARIOS_PREPARACION_V1'], 1);
    }

    public function test_python_sin_ortools_reporta_fallo_de_proceso(): void
    {
        $python = getenv('HORARIOS_PYTHON_SIN_ORTOOLS');
        if (! is_string($python) || ! is_file($python)) {
            $this->markTestSkipped('Requiere un intérprete de prueba sin OR-Tools.');
        }
        $comprobar = new Process([$python, '-I', '-c', 'import importlib.util,sys;sys.exit(0 if importlib.util.find_spec("ortools") is None else 1)']);
        $comprobar->run();
        if (! $comprobar->isSuccessful()) {
            $this->markTestSkipped('El intérprete indicado ya dispone de OR-Tools.');
        }
        $this->expectException(RuntimeException::class);
        (new EjecutorPythonPruebas($python))->ejecutar($this->entrada(), 1);
    }

    public function test_respuestas_invalidas_del_ejecutor_se_rechazan(): void
    {
        $ejecutor = new EjecutorPythonPruebas('');
        foreach (['{"contrato":', '{"contrato":"HORARIOS_RESULTADO_V1","estado":"OPTIMAL"}', '[]'] as $salida) {
            try {
                $ejecutor->validarRespuesta($salida);
                self::fail('La respuesta inválida no debe aceptarse.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }
}
