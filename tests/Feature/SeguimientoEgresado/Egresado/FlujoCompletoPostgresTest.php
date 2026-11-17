<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

/**
 * Recorrido completo sobre el esquema vigente: la Secretaría emite las invitaciones, el egresado abre su enlace,
 * responde la encuesta y la respuesta queda en su historial.
 */
class FlujoCompletoPostgresTest extends TestCase
{
    use ComoPersonal, FabricaSeguimiento, UsaEsquemaPostgres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPostgres();
        $this->actingAs($this->secretaria());
    }

    protected function tearDown(): void
    {
        $this->revertirPostgres();
        parent::tearDown();
    }

    public function test_desde_la_emision_hasta_el_historial_del_egresado(): void
    {
        $egresado = $this->crearEgresado(['nombres' => 'Marisol', 'apellido_paterno' => 'Torres', 'apellido_materno' => 'Linares']);
        $this->darPorEgresado($egresado);
        $cohorte = (int) DB::table('seguimiento_egresado.egresado')->where('id', $egresado)->value('id_cohorte');
        $campania = $this->crearCampania(['fecha_inicio' => now()->subDays(5)->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString()]);
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $campania, 'id_cohorte' => $cohorte]);

        // 1. La Secretaría emite: se muestra un enlace por egresado y en la BD queda solo el hash.
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania))->assertRedirect();
        $enlaces = session('enlaces_emitidos');
        $this->assertCount(1, $enlaces);
        $this->assertSame('Marisol Torres Linares', $enlaces[0]['egresado']);
        $url = $enlaces[0]['url'];

        // 2. El egresado abre su enlace y entra directo a su área, con la encuesta pendiente.
        $this->get($url)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertOk()->assertSee('Marisol')->assertSee('Tienes una encuesta por responder');
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertOk()->assertSee('Encuesta de seguimiento a egresados');

        // 3. Responde la encuesta.
        $datos = $this->datosDeEncuesta(['brechas' => ['CLOUD'], 'fortalezas' => [], 'detalle_brecha_otra' => null])
            + ['contacto' => ['correo_personal' => 'marisol.torres@correo.test', 'celular_whatsapp' => '+51 900 123 456']];
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $invitacion = DB::table('seguimiento_egresado.invitacion_egresado')->where('id_egresado', $egresado)->first();
        $this->assertSame('COMPLETADA', $invitacion->estado);
        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->first();
        $this->assertNotNull($medicion);
        $this->assertSame(1, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->whereNull('vigente_hasta')->count());

        // 4. La respuesta aparece en el historial y se puede abrir su detalle.
        $this->get(route('seguimiento-egresado.egresado.historial'))->assertOk()->assertSee('Respondida')->assertSee('Ver respuesta');
        $this->get(route('seguimiento-egresado.egresado.historial.detalle', $medicion->id))->assertOk()->assertSee('Empresa Ficticia S.A.C.');

        // 5. Con el mismo enlace puede volver a consultar, pero no responder otra vez.
        $this->flushSession();
        $this->get($url)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect();
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion->id)->count());
    }

    public function test_con_el_enlace_de_contacto_inicial_el_expediente_ofrece_tambien_la_encuesta_pendiente(): void
    {
        Mail::fake();
        $egresado = $this->crearEgresado(['nombres' => 'Elena', 'apellido_paterno' => 'Quispe', 'apellido_materno' => 'Rojas']);
        $this->darPorEgresado($egresado);
        $cohorte = (int) DB::table('seguimiento_egresado.egresado')->where('id', $egresado)->value('id_cohorte');
        $campania = $this->crearCampania(['fecha_inicio' => now()->subDays(5)->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString()]);
        DB::table('seguimiento_egresado.campania_cohorte')->insert(['id_campania' => $campania, 'id_cohorte' => $cohorte]);
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania))->assertRedirect();
        $encuesta = (int) DB::table('seguimiento_egresado.invitacion_egresado')->where('id_egresado', $egresado)->value('id');

        // La Secretaría le genera el enlace de contacto inicial desde el padrón.
        $this->withSession(['seguimiento-egresado.identidad' => ['perfil' => 'SECRETARIA', 'usuario_id' => 1]])
            ->post(route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado))->assertRedirect();
        $url = session('enlaces_contacto')[0]['url'];

        // Con ese enlace deja su contacto y, desde el mismo expediente, responde la encuesta que tenía pendiente.
        $this->flushSession();
        $this->get($url)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertOk()
            ->assertSee('Completa tus datos de contacto')
            ->assertSee('Tienes una encuesta por responder')
            ->assertSee(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $encuesta]), false);

        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), ['correo_personal' => 'elena@correo.test', 'celular_whatsapp' => '+51 900 123 456'])
            ->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertOk()->assertDontSee('Completa tus datos de contacto')->assertSee('Tienes una encuesta por responder');

        $this->get(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $encuesta]))->assertOk()->assertSee('Confirma tus datos de contacto');
        $datos = $this->datosDeEncuesta(['brechas' => ['CLOUD'], 'fortalezas' => [], 'detalle_brecha_otra' => null])
            + ['contacto' => ['correo_personal' => 'elena@correo.test', 'celular_whatsapp' => '+51 900 123 456'], 'invitacion' => $encuesta];
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $this->assertSame('COMPLETADA', DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $encuesta)->value('estado'));
        $this->assertSame('COMPLETADA', DB::table('seguimiento_egresado.invitacion_egresado')->where('id_egresado', $egresado)->where('proposito', 'CONTACTO_INICIAL')->value('estado'));
    }
}
