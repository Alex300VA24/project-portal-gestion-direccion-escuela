<?php

namespace Tests\Feature\SeguimientoEgresado\Indicadores;

use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador;
use App\Modules\SeguimientoEgresado\Services\Indicadores\SeriesDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Cada prueba usa registros con un resultado calculado a mano. */
class CalculadoraIndicadoresTest extends TestCase
{
    use FabricaEmpresasIndicadores;
    use UsaBaseDeDatos;

    private CalculadoraIndicadores $calculadora;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculadora = app(CalculadoraIndicadores::class);
    }

    public function test_i1_usa_ingresantes_como_denominador_y_solo_el_estado_vigente(): void
    {
        $cohorte = $this->cohorte(10);
        $this->egresado($cohorte, 'EGRESADO');
        $this->egresado($cohorte, 'BACHILLER');
        $this->egresado($cohorte, 'TITULADO');
        $this->egresado($cohorte, null); // sin estado académico: aún no egresa

        $i1 = $this->calculadora->i1(new FiltroIndicadores);

        $this->assertSame(30.0, $i1->valor);
        $this->assertSame(3, $i1->numerador);
        $this->assertSame(10, $i1->denominador);
        $this->assertSame('%', $i1->unidad);
        $this->assertSame(ResultadoIndicador::CALCULADO, $i1->estado);
    }

    public function test_i1_filtra_por_cohorte_y_suma_las_demas(): void
    {
        $a = $this->cohorte(10);
        $b = $this->cohorte(20);
        $this->egresado($a);
        $this->egresado($b);
        $this->egresado($b);

        $this->assertSame(10.0, $this->calculadora->i1(new FiltroIndicadores($a))->valor);
        $this->assertSame(10.0, $this->calculadora->i1(new FiltroIndicadores($b))->valor);
        $total = $this->calculadora->i1(new FiltroIndicadores);
        $this->assertSame(3, $total->numerador);
        $this->assertSame(30, $total->denominador);
    }

    public function test_i1_sin_ingresantes_es_sin_datos_y_no_cero(): void
    {
        $this->cohorte(0);

        $i1 = $this->calculadora->i1(new FiltroIndicadores);

        $this->assertSame(ResultadoIndicador::SIN_DATOS, $i1->estado);
        $this->assertNull($i1->valor);
    }

    public function test_i1_con_horizonte_toma_cohortes_con_egreso_en_la_ventana(): void
    {
        $corte = CarbonImmutable::create(2026, 6, 30);
        $dentro = $this->cohorte(10, 2020); // egreso esperado 2025
        $fuera = $this->cohorte(10, 2010);  // egreso esperado 2015

        $ids = array_column($this->calculadora->i1PorCohorte(new FiltroIndicadores(null, 3, $corte)), 'id_cohorte');

        $this->assertSame([$dentro], $ids);
        $this->assertNotContains($fuera, $ids);
    }

    public function test_i2_excluye_a_quien_no_respondio_y_muestra_cobertura(): void
    {
        $cohorte = $this->cohorte();
        $campania = $this->campania(['tipo' => 'EGRESADOS']);

        $this->responder($this->invitacionSeguimiento($campania, $this->egresado($cohorte)));
        $this->responder($this->invitacionSeguimiento($campania, $this->egresado($cohorte)));
        $this->responder($this->invitacionSeguimiento($campania, $this->egresado($cohorte)), 'TRABAJA', false);
        $this->responder($this->invitacionSeguimiento($campania, $this->egresado($cohorte)), 'NO_TRABAJA', null);
        $this->invitacionSeguimiento($campania, $this->egresado($cohorte)); // no respondió: no es desempleo

        $i2 = $this->calculadora->i2(new FiltroIndicadores);

        $this->assertSame(50.0, $i2->valor);     // 2 de 4 encuestados
        $this->assertSame(2, $i2->numerador);
        $this->assertSame(4, $i2->denominador);
        $this->assertSame(5, $i2->poblacionElegible);
        $this->assertSame(80.0, $i2->cobertura); // 4 de 5 invitados
    }

    public function test_una_persona_cuenta_una_vez_con_su_respuesta_mas_reciente(): void
    {
        $egresado = $this->egresado($this->cohorte());
        $antes = $this->campania(['tipo' => 'EGRESADOS', 'periodo' => '2025-II', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2025-11-30', 'estado' => 'CERRADA']);
        $ahora = $this->campania(['tipo' => 'EGRESADOS']);
        $this->responder($this->invitacionSeguimiento($antes, $egresado), 'NO_TRABAJA', null, 'INSATISFECHO', ['fecha_respuesta' => '2025-10-01 10:00:00']);
        $this->responder($this->invitacionSeguimiento($ahora, $egresado));

        $i2 = $this->calculadora->i2(new FiltroIndicadores);

        $this->assertSame(1, $i2->denominador);
        $this->assertSame(100.0, $i2->valor);
        $this->assertSame(0.0, $this->calculadora->i2(new FiltroIndicadores(null, null, CarbonImmutable::create(2025, 12, 31)))->valor);
    }

    public function test_el_historial_se_conserva_en_la_tendencia(): void
    {
        $egresado = $this->egresado($this->cohorte());
        $antes = $this->campania(['tipo' => 'EGRESADOS', 'periodo' => '2025-II', 'fecha_inicio' => '2025-09-01', 'fecha_fin' => '2025-11-30', 'estado' => 'CERRADA']);
        $ahora = $this->campania(['tipo' => 'EGRESADOS']);
        $this->responder($this->invitacionSeguimiento($antes, $egresado), 'NO_TRABAJA', null, 'INSATISFECHO', ['fecha_respuesta' => '2025-10-01 10:00:00']);
        $this->responder($this->invitacionSeguimiento($ahora, $egresado));

        $tendencia = app(SeriesDashboard::class)->tendencia(new FiltroIndicadores);

        $this->assertSame(['2025-II', '2026-II'], array_column($tendencia, 'periodo'));
        $this->assertSame([0.0, 100.0], array_column($tendencia, 'i2'));
        $this->assertSame([0.0, 100.0], array_column($tendencia, 'i4'));
    }

    public function test_i2_e_i4_distinguen_sin_datos_de_sin_medicion(): void
    {
        $this->assertSame(ResultadoIndicador::SIN_DATOS, $this->calculadora->i2(new FiltroIndicadores)->estado);

        $this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $this->egresado($this->cohorte()));

        foreach ([$this->calculadora->i2(new FiltroIndicadores), $this->calculadora->i4(new FiltroIndicadores)] as $resultado) {
            $this->assertSame(ResultadoIndicador::SIN_MEDICION, $resultado->estado);
            $this->assertNull($resultado->valor);
            $this->assertSame(1, $resultado->poblacionElegible);
        }
    }

    public function test_cero_medido_se_conserva_como_cero(): void
    {
        $this->responder($this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $this->egresado($this->cohorte())), 'NO_TRABAJA', null, 'NEUTRAL');

        $i2 = $this->calculadora->i2(new FiltroIndicadores);

        $this->assertSame(0.0, $i2->valor);
        $this->assertSame(ResultadoIndicador::CALCULADO, $i2->estado);
    }

    public function test_el_horizonte_y_la_cohorte_filtran_las_encuestas(): void
    {
        $a = $this->cohorte();
        $b = $this->cohorte();
        $tres = $this->campania(['tipo' => 'EGRESADOS', 'horizonte_anios' => 3]);
        $cinco = $this->campania(['tipo' => 'EGRESADOS', 'horizonte_anios' => 5]);
        $this->responder($this->invitacionSeguimiento($tres, $this->egresado($a)));
        $this->responder($this->invitacionSeguimiento($cinco, $this->egresado($b)), 'TRABAJA', false);

        $this->assertSame(100.0, $this->calculadora->i2(new FiltroIndicadores(null, 3))->valor);
        $this->assertSame(0.0, $this->calculadora->i2(new FiltroIndicadores(null, 5))->valor);
        $this->assertSame(ResultadoIndicador::SIN_DATOS, $this->calculadora->i2(new FiltroIndicadores(null, 10))->estado);
        $this->assertSame(0.0, $this->calculadora->i2(new FiltroIndicadores($b))->valor);
    }

    public function test_la_fecha_de_corte_excluye_respuestas_posteriores(): void
    {
        $campania = $this->campania(['tipo' => 'EGRESADOS', 'fecha_inicio' => '2025-01-01']);
        $this->responder($this->invitacionSeguimiento($campania, $this->egresado($this->cohorte())), cambios: ['fecha_respuesta' => '2025-06-15 12:00:00']);

        $this->assertSame(1, $this->calculadora->i2(new FiltroIndicadores(null, null, CarbonImmutable::create(2025, 6, 15)))->n);
        $this->assertSame(ResultadoIndicador::SIN_MEDICION, $this->calculadora->i2(new FiltroIndicadores(null, null, CarbonImmutable::create(2025, 6, 14)))->estado);
    }

    public function test_i3_divide_entre_el_total_de_objetivos_del_programa(): void
    {
        $plan = $this->plan();
        $cohorte = $this->cohorte(10, 2018, $plan);
        [$a, $b, $c] = [$this->objetivo($plan), $this->objetivo($plan), $this->objetivo($plan)];
        $this->objetivo($plan); // cuarto objetivo sin evaluar: cuenta en el denominador
        $this->objetivo($plan, vigente: false); // no vigente: no cuenta
        $this->objetivo($this->plan()); // de otro plan: no cuenta

        $this->evaluarObjetivo($a, $cohorte, 'MUY_BUENO');
        $this->evaluarObjetivo($b, $cohorte, 'BUENO');
        $this->evaluarObjetivo($c, $cohorte, 'REGULAR');

        $i3 = $this->calculadora->i3(new FiltroIndicadores);

        $this->assertSame(50.0, $i3->valor);
        $this->assertSame(2, $i3->numerador);
        $this->assertSame(4, $i3->denominador);
        $this->assertSame(3, $i3->n);
    }

    public function test_i3_ignora_borradores_y_sin_evaluaciones_es_sin_medicion(): void
    {
        $plan = $this->plan();
        $this->evaluarObjetivo($this->objetivo($plan), $this->cohorte(10, 2018, $plan), 'MUY_BUENO', 'BORRADOR');

        $i3 = $this->calculadora->i3(new FiltroIndicadores);

        $this->assertSame(ResultadoIndicador::SIN_MEDICION, $i3->estado);
        $this->assertNull($i3->valor);
    }

    public function test_i3_usa_la_ultima_evaluacion_de_la_cohorte(): void
    {
        $plan = $this->plan();
        $cohorte = $this->cohorte(10, 2018, $plan);
        $objetivo = $this->objetivo($plan);
        $this->evaluarObjetivo($objetivo, $cohorte, 'REGULAR', periodo: '2024-II', fecha: '2024-12-01');
        $this->evaluarObjetivo($objetivo, $cohorte, 'BUENO', periodo: '2025-II', fecha: '2025-12-01');

        $this->assertSame(100.0, $this->calculadora->i3(new FiltroIndicadores)->valor);
        $this->assertSame(0.0, $this->calculadora->i3(new FiltroIndicadores(null, null, CarbonImmutable::create(2025, 1, 1)))->valor);
    }

    public function test_i4_cuenta_satisfechos_sobre_valoraciones(): void
    {
        $campania = $this->campania(['tipo' => 'EGRESADOS']);
        $cohorte = $this->cohorte();
        foreach (['MUY_SATISFECHO', 'SATISFECHO', 'NEUTRAL', 'INSATISFECHO'] as $nivel) {
            $this->responder($this->invitacionSeguimiento($campania, $this->egresado($cohorte)), satisfaccion: $nivel);
        }

        $i4 = $this->calculadora->i4(new FiltroIndicadores);

        $this->assertSame(50.0, $i4->valor);
        $this->assertSame(2, $i4->numerador);
        $this->assertSame(4, $i4->denominador);
    }

    public function test_i5_solo_cuenta_desempeno_valido(): void
    {
        $campania = $this->campania();
        $cohorte = $this->cohorte();
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($cohorte))['id'], 'SATISFECHO');
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($cohorte))['id'], 'MUY_SATISFECHO');
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($cohorte))['id'], 'NEUTRAL');
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($cohorte))['id'], 'INSATISFECHO');
        $this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($cohorte)); // pendiente
        $this->respuestaEmpresa($this->invitacionEmpresa($campania)['id'], null); // mercado

        $i5 = $this->calculadora->i5(new FiltroIndicadores);

        $this->assertSame(50.0, $i5->valor);
        $this->assertSame(2, $i5->numerador);
        $this->assertSame(4, $i5->denominador);
        $this->assertSame(5, $i5->poblacionElegible);
        $this->assertSame(80.0, $i5->cobertura);
    }

    public function test_i6_es_una_cantidad_absoluta_con_evidencia(): void
    {
        $this->mejora();
        $this->mejora(['fecha_implementacion' => '2025-03-01']);
        $this->mejora(['estado' => 'APROBADA']);
        $this->mejora(['origen_seguimiento' => false]);
        $this->mejora(['origen_evidencia' => null]); // sin evidencia

        $i6 = $this->calculadora->i6(new FiltroIndicadores);

        $this->assertSame(2.0, $i6->valor);
        $this->assertSame('mejoras', $i6->unidad);
        $this->assertNull($i6->denominador);
        $this->assertNull($i6->cobertura);
        $this->assertSame([2024 => 1, 2025 => 1], $this->calculadora->i6PorAnio(new FiltroIndicadores));
    }

    public function test_i6_con_registros_pero_ninguna_implementada_es_cero_medido(): void
    {
        $this->assertSame(ResultadoIndicador::SIN_DATOS, $this->calculadora->i6(new FiltroIndicadores)->estado);

        $this->mejora(['estado' => 'PROPUESTA']);

        $i6 = $this->calculadora->i6(new FiltroIndicadores);
        $this->assertSame(0.0, $i6->valor);
        $this->assertSame(ResultadoIndicador::CALCULADO, $i6->estado);
    }

    public function test_cada_resultado_lleva_los_metadatos_del_contrato(): void
    {
        foreach ($this->calculadora->todos(new FiltroIndicadores) as $codigo => $resultado) {
            $datos = $resultado->aArreglo();

            $this->assertSame($codigo, $datos['codigo']);
            foreach (['nombre', 'formula', 'unidad', 'fuente', 'cohorte', 'periodo', 'fecha_corte', 'estado'] as $campo) {
                $this->assertNotEmpty($datos[$campo], $codigo.' sin '.$campo);
            }
            foreach (['valor', 'numerador', 'denominador', 'poblacion_elegible', 'n', 'cobertura'] as $campo) {
                $this->assertArrayHasKey($campo, $datos);
            }
        }
    }

    public function test_un_fallo_de_consulta_se_informa_como_error(): void
    {
        Schema::rename('seguimiento_egresado.mejora_curricular', 'mejora_curricular_oculta');

        $resultado = $this->calculadora->todos(new FiltroIndicadores)['I6'];

        $this->assertSame(ResultadoIndicador::ERROR, $resultado->estado);
        $this->assertNull($resultado->valor);
    }
}
