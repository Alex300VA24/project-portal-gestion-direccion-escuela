<?php

namespace Tests\Feature\SeguimientoEgresado\Invitaciones;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Mail\InvitacionEgresadoMail;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\ContactoEgresado;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** La Secretaría genera el enlace de contacto inicial desde el padrón; el egresado lo abre y deja sus datos. */
class ContactoInicialTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    private Cohorte $cohorte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatosPruebaSeeder::class);
        config(['mail.default' => 'smtp', 'seguimiento-egresado.correo.enviar' => true, 'seguimiento-egresado.correo.redirigir_a' => null]);
        Mail::fake();

        // Una cohorte con egresados, todos sin contacto y con correo institucional.
        $this->cohorte = Cohorte::whereHas('egresados')->firstOrFail();
        ContactoEgresado::whereIn('id_egresado', $this->cohorte->egresados()->pluck('id'))->delete();
        InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->delete();
        foreach ($this->cohorte->egresados as $n => $egresado) {
            $egresado->update(['correo_institucional' => "egresado{$n}@unitru.edu.pe"]);
        }
    }

    private function comoSecretaria(): static
    {
        return $this->actingAs($this->secretaria());
    }

    private function egresado(): Egresado
    {
        return $this->cohorte->egresados()->orderBy('id')->firstOrFail();
    }

    public function test_la_secretaria_genera_un_enlace_que_se_muestra_una_vez_y_se_envia_por_correo(): void
    {
        $egresado = $this->egresado();

        $this->comoSecretaria()->post(route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id))
            ->assertRedirect();

        $enlace = session('enlaces_contacto')[0];
        $token = basename($enlace['url']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token);
        $this->assertSame('enviado', $enlace['correo']);

        $invitacion = InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->where('id_egresado', $egresado->id)->firstOrFail();
        $this->assertSame('CONTACTO_INICIAL', $invitacion->proposito);
        $this->assertNull($invitacion->id_campania);
        $this->assertSame('PENDIENTE', $invitacion->estado);
        $this->assertSame(EnlaceInvitacion::hashDe($token), $invitacion->token_hash, 'en la BD queda solo el hash');
        $this->assertNotNull($invitacion->enviado_en);
        Mail::assertSent(InvitacionEgresadoMail::class, fn ($m) => $m->url === $enlace['url'] && $m->proposito === 'CONTACTO_INICIAL');

        // La página lo muestra una vez y después solo queda el estado.
        $this->comoSecretaria()->followingRedirects()->post(route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id))
            ->assertSee('Enlaces personales de contacto inicial')->assertSee('/seguimiento-egresado/acceso/', false);
        $this->comoSecretaria()->get(route('seguimiento-egresado.padron.contacto-inicial'))
            ->assertOk()->assertDontSee('Enlaces personales de contacto inicial')->assertSee('Enlace nuevo')->assertSee('Correo enviado');
    }

    public function test_sin_servidor_de_correo_el_enlace_sale_en_pantalla_y_no_se_dice_enviado(): void
    {
        config(['mail.default' => 'log']);
        $egresado = $this->egresado();

        $this->comoSecretaria()->followingRedirects()->post(route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id))
            ->assertOk()
            ->assertSee('/seguimiento-egresado/acceso/', false)
            ->assertSee('Entrega a cada egresado su enlace de abajo.')
            ->assertDontSee('Enviado por correo a')
            ->assertDontSee('Todos se enviaron');

        $this->assertNull(InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->where('id_egresado', $egresado->id)->value('enviado_en'));
    }

    public function test_enlace_nuevo_anula_el_anterior_y_el_viejo_deja_de_servir(): void
    {
        $egresado = $this->egresado();
        $servicio = app(ContactoInicialService::class);

        $primero = $servicio->invitar($egresado)['url'];
        $segundo = $servicio->invitar($egresado)['url'];

        $this->assertNotSame($primero, $segundo);
        $this->assertSame(['ANULADA', 'PENDIENTE'], InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->where('id_egresado', $egresado->id)->orderBy('id')->pluck('estado')->all());
        $this->get($primero)->assertNotFound();
        $this->get($segundo)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
    }

    public function test_invitar_una_cohorte_omite_a_quien_ya_dejo_contacto_o_ya_tiene_enlace(): void
    {
        $egresados = $this->cohorte->egresados()->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(3, $egresados->count(), 'la cohorte de prueba debe tener al menos tres egresados');

        ContactoEgresado::create([
            'id_egresado' => $egresados[0]->id, 'correo_personal' => 'ya@correo.test', 'celular_whatsapp' => '+51900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);
        app(ContactoInicialService::class)->invitar($egresados[1]);
        $previos = InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->count();

        $this->comoSecretaria()->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => $this->cohorte->id])->assertRedirect();

        $enlaces = session('enlaces_contacto');
        $this->assertCount($egresados->count() - 2, $enlaces);
        $this->assertSame($previos + count($enlaces), InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->count());
        $this->assertStringContainsString('Se omitieron 1 que ya dejaron su contacto', session('success'));
        $this->assertStringContainsString('Se omitieron 1 que ya tienen un enlace pendiente', session('success'));
    }

    public function test_el_egresado_abre_su_enlace_deja_su_contacto_y_la_invitacion_queda_completada(): void
    {
        $egresado = $this->egresado();
        $url = app(ContactoInicialService::class)->invitar($egresado)['url'];

        $this->get($url)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.contacto'))->assertOk();
        $this->post(route('seguimiento-egresado.egresado.contacto.guardar'), [
            'correo_personal' => 'nuevo@correo.test', 'celular_whatsapp' => '+51 900 123 456',
        ])->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $this->assertSame('nuevo@correo.test', ContactoEgresado::where('id_egresado', $egresado->id)->whereNull('vigente_hasta')->value('correo_personal'));
        $this->assertSame('COMPLETADA', InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->where('id_egresado', $egresado->id)->value('estado'));

        $this->comoSecretaria()->get(route('seguimiento-egresado.padron.contacto-inicial', ['estado' => 'todos']))->assertOk()->assertSee('Completado');
    }

    public function test_el_listado_filtra_por_contacto_y_cohorte(): void
    {
        $egresado = $this->egresado();
        ContactoEgresado::create([
            'id_egresado' => $egresado->id, 'correo_personal' => 'ya@correo.test', 'celular_whatsapp' => '+51900111222',
            'vigente_desde' => now(), 'origen_registro' => 'FORMULARIO',
        ]);

        $sin = $this->comoSecretaria()->get(route('seguimiento-egresado.padron.contacto-inicial', ['cohorte' => $this->cohorte->id]))->assertOk();
        $sin->assertDontSee($egresado->codigo_matricula)->assertSee($this->cohorte->egresados()->where('id', '!=', $egresado->id)->value('codigo_matricula'));

        $this->comoSecretaria()->get(route('seguimiento-egresado.padron.contacto-inicial', ['cohorte' => $this->cohorte->id, 'estado' => 'con_contacto']))
            ->assertOk()->assertSee($egresado->codigo_matricula);
    }

    public function test_un_filtro_invalido_se_rechaza(): void
    {
        $this->comoSecretaria()->get(route('seguimiento-egresado.padron.contacto-inicial', ['cohorte' => 'abc']))->assertSessionHasErrors('cohorte');
        $this->comoSecretaria()->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), [])->assertSessionHasErrors('cohorte');
        $this->comoSecretaria()->post(route('seguimiento-egresado.padron.contacto-inicial.cohorte'), ['cohorte' => 999999])->assertSessionHasErrors('cohorte');
    }

    public function test_solo_la_secretaria_genera_enlaces_y_nadie_sin_sesion_entra(): void
    {
        $egresado = $this->egresado();
        $ruta = route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id);

        $this->postJson($ruta)->assertUnauthorized();
        $this->actingAs($this->director())->post($ruta)->assertForbidden();
        $this->actingAs($this->director())->get(route('seguimiento-egresado.padron.contacto-inicial'))->assertOk();
        $this->assertSame(0, InvitacionEgresado::where('proposito', 'CONTACTO_INICIAL')->count());
    }
}
