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

    public function test_secretaria_genera_y_ve_el_enlace_una_sola_vez(): void
    {
        $campania = $this->campania();

        $respuesta = $this->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $campania, 'contexto' => 'MERCADO', 'dias_validez' => 10,
        ]);

        $respuesta->assertRedirect(route('seguimiento-egresado.empresas'));
        $url = session('enlace_generado')['url'];
        $this->get($url)->assertOk()->assertSee('Demanda de habilidades');

        $this->followingRedirects()->get(route('seguimiento-egresado.empresas'))->assertDontSee('/encuesta-empresa/');
    }

    public function test_error_de_generacion_se_muestra_junto_al_campo(): void
    {
        $this->from(route('seguimiento-egresado.empresas'))->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $this->campania(['estado' => 'CERRADA']), 'contexto' => 'MERCADO', 'dias_validez' => 10,
        ])->assertRedirect(route('seguimiento-egresado.empresas'))->assertSessionHasErrors('id_campania');

        $this->from(route('seguimiento-egresado.empresas'))->post(route('seguimiento-egresado.empresas.enlaces'), [
            'id_campania' => $this->campania(), 'contexto' => 'MERCADO', 'dias_validez' => 500,
        ])->assertSessionHasErrors('dias_validez');
    }

    public function test_un_enlace_perdido_se_reemplaza_y_el_anterior_deja_de_funcionar(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['organizacion' => 'Empresa Original']);

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertRedirect(route('seguimiento-egresado.empresas'));
        $nuevo = session('enlace_generado')['url'];

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertNotFound();
        $this->get($nuevo)->assertOk();
        $this->assertDatabaseCount('seguimiento_egresado.invitacion_empresa', 1);
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'organizacion' => 'Empresa Original', 'estado' => 'PENDIENTE']);
    }

    public function test_un_enlace_vencido_se_puede_renovar(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['estado' => 'EXPIRADA', 'expira_en' => now()->subDay()->format('Y-m-d H:i:sP')]);

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertRedirect(route('seguimiento-egresado.empresas'));

        $this->get(session('enlace_generado')['url'])->assertOk();
    }

    public function test_no_se_renueva_una_invitacion_respondida_ni_de_campania_cerrada(): void
    {
        $respondida = $this->invitacionEmpresa($this->campania());
        $this->respuestaEmpresa($respondida['id'], null);
        $cerrada = $this->invitacionEmpresa($this->campania(['estado' => 'CERRADA']));

        foreach ([$respondida, $cerrada] as $i) {
            $hash = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('token_hash');

            $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertSessionHas('aviso_enlace');
            $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'token_hash' => $hash]);
        }
    }

    public function test_copiar_el_enlace_deja_constancia(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->post(route('seguimiento-egresado.empresas.enlaces.enviado', ['id' => $i['id']]))->assertNoContent();

        $this->assertNotNull(DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('enviado_en'));
        $this->get(route('seguimiento-egresado.empresas'))->assertSee('Copiado')->assertSee('Reemplazar');
    }

    public function test_un_enlace_ya_copiado_no_se_reemplaza_sin_confirmar(): void
    {
        $i = $this->invitacionEmpresa($this->campania(), cambios: ['enviado_en' => now()->format('Y-m-d H:i:sP')]);
        $hash = DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('token_hash');

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertSessionHas('aviso_enlace');
        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $i['id'], 'token_hash' => $hash]);
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertOk();

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]), ['confirmar_reemplazo' => '1'])->assertSessionHas('enlace_generado');
        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertNotFound();
        $this->assertNull(DB::table('seguimiento_egresado.invitacion_empresa')->where('id', $i['id'])->value('enviado_en'));
    }

    public function test_la_accion_de_la_tabla_depende_del_estado(): void
    {
        $campania = $this->campania();
        $this->invitacionEmpresa($campania);
        $this->invitacionEmpresa($campania, cambios: ['estado' => 'EXPIRADA', 'expira_en' => now()->subDay()->format('Y-m-d H:i:sP')]);

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()->assertSee('Generar otro')->assertSee('Renovar')->assertDontSee('confirmar_reemplazo', false);
    }

    public function test_la_tabla_ofrece_nuevo_enlace_solo_si_se_puede(): void
    {
        $campania = $this->campania();
        $pendiente = $this->invitacionEmpresa($campania);
        $respondida = $this->invitacionEmpresa($campania);
        $this->respuestaEmpresa($respondida['id'], null);

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()
            ->assertSee(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $pendiente['id']]), false)
            ->assertDontSee(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $respondida['id']]), false);
    }

    public function test_el_enlace_generado_se_muestra_en_un_modal(): void
    {
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $this->campania(), 'contexto' => 'MERCADO', 'dias_validez' => 10]);

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()
            ->assertSee('id="sg-modal-enlace-listo"', false)
            ->assertSee('data-sg-abrir-al-cargar', false)
            ->assertSee('Copiar enlace');
    }

    public function test_el_formulario_pide_promocion_y_luego_egresado(): void
    {
        $this->egresadoAutorizado();
        $this->campania();

        $this->get(route('seguimiento-egresado.empresas'))->assertOk()
            ->assertSee('<optgroup label="C-', false)
            ->assertSee('id="sg-promocion"', false)
            ->assertSee('data-cohorte="C-', false)
            ->assertDontSee('sg-buscar-egresado', false);
    }

    public function test_las_invitaciones_se_paginan_y_conservan_filtros(): void
    {
        $campania = $this->campania();
        for ($i = 1; $i <= 12; $i++) {
            $this->invitacionEmpresa($campania, cambios: ['organizacion' => 'Org-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $primera = $this->get(route('seguimiento-egresado.empresas', ['campania' => $campania]))->assertOk();
        $primera->assertSee('Org-12')->assertDontSee('Org-02')->assertSee('Página 1 de 2')->assertSee('Siguiente')->assertSee('campania='.$campania.'&amp;pagina=2', false);

        $this->get(route('seguimiento-egresado.empresas', ['campania' => $campania, 'pagina' => 2]))->assertOk()
            ->assertSee('Org-02')->assertDontSee('Org-12')->assertSee('Anterior');
        $this->get(route('seguimiento-egresado.empresas', ['campania' => $campania, 'pagina' => 999]))->assertOk()->assertSee('Página 2 de 2');
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
