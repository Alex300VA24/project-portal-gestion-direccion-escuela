<?php

namespace Tests\Feature\SeguimientoEgresado\Invitaciones;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * Un mismo enlace del egresado sirve para dejar su contacto y para responder las encuestas que tenga pendientes
 * (la lista de pendientes del inicio usa una vista de PostgreSQL y se ve en FlujoCompletoPostgresTest).
 */
class EncuestaConEnlaceDeContactoTest extends TestCase
{
    use ComoPersonal, FabricaSeguimiento, UsaBaseDeDatos;

    private Egresado $egresado;

    private InvitacionEgresado $encuesta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
        Mail::fake();

        $campania = CampaniaSeguimiento::create([
            'nombre' => 'Seguimiento de prueba', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDays(5)->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(),
            'estado' => 'ABIERTA', 'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $campania->cohortes()->attach(Cohorte::where('anio_ingreso', 2019)->value('id'));
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id))->assertRedirect();

        $this->encuesta = InvitacionEgresado::where('id_campania', $campania->id)->orderBy('id')->firstOrFail();
        $this->egresado = Egresado::findOrFail($this->encuesta->id_egresado);
        ContactoEgresado::where('id_egresado', $this->egresado->id)->delete();
    }

    private function entrarConEnlaceDeContacto(): void
    {
        $url = app(ContactoInicialService::class)->invitar($this->egresado)['url'];
        $this->get($url)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
    }

    public function test_con_el_enlace_de_contacto_se_responde_la_encuesta_pendiente_elegida(): void
    {
        $this->entrarConEnlaceDeContacto();

        // Sin elegir encuesta, vuelve al expediente donde se listan las pendientes.
        $this->get(route('seguimiento-egresado.egresado.encuesta'))->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $this->get(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $this->encuesta->id]))
            ->assertOk()
            ->assertSee('Encuesta de seguimiento a egresados')
            ->assertSee('name="invitacion" value="'.$this->encuesta->id.'"', false);

        $datos = $this->datosDeEncuesta(['brechas' => ['CLOUD'], 'fortalezas' => [], 'detalle_brecha_otra' => null])
            + ['contacto' => ['correo_personal' => 'respuesta@correo.test', 'celular_whatsapp' => '+51 900 123 456'], 'invitacion' => $this->encuesta->id];
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $this->assertSame('COMPLETADA', $this->encuesta->refresh()->estado);
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $this->encuesta->id)->count());
        $this->assertSame('respuesta@correo.test', ContactoEgresado::where('id_egresado', $this->egresado->id)->whereNull('vigente_hasta')->value('correo_personal'));
    }

    public function test_no_se_puede_responder_la_invitacion_de_otro_egresado(): void
    {
        $this->entrarConEnlaceDeContacto();
        $ajena = InvitacionEgresado::where('id_campania', $this->encuesta->id_campania)->where('id_egresado', '!=', $this->egresado->id)->firstOrFail();

        $this->get(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $ajena->id]))->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $datos = $this->datosDeEncuesta(['brechas' => ['CLOUD'], 'fortalezas' => [], 'detalle_brecha_otra' => null])
            + ['contacto' => ['correo_personal' => 'intruso@correo.test', 'celular_whatsapp' => '+51 900 123 456'], 'invitacion' => $ajena->id];
        $this->post(route('seguimiento-egresado.egresado.encuesta.responder'), $datos)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $this->assertSame('PENDIENTE', $ajena->refresh()->estado);
        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $ajena->id)->count());
        $this->assertNull(ContactoEgresado::where('correo_personal', 'intruso@correo.test')->first());
    }

    public function test_la_encuesta_pide_confirmar_el_contacto_si_ya_lo_dejo(): void
    {
        $this->entrarConEnlaceDeContacto();
        $this->get(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $this->encuesta->id]))->assertOk()->assertSee('1. Tus datos de contacto')->assertDontSee('Confirma tus datos');

        ContactoEgresado::create([
            'id_egresado' => $this->egresado->id, 'correo_personal' => 'ya@correo.test', 'celular_whatsapp' => '+51900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);

        $this->get(route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $this->encuesta->id]))->assertOk()
            ->assertSee('1. Confirma tus datos de contacto')->assertSee('ya@correo.test', false);
    }
}
