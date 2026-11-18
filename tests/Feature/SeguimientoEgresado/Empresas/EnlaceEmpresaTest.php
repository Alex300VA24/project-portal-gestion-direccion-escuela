<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceNoGenerable;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class EnlaceEmpresaTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;
    use FabricaEmpresasIndicadores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->entrarComo();
    }

    private function egresadoAutorizado(?bool $autoriza = true): int
    {
        $egresado = $this->egresado($this->cohorte());
        $invitacion = $this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $egresado);
        $this->responder($invitacion, cambios: ['autoriza_contacto_empresa' => $autoriza]);

        return $egresado;
    }

    public function test_guarda_solo_el_hash_del_token(): void
    {
        $enlace = app(EnlaceEmpresa::class)->generar(['id_campania' => $this->campania(), 'contexto' => 'MERCADO']);

        $fila = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $enlace['id'])->first();

        $this->assertSame(hash('sha256', $enlace['token']), $fila->token_hash);
        $this->assertNotSame($enlace['token'], $fila->token_hash);
        $this->assertGreaterThanOrEqual(64, strlen($enlace['token']));
        $this->assertSame('PENDIENTE', $fila->estado);
    }

    public function test_cada_enlace_tiene_un_token_distinto(): void
    {
        $campania = $this->campania();
        $servicio = app(EnlaceEmpresa::class);

        $this->assertNotSame(
            $servicio->generar(['id_campania' => $campania, 'contexto' => 'MERCADO'])['token'],
            $servicio->generar(['id_campania' => $campania, 'contexto' => 'MERCADO'])['token'],
        );
    }

    public function test_no_genera_en_campania_de_egresados_ni_cerrada(): void
    {
        foreach ([['tipo' => 'EGRESADOS'], ['estado' => 'CERRADA'], ['fecha_fin' => now()->subDays(2)->toDateString()]] as $cambios) {
            try {
                app(EnlaceEmpresa::class)->generar(['id_campania' => $this->campania($cambios), 'contexto' => 'MERCADO']);
                $this->fail('Debió rechazar la campaña.');
            } catch (EnlaceNoGenerable) {
                $this->assertDatabaseCount('seguimiento_egresado.invitacion_empresa', 0);
            }
        }
    }

    public function test_el_vencimiento_no_supera_el_cierre_de_la_campania(): void
    {
        $fin = now('America/Lima')->addDays(5);
        $enlace = app(EnlaceEmpresa::class)->generar([
            'id_campania' => $this->campania(['fecha_fin' => $fin->toDateString()]),
            'contexto' => 'MERCADO',
            'dias_validez' => 90,
        ]);

        $this->assertSame($fin->toDateString(), $enlace['expira_en']->setTimezone('America/Lima')->toDateString());
    }

    public function test_desempeno_exige_egresado_que_autorizo_el_contacto(): void
    {
        $servicio = app(EnlaceEmpresa::class);
        $campania = $this->campania();

        foreach ([null, $this->egresadoAutorizado(false), $this->egresado($this->cohorte())] as $egresado) {
            try {
                $servicio->generar(['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $egresado]);
                $this->fail('Debió rechazar al egresado.');
            } catch (EnlaceNoGenerable $e) {
                $this->assertSame('id_egresado', $e->campo);
            }
        }

        $enlace = $servicio->generar(['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $this->egresadoAutorizado()]);
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $enlace['id'], 'contexto' => 'DESEMPENO']);
    }

    public function test_no_repite_la_evaluacion_de_un_egresado_en_la_campania(): void
    {
        $servicio = app(EnlaceEmpresa::class);
        $campania = $this->campania();
        $egresado = $this->egresadoAutorizado();
        $servicio->generar(['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $egresado]);

        $this->expectException(EnlaceNoGenerable::class);
        $servicio->generar(['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $egresado]);
    }

    private function paginaDe(int $campania): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('seguimiento-egresado.empresas', ['campania' => $campania]));
    }

    /** Egresado que autorizó contactar a su empresa y pertenece a una promoción de la campaña. */
    private function evaluableEnCampania(int $campania, array $respuesta = []): int
    {
        $egresado = $this->egresado($this->cohorte());
        $this->responder($this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $egresado), cambios: ['autoriza_contacto_empresa' => true] + $respuesta);
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $campania, 'id_cohorte' => DB::table('seguimiento_egresado.egresado')->where('id', $egresado)->value('id_cohorte')]);

        return $egresado;
    }

    public function test_secretaria_genera_y_ve_el_enlace_una_sola_vez(): void
    {
        $campania = $this->campania();

        $respuesta = $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $campania, 'contexto' => 'MERCADO']);

        $respuesta->assertRedirect(route('seguimiento-egresado.empresas', ['campania' => $campania]));
        $url = session('enlaces_empresa')[0]['url'];
        $this->get($url)->assertOk()->assertSee('Demanda de habilidades');

        $this->followingRedirects()->get(route('seguimiento-egresado.empresas', ['campania' => $campania]))->assertDontSee('/encuesta-empresa/');
    }

    public function test_error_de_generacion_se_muestra_como_error(): void
    {
        $this->from(route('seguimiento-egresado.empresas'))->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $this->campania(['estado' => 'CERRADA']), 'contexto' => 'MERCADO',
        ])->assertRedirect(route('seguimiento-egresado.empresas'))->assertSessionHasErrors('id_campania');

        $this->from(route('seguimiento-egresado.empresas'))->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $this->campania(), 'contexto' => 'MERCADO', 'dias_validez' => 500,
        ])->assertSessionHasErrors('dias_validez');
    }

    public function test_el_enlace_vale_hasta_el_cierre_de_la_campania_si_no_se_indican_dias(): void
    {
        $fin = now('America/Lima')->addDays(40);
        $enlace = app(EnlaceEmpresa::class)->generar(['id_campania' => $this->campania(['fecha_fin' => $fin->toDateString()]), 'contexto' => 'MERCADO']);

        $this->assertSame($fin->toDateString(), $enlace['expira_en']->setTimezone('America/Lima')->toDateString());
    }

    public function test_un_enlace_perdido_se_reemplaza_y_el_anterior_deja_de_funcionar(): void
    {
        $campania = $this->campania();
        $i = $this->invitacionEmpresa($campania, cambios: ['organizacion' => 'Empresa Original']);

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertRedirect(route('seguimiento-egresado.empresas', ['campania' => $campania]));
        $nuevo = session('enlaces_empresa')[0]['url'];

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertNotFound();
        $this->get($nuevo)->assertOk();
        $this->assertDatabaseCount('seguimiento_egresado.invitacion_empresa', 1);
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'organizacion' => 'Empresa Original', 'estado' => 'PENDIENTE']);
    }

    public function test_un_enlace_vencido_se_puede_renovar(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['estado' => 'EXPIRADA', 'expira_en' => now()->subDay()->format('Y-m-d H:i:sP')]);

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertRedirect();

        $this->get(session('enlaces_empresa')[0]['url'])->assertOk();
    }

    public function test_no_se_renueva_una_invitacion_respondida_ni_de_campania_cerrada(): void
    {
        $respondida = $this->invitacionEmpresa($this->campania());
        $this->respuestaEmpresa($respondida['id'], null);
        $cerrada = $this->invitacionEmpresa($this->campania(['estado' => 'CERRADA']));

        foreach ([$respondida, $cerrada] as $i) {
            $hash = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('token_hash');

            $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertSessionHas('error');
            $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'token_hash' => $hash]);
        }
    }

    public function test_copiar_el_enlace_deja_constancia(): void
    {
        $campania = $this->campania();
        $i = $this->invitacionEmpresa($campania);

        $this->post(route('seguimiento-egresado.empresas.enlaces.enviado', ['id' => $i['id']]))->assertNoContent();

        $this->assertNotNull(DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('enviado_en'));
        $this->paginaDe($campania)->assertSee('entregado el')->assertSee('Enlace nuevo');
    }

    public function test_un_enlace_ya_copiado_no_se_reemplaza_sin_confirmar(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['enviado_en' => now()->format('Y-m-d H:i:sP')]);
        $hash = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('token_hash');

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertSessionHas('error');
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'token_hash' => $hash]);
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertOk();

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]), ['confirmar_reemplazo' => '1'])->assertSessionHas('enlaces_empresa');
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertNotFound();
        $this->assertNull(DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('enviado_en'));
    }

    public function test_la_accion_de_la_tabla_depende_del_estado(): void
    {
        $campania = $this->campania();
        $this->invitacionEmpresa($campania, cambios: ['enviado_en' => now()->format('Y-m-d H:i:sP')]);
        $respondida = $this->invitacionEmpresa($campania);
        $this->respuestaEmpresa($respondida['id'], null);

        $pagina = $this->paginaDe($campania)->assertOk();
        $pagina->assertSee('Enlace nuevo')->assertSee('Ya entregaste este enlace')->assertSee('✓ Respondió');
        $pagina->assertDontSee(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $respondida['id']]), false);
    }

    public function test_los_enlaces_generados_se_muestran_en_una_ventana_con_copiar_y_descargar(): void
    {
        $campania = $this->campania();
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $campania, 'contexto' => 'MERCADO']);

        $this->paginaDe($campania)->assertOk()
            ->assertSee('id="dlg-enlaces-empresa"', false)->assertSee('Copiar todos')->assertSee('Descargar lista (CSV)')->assertSee('Se muestran una sola vez');
    }

    public function test_la_tabla_trae_la_empresa_y_el_contacto_que_dejo_el_egresado(): void
    {
        $campania = $this->campania();
        $this->evaluableEnCampania($campania, ['nombre_empleador_cliente' => 'Andes Software Labs', 'contacto_jefe' => '+51 944 120 301']);

        $this->paginaDe($campania)->assertOk()
            ->assertSee('Andes Software Labs')->assertSee('+51 944 120 301')->assertSee('Sin enlace')->assertSee('Generar enlace')
            ->assertSee('Generar los enlaces que faltan (1)')->assertDontSee('Organización (opcional)');
    }

    public function test_solo_aparecen_quienes_autorizaron_y_estan_en_las_promociones_de_la_campania(): void
    {
        $campania = $this->campania();
        $dentro = $this->evaluableEnCampania($campania);
        $noAutorizo = $this->egresado($this->cohorte());
        $this->responder($this->invitacionSeguimiento($this->campania(['tipo' => 'EGRESADOS']), $noAutorizo), cambios: ['autoriza_contacto_empresa' => false]);
        $otraCampania = $this->campania();
        $fuera = $this->evaluableEnCampania($otraCampania);

        $filas = app(\App\Modules\SeguimientoEgresado\Services\Empresas\ResultadosEmpresas::class)->evaluablesDeCampania($campania);

        $this->assertSame([$dentro], $filas->pluck('id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_generar_un_enlace_de_desempeno_toma_empresa_y_contacto_de_la_respuesta(): void
    {
        $campania = $this->campania();
        $egresado = $this->evaluableEnCampania($campania, ['nombre_empleador_cliente' => 'Banco del Norte', 'contacto_jefe' => '+51 944 555 666']);

        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $egresado])->assertRedirect();

        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id_campania' => $campania, 'id_egresado' => $egresado, 'organizacion' => 'Banco del Norte', 'contacto_empresa' => '+51 944 555 666']);
        $fila = session('enlaces_empresa')[0];
        $this->assertSame('Banco del Norte', $fila['empresa']);
        $this->assertSame('+51 944 555 666', $fila['contacto']);
    }

    public function test_generar_los_que_faltan_crea_un_enlace_por_egresado_sin_repetir_a_quien_ya_tiene(): void
    {
        $campania = $this->campania();
        $uno = $this->evaluableEnCampania($campania);
        $dos = $this->evaluableEnCampania($campania);
        $tres = $this->evaluableEnCampania($campania);
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $campania, 'contexto' => 'DESEMPENO', 'id_egresado' => $uno]);
        $hashUno = DB::table('seguimiento_egresado.invitacion_empresa')->where('id_egresado', $uno)->value('token_hash');

        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $campania, 'contexto' => 'DESEMPENO'])
            ->assertRedirect(route('seguimiento-egresado.empresas', ['campania' => $campania]));

        $this->assertCount(2, session('enlaces_empresa'));
        $this->assertSame(3, DB::table('seguimiento_egresado.invitacion_empresa')->where('id_campania', $campania)->count());
        $this->assertSame($hashUno, DB::table('seguimiento_egresado.invitacion_empresa')->where('id_egresado', $uno)->value('token_hash'), 'quien ya tenía enlace vigente no se toca');

        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $campania, 'contexto' => 'DESEMPENO'])->assertSessionHas('info');
        $this->assertSame(3, DB::table('seguimiento_egresado.invitacion_empresa')->where('id_campania', $campania)->count());
    }

    public function test_generar_enlaces_de_mercado_por_cantidad(): void
    {
        $campania = $this->campania();

        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $campania, 'contexto' => 'MERCADO', 'cantidad' => 4])->assertRedirect();

        $this->assertCount(4, session('enlaces_empresa'));
        $this->assertSame(4, DB::table('seguimiento_egresado.invitacion_empresa')->where('id_campania', $campania)->where('contexto', 'MERCADO')->whereNull('id_egresado')->count());
        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $campania, 'contexto' => 'MERCADO'])->assertSessionHasErrors('cantidad');
        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $campania, 'contexto' => 'MERCADO', 'cantidad' => 500])->assertSessionHasErrors('cantidad');
    }

    public function test_no_se_generan_enlaces_en_lote_en_una_campania_cerrada(): void
    {
        $cerrada = $this->campania(['estado' => 'CERRADA']);

        $this->post(route('seguimiento-egresado.empresas.enlaces.lote'), ['id_campania' => $cerrada, 'contexto' => 'MERCADO', 'cantidad' => 2])->assertSessionHasErrors('general');
        $this->assertDatabaseCount('seguimiento_egresado.invitacion_empresa', 0);
    }

    public function test_la_pantalla_elige_la_campania_abierta_y_ofrece_crear_una_nueva(): void
    {
        $this->campania(['estado' => 'CERRADA', 'nombre' => 'Vieja de empleadores']);
        $abierta = $this->campania(['nombre' => 'Abierta de empleadores']);

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()
            ->assertSee('value="'.$abierta.'" selected', false)->assertSee('+ Nueva campaña de empleadores')->assertSee("abrirNuevaCampania('EMPRESAS')", false)
            ->assertSee('name="volver_a" value="empresas"', false)->assertSee('Lo que dicen las empresas');
    }

    public function test_crear_una_campania_de_empleadores_desde_esta_pantalla_vuelve_aqui(): void
    {
        $promocion = $this->cohorte();

        $respuesta = $this->post(route('seguimiento-egresado.campanias.store'), [
            'nombre' => 'Empleadores 2026-II', 'tipo' => 'EMPRESAS', 'periodo' => '2026-II', 'volver_a' => 'empresas',
            'fecha_inicio' => now()->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(), 'cohortes' => [$promocion],
        ]);

        $id = (int) DB::table('seguimiento_egresado.campania_seguimiento')->where('nombre', 'Empleadores 2026-II')->value('id');
        $respuesta->assertRedirect(route('seguimiento-egresado.empresas', ['campania' => $id]));
    }

    public function test_token_invalido_responde_404_sin_datos(): void
    {
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => str_repeat('a', 64)]))
            ->assertNotFound()
            ->assertSee('no es válido');
    }

    public function test_token_con_formato_invalido_no_llega_al_controlador(): void
    {
        $this->get('/seguimiento-egresado/encuesta-empresa/corto')->assertNotFound();
    }

    public function test_token_vencido_se_rechaza_y_queda_expirado(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['expira_en' => now()->subMinute()->format('Y-m-d H:i:sP')]);

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertStatus(410)->assertSee('venció');
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'estado' => 'EXPIRADA']);
    }

    public function test_token_usado_se_rechaza(): void
    {
        $i = $this->invitacionEmpresa($this->campania());
        $this->respuestaEmpresa($i['id'], null);

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertStatus(409)->assertSee('ya fue respondida');
    }

    public function test_token_anulado_o_de_campania_cerrada_se_rechaza(): void
    {
        $anulada = $this->invitacionEmpresa($this->campania(), cambios: ['estado' => 'ANULADA']);
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $anulada['token']]))->assertNotFound();

        $cerrada = $this->invitacionEmpresa($this->campania(['estado' => 'CERRADA']));
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $cerrada['token']]))->assertStatus(410);
    }

    public function test_el_formulario_publico_no_expone_padron_ni_menu_interno(): void
    {
        $egresado = $this->egresado($this->cohorte(), cambios: [
            'nombres' => 'Lucía', 'apellido_paterno' => 'Ficticia', 'numero_documento' => '99887766',
            'codigo_matricula' => 'MATRICULA01', 'correo_institucional' => 'lucia@privado.test',
        ]);
        $otro = $this->egresado($this->cohorte(), cambios: ['nombres' => 'OtraPersonaDelPadron']);
        $i = $this->invitacionEmpresa($this->campania(), 'DESEMPENO', $egresado);

        $respuesta = $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]));

        $respuesta->assertOk()
            ->assertSee('Lucía Ficticia')
            ->assertDontSee('99887766')
            ->assertDontSee('MATRICULA01')
            ->assertDontSee('lucia@privado.test')
            ->assertDontSee('OtraPersonaDelPadron')
            ->assertDontSee('sg-navigation', false)
            ->assertDontSee(route('seguimiento-egresado.padron'), false);

        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $respuesta->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $respuesta->headers->get('X-Robots-Tag'));
        $this->assertNotNull($otro);
    }

    public function test_la_empresa_no_necesita_cuenta_para_abrir_el_formulario(): void
    {
        $i = $this->invitacionEmpresa($this->campania());
        $this->flushSession();

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertOk()->assertDontSee('Iniciar sesión');
    }
}
