<?php

namespace Tests\Feature\SeguimientoEgresado\Empresas;

use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Permisos por perfil con las cuentas del portal: sin sesión se pide el acceso del portal; sin el rol, 403. */
class PermisosEmpresasIndicadoresTest extends TestCase
{
    use ComoPersonal, FabricaEmpresasIndicadores, UsaBaseDeDatos;

    public function test_sin_sesion_las_rutas_internas_se_rechazan(): void
    {
        $this->get(route('seguimiento-egresado.empresas'))->assertRedirect(route('login'));
        $this->get(route('seguimiento-egresado.indicadores'))->assertRedirect(route('login'));
        $this->get(route('seguimiento-egresado.indicadores.exportar'))->assertRedirect(route('login'));
        $this->post(route('seguimiento-egresado.empresas.enlaces'), [])->assertRedirect(route('login'));
    }

    public function test_el_egresado_con_su_enlace_no_entra_a_resultados(): void
    {
        // Solo tiene la sesión del enlace, sin cuenta del portal: las páginas internas le piden iniciar sesión.
        $this->withSession(['seguimiento-egresado.identidad' => ['perfil' => 'EGRESADO', 'egresado_id' => 1, 'invitacion_id' => 1, 'vence_en' => now()->addDay()->toIso8601String()]]);

        $this->get(route('seguimiento-egresado.empresas'))->assertRedirect(route('login'));
        $this->get(route('seguimiento-egresado.indicadores'))->assertRedirect(route('login'));
    }

    public function test_un_usuario_del_portal_sin_rol_en_el_modulo_recibe_403(): void
    {
        $this->actingAs($this->profesor());

        $this->get(route('seguimiento-egresado.empresas'))->assertForbidden();
        $this->get(route('seguimiento-egresado.indicadores'))->assertForbidden();
    }

    public function test_secretaria_consulta_y_genera(): void
    {
        $this->entrarComo('SECRETARIA');

        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()->assertSee('Gestión diaria')->assertDontSee('Evidencia de las mejoras');
        $this->get(route('seguimiento-egresado.indicadores.exportar'))->assertOk();
        $this->get(route('seguimiento-egresado.empresas'))->assertOk();
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $this->campania(), 'contexto' => 'MERCADO', 'dias_validez' => 5])
            ->assertRedirect();
    }

    public function test_director_consulta_pero_no_genera_enlaces(): void
    {
        $this->entrarComo('DIRECTOR');

        $i = $this->invitacionEmpresa($this->campania());
        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertForbidden();
        $this->get(route('seguimiento-egresado.empresas'))->assertOk()->assertDontSee(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]), false);

        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()->assertSee('Evidencia de las mejoras')->assertDontSee('Gestión diaria');
        $this->get(route('seguimiento-egresado.empresas'))->assertOk()->assertDontSee('Generar enlace')->assertDontSee('Generar los enlaces que faltan');
        $this->post(route('seguimiento-egresado.empresas.enlaces'), ['id_campania' => $this->campania(), 'contexto' => 'MERCADO', 'dias_validez' => 5])
            ->assertForbidden();
    }

    public function test_reemplazar_un_enlace_queda_en_auditoria(): void
    {
        $this->entrarComo('SECRETARIA');
        $i = $this->invitacionEmpresa($this->campania());

        $this->post(route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i['id']]))->assertSessionHas('enlaces_empresa');

        $this->assertDatabaseHas('seguimiento_egresado.auditoria', ['tabla_afectada' => 'invitacion_empresa', 'id_registro' => $i['id'], 'accion' => 'REEMPLAZAR_ENLACE']);
    }

    public function test_el_director_no_marca_enlaces_como_enviados(): void
    {
        $this->entrarComo('DIRECTOR');
        $i = $this->invitacionEmpresa($this->campania());

        $this->post(route('seguimiento-egresado.empresas.enlaces.enviado', ['id' => $i['id']]))->assertForbidden();
    }

    public function test_la_encuesta_publica_no_pide_sesion(): void
    {
        $i = $this->invitacionEmpresa($this->campania());

        $this->get(route('seguimiento-egresado.encuesta-empresa', ['token' => $i['token']]))->assertOk();
    }
}
