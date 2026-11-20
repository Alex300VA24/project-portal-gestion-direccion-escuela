<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class RespuestaEmpresaTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;
    use FabricaEmpresasIndicadores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->entrarComo();
    }

    private function enviar(string $token, array $datos)
    {
        return $this->post(route('seguimiento-egresado.encuesta-empresa.responder', ['token' => $token]), $datos);
    }

    private function i5(): ResultadoIndicador
    {
        return app(CalculadoraIndicadores::class)->i5(new FiltroIndicadores);
    }

    public function test_guarda_una_evaluacion_de_desempeno_y_alimenta_i5(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), 'DESEMPENO', $this->egresado($this->cohorte()));

        $this->enviar($i['token'], $this->datosEncuesta(['satisfaccion' => 'SATISFECHO']))
            ->assertRedirect(route('seguimiento-egresado.encuesta-empresa.gracias'));

        $respuesta = DB::table('seguimiento_egresado.respuesta_empresa')->where('id_invitacion_empresa', $i['id'])->first();
        $this->assertSame('Organización Ficticia', $respuesta->organizacion_informada);
        $this->assertSame($this->catalogo('cat_nivel_satisfaccion', 'SATISFECHO'), (int) $respuesta->id_nivel_satisfaccion);
        $this->assertSame(2, DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->where('id_respuesta', $respuesta->id)->where('tipo', 'DEMANDA')->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->where('id_respuesta', $respuesta->id)->where('tipo', 'BRECHA')->count());
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'estado' => 'COMPLETADA']);

        $this->assertSame(100.0, $this->i5()->valor);
        $this->assertSame(1, $this->i5()->denominador);
    }

    public function test_mercado_se_guarda_sin_satisfaccion_y_no_alimenta_i5(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], $this->datosEncuesta())->assertRedirect(route('seguimiento-egresado.encuesta-empresa.gracias'));

        $this->assertNull(DB::table('seguimiento_egresado.respuesta_empresa')->where('id_invitacion_empresa', $i['id'])->value('id_nivel_satisfaccion'));
        $this->assertSame(ResultadoIndicador::SIN_DATOS, $this->i5()->estado);
        $this->assertNull($this->i5()->valor);
    }

    public function test_mercado_rechaza_una_satisfaccion_enviada_a_mano(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], $this->datosEncuesta(['satisfaccion' => 'MUY_SATISFECHO']))->assertSessionHasErrors('satisfaccion');

        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'estado' => 'PENDIENTE']);
    }

    public function test_desempeno_exige_satisfaccion(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), 'DESEMPENO', $this->egresado($this->cohorte()));

        $this->enviar($i['token'], $this->datosEncuesta())->assertSessionHasErrors('satisfaccion');

        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_valida_campos_y_catalogos_en_el_servidor(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], [])->assertSessionHasErrors(['organizacion', 'sector', 'demandas', 'brechas']);
        $this->enviar($i['token'], $this->datosEncuesta(['sector' => 'INVENTADO']))->assertSessionHasErrors('sector');
        $this->enviar($i['token'], $this->datosEncuesta(['demandas' => ['INVENTADA']]))->assertSessionHasErrors('demandas.0');

        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_la_opcion_otra_exige_y_guarda_su_detalle(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], $this->datosEncuesta(['demandas' => ['OTRA']]))->assertSessionHasErrors('detalle_demanda_otra');

        $this->enviar($i['token'], $this->datosEncuesta(['demandas' => ['OTRA'], 'detalle_demanda_otra' => 'Robótica']))
            ->assertRedirect(route('seguimiento-egresado.encuesta-empresa.gracias'));

        $this->assertDatabaseHas('seguimiento_egresado.respuesta_empresa_habilidad', ['tipo' => 'DEMANDA', 'detalle_otro' => 'Robótica']);
    }

    public function test_la_encuesta_publica_no_depende_de_la_sesion_para_enviar(): void
    {
        $excluidos = Route::getRoutes()->getByName('seguimiento-egresado.encuesta-empresa.responder')->excludedMiddleware();

        $this->assertContains(ValidateCsrfToken::class, $excluidos);
    }

    public function test_limita_las_habilidades_a_cinco(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], $this->datosEncuesta(['demandas' => ['BASES_DATOS', 'CLOUD', 'DEVOPS', 'IA', 'REDES', 'INGLES']]))->assertSessionHasErrors('demandas');
        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_la_encuesta_va_por_pasos_y_funciona_sin_javascript(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), 'DESEMPENO', $this->egresado($this->cohorte()));

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertOk()
            ->assertSee('data-sg-paso', false)
            ->assertSee('data-sg-siguiente', false)
            ->assertSee('Enviar mis respuestas');
    }

    public function test_al_terminar_se_ve_una_confirmacion_clara(): void
    {
        $i = $this->invitacionEmpresa($this->campania());
        $this->enviar($i['token'], $this->datosEncuesta());

        $this->get(route('seguimiento-egresado.encuesta-empresa.gracias'))->assertOk()->assertSee('Gracias por responder')->assertSee('sg-estado-exito', false);
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertStatus(409)->assertSee('sg-estado-info', false)->assertDontSee('sg-navigation', false);
    }

    public function test_un_enlace_no_admite_una_segunda_respuesta(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->enviar($i['token'], $this->datosEncuesta())->assertRedirect();
        $this->enviar($i['token'], $this->datosEncuesta(['organizacion' => 'Otra']))->assertStatus(409);

        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 1);
        $this->assertSame('Organización Ficticia', DB::table('seguimiento_egresado.respuesta_empresa')->value('organizacion_informada'));
    }

    public function test_un_enlace_vencido_o_invalido_no_guarda_nada(): void
    {
        $vencida = $this->invitacionEmpresa($this->campania(), cambios: ['expira_en' => now()->subMinute()->format('Y-m-d H:i:sP')]);

        $this->enviar($vencida['token'], $this->datosEncuesta())->assertStatus(410);
        $this->enviar(str_repeat('b', 64), $this->datosEncuesta())->assertNotFound();

        $this->assertDatabaseCount('seguimiento_egresado.respuesta_empresa', 0);
    }

    public function test_la_pagina_interna_muestra_resultados_de_la_base_de_datos(): void
    {
        $campania = $this->campania();
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($this->cohorte()))['id'], 'SATISFECHO', ['organizacion_informada' => 'Org Uno']);
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($this->cohorte()))['id'], 'INSATISFECHO', ['organizacion_informada' => 'Org Dos']);
        $mercado = $this->respuestaEmpresa($this->invitacionEmpresa($campania)['id'], null, ['organizacion_informada' => 'Org Mercado']);
        DB::table('seguimiento_egresado.respuesta_empresa_habilidad')->insert(['id_respuesta' => $mercado, 'id_habilidad' => $this->catalogo('cat_habilidad', 'CLOUD'), 'tipo' => 'DEMANDA']);

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()
            ->assertSee('50,0')
            ->assertSee('1 de 2 evaluaciones de desempeño')
            ->assertSee('Org Mercado')
            ->assertSee('Cloud')
            ->assertDontSee('DEMO-EMP');
    }

    public function test_la_pagina_interna_muestra_periodo_corte_fuente_y_total(): void
    {
        $campania = $this->campania(['nombre' => 'Campaña Visible', 'periodo' => '2026-II']);
        $this->respuestaEmpresa($this->invitacionEmpresa($campania, 'DESEMPENO', $this->egresado($this->cohorte()))['id'], 'SATISFECHO');

        $this->get(route('seguimiento-egresado.empresas', ['campania' => $campania]))->assertOk()
            ->assertSee('Periodo 2026-II')
            ->assertSee('Corte '.now('America/Lima')->format('d/m/Y'))
            ->assertSee('Fuente: Encuesta empresarial')
            ->assertSee('1 invitación')
            ->assertSee('N=1 evaluaciones', false);
    }

    public function test_la_encuesta_publica_lleva_el_pie_comun(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))
            ->assertSee('Universidad Nacional de Trujillo')
            ->assertDontSee('Módulo de demostración');
    }

    public function test_sin_respuestas_la_pagina_muestra_sin_medicion_y_no_cero(): void
    {
        $this->campania();

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()->assertSee('Sin medición')->assertSee('Sin datos')->assertDontSee('0,0');
    }
}
