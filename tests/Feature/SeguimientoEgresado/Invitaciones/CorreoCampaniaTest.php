<?php

namespace Tests\Feature\SeguimientoEgresado\Invitaciones;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Las campañas envían el enlace por correo y la pantalla dice la verdad sobre lo enviado. */
class CorreoCampaniaTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    private CampaniaSeguimiento $campania;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->seed(DatosPruebaSeeder::class);
        config(['mail.default' => 'smtp', 'seguimiento-egresado.correo.enviar' => true, 'seguimiento-egresado.correo.redirigir_a' => null]);
        Mail::fake();

        $this->campania = CampaniaSeguimiento::create([
            'nombre' => 'Encuesta Cohorte 2019', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-12-31', 'estado' => 'ABIERTA', 'id_usuario_creacion' => UsuarioInterno::idActual(),
        ]);
        $cohorteId = Cohorte::where('anio_ingreso', 2019)->value('id');
        $this->campania->cohortes()->attach($cohorteId);

        // Las campañas se envían al correo personal: los egresados de la promoción ya lo dejaron.
        foreach (\App\Modules\SeguimientoEgresado\Models\Egresado::where('id_cohorte', $cohorteId)->get() as $egresado) {
            \App\Modules\SeguimientoEgresado\Models\ContactoEgresado::firstOrCreate(['id_egresado' => $egresado->id, 'vigente_hasta' => null], [
                'correo_personal' => "personal{$egresado->id}@correo.test", 'celular_whatsapp' => '+51900111222', 'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
            ]);
        }
    }

    public function test_al_emitir_se_envia_un_correo_por_invitacion_y_se_marca_la_fecha_de_envio(): void
    {
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $this->campania->id))->assertRedirect();

        $enlaces = session('enlaces_emitidos');
        $this->assertNotEmpty($enlaces);
        Mail::assertSent(InvitacionEgresadoMail::class, count($enlaces));
        foreach ($enlaces as $enlace) {
            $this->assertSame('enviado', $enlace['correo']);
            Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->url === $enlace['url'] && $m->proposito === 'SEGUIMIENTO');
        }
        $this->assertSame(0, InvitacionEgresado::where('id_campania', $this->campania->id)->whereNull('enviado_en')->count());
        $this->assertStringContainsString('Todas salieron por correo', session('success'));
    }

    public function test_sin_servidor_de_correo_no_se_marca_enviado_y_el_enlace_sigue_en_pantalla(): void
    {
        config(['mail.default' => 'log']);

        $this->followingRedirects()->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $this->campania->id))
            ->assertOk()
            ->assertSee('Entrega a cada egresado su enlace de abajo.')
            ->assertDontSee('Enviado por correo a')
            ->assertSee('/seguimiento-egresado/acceso/', false);

        $this->assertGreaterThan(0, InvitacionEgresado::where('id_campania', $this->campania->id)->count());
        $this->assertSame(0, InvitacionEgresado::where('id_campania', $this->campania->id)->whereNotNull('enviado_en')->count(), 'nada salió a un servidor de correo');
    }

    public function test_el_recordatorio_genera_un_enlace_nuevo_y_lo_envia(): void
    {
        $this->post(route('seguimiento-egresado.campanias.emitir-invitaciones', $this->campania->id));
        $invitacion = InvitacionEgresado::where('id_campania', $this->campania->id)->orderBy('id')->firstOrFail();
        $hashAnterior = $invitacion->token_hash;
        Mail::fake();

        $this->post(route('seguimiento-egresado.campanias.recordatorio', $invitacion->id))->assertRedirect();

        $this->assertNotSame($hashAnterior, $invitacion->refresh()->token_hash);
        Mail::assertSent(InvitacionEgresadoMail::class, 1);
        $this->assertStringContainsString('enviado por correo', session('success'));
    }
}
