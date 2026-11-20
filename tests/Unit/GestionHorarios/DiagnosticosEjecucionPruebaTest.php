<?php

namespace Tests\Unit\GestionHorarios;

use App\Modules\GestionHorarios\Engine\EjecutorPythonPruebas;
use App\Modules\GestionHorarios\Engine\ErrorEjecucionPythonPrueba;
use App\Modules\GestionHorarios\Engine\PoliticaReintentoPrueba;
use App\Modules\GestionHorarios\Engine\ValidarResultadoFicticio;
use DomainException;
use Tests\TestCase;

final class DiagnosticosEjecucionPruebaTest extends TestCase
{
    public function test_comunicacion_y_ausencia_de_interprete_tienen_codigos_distintos(): void
    {
        $ejecutor = new EjecutorPythonPruebas('ruta-inexistente-de-prueba');
        try {
            $ejecutor->validarRespuesta('{');
            self::fail('El JSON inválido debe rechazarse.');
        } catch (ErrorEjecucionPythonPrueba $e) {
            self::assertSame('ERROR_COMUNICACION', $e->diagnostico);
        }
        try {
            $ejecutor->ejecutar(['contrato' => 'HORARIOS_MOTOR_V1'], 5);
            self::fail('Un intérprete inexistente debe rechazarse.');
        } catch (ErrorEjecucionPythonPrueba $e) {
            self::assertSame('INTERPRETE_NO_DISPONIBLE', $e->diagnostico);
        }
    }

    public function test_los_estados_del_solver_no_se_convierten_en_fallos_reintentables(): void
    {
        $politica = new PoliticaReintentoPrueba;
        self::assertSame('NO_REINTENTAR', $politica->evaluar('INFEASIBLE'));
        self::assertSame('NO_REINTENTAR', $politica->evaluar('MODEL_INVALID'));
        self::assertSame('NO_REINTENTAR', $politica->evaluar('INPUT_INVALID'));
        self::assertSame('REVISION_MANUAL', $politica->evaluar('UNKNOWN'));
        self::assertSame('REQUIERE_TERMINACION_VERIFICADA', $politica->evaluar(null));
    }

    public function test_estados_sin_solucion_se_conservan_y_no_admiten_sesiones(): void
    {
        $validador = new ValidarResultadoFicticio;
        foreach (['INFEASIBLE', 'UNKNOWN', 'MODEL_INVALID', 'INPUT_INVALID'] as $estado) {
            $resultado = ['contrato' => 'HORARIOS_RESULTADO_V1', 'estado' => $estado, 'sesiones' => []];
            $validacion = $validador->validar([], $resultado);
            self::assertSame($estado, $validacion['estado']);
            self::assertFalse($validacion['solucion_validada']);
            try {
                $resultado['sesiones'] = [['id' => 'no-permitida']];
                $validador->validar([], $resultado);
                self::fail('Un estado sin solución no puede contener sesiones.');
            } catch (DomainException) {
                self::assertTrue(true);
            }
        }
    }
}
