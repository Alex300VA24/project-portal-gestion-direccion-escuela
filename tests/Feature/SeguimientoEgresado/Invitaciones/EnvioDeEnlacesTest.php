<?php

namespace Tests\Feature\SeguimientoEgresado\Invitaciones;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\EnvioDeEnlaces;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\EstadoEnvio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** El envío por correo dice la verdad: solo se marca enviado lo que el servidor de correo aceptó. */
class EnvioDeEnlacesTest extends TestCase
{
    use UsaBaseDeDatos;

    private Egresado $egresado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);

        // Las pruebas corren con MAIL_MAILER=array; aquí se simula un servidor real (Mail::fake intercepta el envío).
        config(['mail.default' => 'smtp', 'seguimiento-egresado.correo.enviar' => true, 'seguimiento-egresado.correo.redirigir_a' => null]);

        $this->egresado = Egresado::query()->firstOrFail();
        $this->egresado->update(['correo_institucional' => 'egresado.prueba@unitru.edu.pe']);
        ContactoEgresado::where('id_egresado', $this->egresado->id)->delete();
    }

    private function invitacion(): InvitacionEgresado
    {
        return InvitacionEgresado::create([
            'id_campania' => null, 'id_egresado' => $this->egresado->id, 'proposito' => 'CONTACTO_INICIAL',
            'token_hash' => hash('sha256', 'token-de-prueba'), 'expira_en' => now()->addDays(30),
            'recordatorios_enviados' => 0, 'entrega_fallida' => false, 'estado' => 'PENDIENTE',
        ]);
    }

    public function test_un_envio_real_manda_el_correo_con_el_enlace_y_marca_la_invitacion_como_enviada(): void
    {
        Mail::fake();
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Enviado, $resultado->estado);
        Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->hasTo('egresado.prueba@unitru.edu.pe')
            && $m->url === url('seguimiento-egresado/acceso/token-de-prueba'));

        $invitacion->refresh();
        $this->assertNotNull($invitacion->enviado_en);
        $this->assertFalse((bool) $invitacion->entrega_fallida);
        $this->assertSame(
            DB::table('seguimiento_egresado.cat_canal_envio')->where('codigo', 'CORREO_INSTITUCIONAL')->value('id'),
            $invitacion->id_canal_envio,
        );
    }

    public function test_la_hora_de_envio_no_depende_de_la_zona_horaria_de_la_base(): void
    {
        Mail::fake();
        // La base puede correr en otra zona que la aplicación (UTC): la hora guardada debe seguir siendo el momento real del envío.
        DB::statement("SET TIME ZONE 'America/Lima'");
        $invitacion = $this->invitacion();

        try {
            app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');
            $desfase = abs((float) DB::table('seguimiento_egresado.invitacion_egresado')->where('id', $invitacion->id)
                ->selectRaw('extract(epoch from enviado_en) - extract(epoch from now()) as d')->value('d'));
        } finally {
            DB::statement("SET TIME ZONE 'UTC'");
        }

        $this->assertLessThan(60, $desfase, 'La hora de envío quedó desfasada respecto al momento real.');
    }

    public function test_con_lista_de_permitidos_solo_se_envia_a_esas_direcciones(): void
    {
        Mail::fake();
        config(['seguimiento-egresado.correo.permitidos' => ['otro.permitido@correo.test']]);
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Desactivado, $resultado->estado);
        Mail::assertNothingSent();
        $this->assertNull($invitacion->refresh()->enviado_en);

        config(['seguimiento-egresado.correo.permitidos' => ['egresado.prueba@unitru.edu.pe']]);
        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Enviado, $resultado->estado);
        Mail::assertSent(InvitacionEgresadoMail::class, 1);
    }

    public function test_el_contacto_inicial_va_al_correo_institucional_aunque_ya_haya_un_personal(): void
    {
        Mail::fake();
        ContactoEgresado::create([
            'id_egresado' => $this->egresado->id, 'correo_personal' => 'personal@correo.test', 'celular_whatsapp' => '+51900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);

        app(EnvioDeEnlaces::class)->enviar($this->invitacion(), 'token-de-prueba');

        Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->hasTo('egresado.prueba@unitru.edu.pe') && ! $m->hasTo('personal@correo.test'));
    }

    public function test_las_campanas_van_solo_al_correo_personal_y_sin_el_no_se_envia(): void
    {
        Mail::fake();
        $campania = \App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento::create([
            'nombre' => 'Campaña de envío', 'tipo' => 'EGRESADOS', 'finalidad' => 'SEGUIMIENTO', 'periodo' => '2026-II',
            'fecha_inicio' => now()->subDay()->toDateString(), 'fecha_fin' => now()->addDays(30)->toDateString(), 'estado' => 'ABIERTA', 'id_usuario_creacion' => \App\Modules\SeguimientoEgresado\Models\UsuarioInterno::idActual(),
        ])->id;
        $seguimiento = InvitacionEgresado::create([
            'id_campania' => $campania, 'id_egresado' => $this->egresado->id, 'proposito' => 'SEGUIMIENTO',
            'token_hash' => hash('sha256', 'token-seguimiento'), 'expira_en' => now()->addDays(30),
            'recordatorios_enviados' => 0, 'entrega_fallida' => false, 'estado' => 'PENDIENTE',
        ]);

        $sin = app(EnvioDeEnlaces::class)->enviar($seguimiento, 'token-seguimiento');
        $this->assertSame(EstadoEnvio::SinCorreo, $sin->estado, 'el correo institucional no se usa para las campañas');
        Mail::assertNothingSent();

        ContactoEgresado::create([
            'id_egresado' => $this->egresado->id, 'correo_personal' => 'personal@correo.test', 'celular_whatsapp' => '+51900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);
        $this->egresado->refresh();
        $con = app(EnvioDeEnlaces::class)->enviar($seguimiento->refresh(), 'token-seguimiento');

        $this->assertSame(EstadoEnvio::Enviado, $con->estado);
        Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->hasTo('personal@correo.test'));
        $this->assertSame(DB::table('seguimiento_egresado.cat_canal_envio')->where('codigo', 'CORREO_PERSONAL')->value('id'), $seguimiento->refresh()->id_canal_envio);
    }

    public function test_con_redireccion_todo_el_correo_va_a_la_direccion_indicada(): void
    {
        Mail::fake();
        config(['seguimiento-egresado.correo.redirigir_a' => 'demo@correo.test']);
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->hasTo('demo@correo.test') && ! $m->hasTo('egresado.prueba@unitru.edu.pe'));
        $this->assertTrue($resultado->redirigido);
        $this->assertStringContainsString('(redirigido)', $resultado->texto());
        $this->assertNotNull($invitacion->refresh()->enviado_en);
    }

    public function test_si_el_servidor_falla_se_marca_la_entrega_fallida_y_no_se_dice_enviado(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new \RuntimeException('Servidor SMTP caído'));
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Fallido, $resultado->estado);
        $invitacion->refresh();
        $this->assertNull($invitacion->enviado_en);
        $this->assertTrue((bool) $invitacion->entrega_fallida);
    }

    public function test_con_el_registro_como_servidor_no_se_marca_como_enviado(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Simulado, $resultado->estado);
        $this->assertFalse($resultado->enviado());
        $this->assertNull($invitacion->refresh()->enviado_en);
    }

    public function test_con_el_envio_desactivado_no_sale_ningun_correo(): void
    {
        Mail::fake();
        config(['seguimiento-egresado.correo.enviar' => false]);
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::Desactivado, $resultado->estado);
        Mail::assertNothingSent();
        $this->assertNull($invitacion->refresh()->enviado_en);
    }

    public function test_sin_correo_registrado_no_se_envia_nada(): void
    {
        Mail::fake();
        $this->egresado->update(['correo_institucional' => null]);
        $invitacion = $this->invitacion();

        $resultado = app(EnvioDeEnlaces::class)->enviar($invitacion, 'token-de-prueba');

        $this->assertSame(EstadoEnvio::SinCorreo, $resultado->estado);
        Mail::assertNothingSent();
        $this->assertNull($invitacion->refresh()->enviado_en);
    }
}
