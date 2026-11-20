<?php

namespace Tests\Feature\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Middleware\RequiereCapacidad;
use App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * El middleware con rutas de prueba propias (no depende de las rutas del módulo). El personal entra con una cuenta del
 * portal con rol de Secretaría o Dirección; el egresado, con la sesión de su enlace personal.
 */
class RequiereCapacidadTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    private const AHORA = '2026-11-10 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse(self::AHORA));

        Route::middleware(['web', RequiereCapacidad::class.':padron.gestionar'])->get('/_t/padron-gestion', fn () => 'ok');
        Route::middleware(['web', RequiereCapacidad::class.':seguimiento.propio'])->get('/_t/seguimiento-propio', fn () => 'ok');
        Route::middleware(['web', RequiereCapacidad::class.':padron.consultar'])->get('/_t/padron-consulta', fn () => 'ok');
        Route::middleware(['web', RequiereCapacidad::class.':seguimiento.propio,seguimiento.consultar'])->get('/_t/seguimiento', fn () => 'ok');
        Route::middleware(['web', RequiereCapacidad::class.':capacidad.inexistente'])->get('/_t/typo', fn () => 'ok');
        Route::middleware(['web', RequiereCapacidad::class.':expediente.propio,expediente.gestionar,expediente.consultar'])
            ->get('/_t/expediente/{egresado}', function (int $egresado) {
                $identidad = SesionAcceso::desde(request())->actual();
                abort_unless((new ExpedientePolicy)->ver($identidad, $egresado), 403);

                return 'expediente '.$egresado;
            });
    }

    /** Sesión del enlace personal de un egresado. */
    private function conEnlace(int $egresadoId = 42, ?string $vence = null): static
    {
        return $this->withSession(['seguimiento-egresado.identidad' => [
            'perfil' => 'EGRESADO', 'egresado_id' => $egresadoId, 'invitacion_id' => 7,
            'vence_en' => $vence ?? CarbonImmutable::parse(self::AHORA)->addDay()->toIso8601String(),
        ]]);
    }

    public function test_sin_sesion_se_responde_401_en_una_ruta_privada(): void
    {
        $this->getJson('/_t/padron-consulta')->assertStatus(401);
        $this->get('/_t/padron-consulta')->assertStatus(401);
    }

    public function test_sin_sesion_en_una_ruta_del_egresado_se_envia_a_la_pantalla_de_acceso(): void
    {
        $this->get('/_t/seguimiento-propio')->assertRedirect(route('seguimiento-egresado.acceso'));
        $this->getJson('/_t/seguimiento-propio')->assertStatus(401);
    }

    public function test_secretaria_gestiona_padron(): void
    {
        $this->actingAs($this->secretaria())->get('/_t/padron-gestion')->assertOk();
    }

    public function test_el_director_recibe_403_al_gestionar_padron_pero_puede_consultarlo(): void
    {
        $this->actingAs($this->director())->get('/_t/padron-gestion')->assertForbidden();
        $this->actingAs($this->director())->get('/_t/padron-consulta')->assertOk();
    }

    public function test_un_usuario_del_portal_sin_rol_en_el_modulo_recibe_403(): void
    {
        $this->actingAs($this->profesor())->get('/_t/padron-consulta')->assertForbidden();
        $this->actingAs($this->profesor())->get('/_t/padron-gestion')->assertForbidden();
    }

    public function test_el_egresado_con_su_enlace_recibe_403_en_cualquier_ruta_interna(): void
    {
        $this->conEnlace()->get('/_t/padron-consulta')->assertForbidden();
        $this->conEnlace()->get('/_t/padron-gestion')->assertForbidden();
    }

    public function test_basta_una_de_las_capacidades_indicadas(): void
    {
        $this->conEnlace()->get('/_t/seguimiento')->assertOk();
        $this->flushSession();
        $this->actingAs($this->secretaria())->get('/_t/seguimiento')->assertOk();
        $this->actingAs($this->director())->get('/_t/seguimiento')->assertForbidden();
    }

    public function test_la_sesion_vencida_del_egresado_equivale_a_no_tener_sesion(): void
    {
        $vencida = CarbonImmutable::parse(self::AHORA)->subMinute()->toIso8601String();

        $this->conEnlace(42, $vencida)->getJson('/_t/seguimiento')->assertStatus(401);
    }

    public function test_una_identidad_manipulada_con_perfil_desconocido_no_pasa(): void
    {
        $this->withSession(['seguimiento-egresado.identidad' => ['perfil' => 'ADMINISTRADOR']])->getJson('/_t/padron-consulta')->assertStatus(401);
    }

    public function test_el_perfil_de_secretaria_guardado_en_la_sesion_no_da_acceso_interno(): void
    {
        // Solo cuenta quien inició sesión en el portal: un dato en la sesión no basta para ser Secretaría.
        $this->withSession(['seguimiento-egresado.identidad' => ['perfil' => 'SECRETARIA', 'usuario_id' => 1]])
            ->getJson('/_t/padron-gestion')->assertStatus(401);
    }

    public function test_una_capacidad_mal_escrita_falla_en_voz_alta_y_no_abre_la_ruta(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(\LogicException::class);

        $this->actingAs($this->secretaria())->get('/_t/typo');
    }

    public function test_el_egresado_accede_por_url_directa_solo_a_su_expediente(): void
    {
        $this->conEnlace(42)->get('/_t/expediente/42')->assertOk()->assertSee('expediente 42');
        $this->conEnlace(42)->get('/_t/expediente/43')->assertForbidden();
    }

    public function test_secretaria_y_director_pueden_consultar_cualquier_expediente_por_url(): void
    {
        $this->actingAs($this->secretaria())->get('/_t/expediente/43')->assertOk();
        $this->actingAs($this->director())->get('/_t/expediente/43')->assertOk();
    }

    public function test_el_personal_con_sesion_del_portal_y_el_enlace_de_un_egresado_usa_cada_identidad_donde_corresponde(): void
    {
        // La Secretaría abre en su navegador el enlace de un egresado para ver lo que él ve.
        $this->actingAs($this->secretaria());

        $this->conEnlace(42)->get('/_t/padron-gestion')->assertOk();
        $this->conEnlace(42)->get('/_t/seguimiento-propio')->assertOk();
        $this->conEnlace(42)->get('/_t/expediente/43')->assertOk();
    }

    public function test_una_ruta_solo_del_egresado_con_personal_sin_enlace_explica_como_entrar(): void
    {
        $this->actingAs($this->secretaria())->get('/_t/seguimiento-propio')->assertRedirect(route('seguimiento-egresado.acceso'));
    }
}
