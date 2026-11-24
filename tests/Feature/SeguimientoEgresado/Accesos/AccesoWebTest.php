<?php

namespace Tests\Feature\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

/** Entrada directa del egresado por su enlace personal. */
class AccesoWebTest extends TestCase
{
    use FabricaSeguimiento, UsaEsquemaPostgres;

    private const TOKEN = 'token-de-prueba-ABCDEF123456';

    private const ENLACE = '/seguimiento-egresado/acceso/'.self::TOKEN;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPostgres();
    }

    protected function tearDown(): void
    {
        $this->revertirPostgres();
        parent::tearDown();
    }

    /** @return array{0:int,1:int} egresado e invitación con el token de la prueba */
    private function invitado(array $invitacion = [], ?int $campania = null): array
    {
        $egresado = $this->crearEgresado();

        return [$egresado, $this->crearInvitacion($egresado, $campania ?? $this->crearCampania(), ['token_hash' => EnlaceInvitacion::hashDe(self::TOKEN)] + $invitacion)];
    }

    public function test_el_enlace_valido_entra_directo_al_expediente_propio_sin_pedir_datos(): void
    {
        [$egresado, $invitacion] = $this->invitado();

        $this->get(self::ENLACE)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $identidad = session('seguimiento-egresado.identidad');
        $this->assertSame(['perfil' => 'EGRESADO', 'egresado_id' => $egresado, 'invitacion_id' => $invitacion], collect($identidad)->only(['perfil', 'egresado_id', 'invitacion_id'])->all());
        $this->assertSame([], array_diff(array_keys($identidad), ['perfil', 'egresado_id', 'invitacion_id', 'vence_en']), 'la sesión no guarda matrícula ni documento');
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertOk()->assertSee('Tienes una encuesta por responder');
    }

    public function test_el_enlace_de_contacto_inicial_lleva_a_completar_el_contacto(): void
    {
        $egresado = $this->crearEgresado();
        $this->crearInvitacion($egresado, null, ['token_hash' => EnlaceInvitacion::hashDe(self::TOKEN)]);

        $this->get(self::ENLACE)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->get(route('seguimiento-egresado.egresado.inicio'))->assertOk()->assertSee('Completa tus datos de contacto');
    }

    public function test_una_invitacion_completada_sigue_permitiendo_ver_el_expediente(): void
    {
        $this->invitado(['estado' => 'COMPLETADA']);

        $this->get(self::ENLACE)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
    }

    #[DataProvider('enlacesSinAcceso')]
    public function test_un_enlace_desconocido_anulado_o_vencido_muestra_el_mismo_aviso_y_no_abre_sesion(?array $invitacion): void
    {
        if ($invitacion !== null) {
            $this->invitado($invitacion);
        }

        $this->get(self::ENLACE)->assertNotFound()->assertSee('Este enlace no es válido o ya venció');
        $this->assertNull(session('seguimiento-egresado.identidad'));
    }

    /** @return iterable<string, array{array<string, mixed>|null}> */
    public static function enlacesSinAcceso(): iterable
    {
        yield 'enlace desconocido' => [null];
        yield 'anulado' => [['estado' => 'ANULADA']];
        yield 'expirado' => [['estado' => 'EXPIRADA']];
        yield 'vencido por fecha' => [['expira_en' => '2020-01-01 00:00:00+00']];
    }

    public function test_sin_haber_abierto_el_enlace_el_area_del_egresado_lleva_a_la_pantalla_de_acceso(): void
    {
        $this->get('/seguimiento-egresado/egresado/historial')->assertRedirect(route('seguimiento-egresado.acceso'));
        $this->get(route('seguimiento-egresado.acceso'))->assertOk()->assertSee('Entra con tu enlace personal');
    }

    public function test_no_existen_formularios_de_inicio_de_sesion(): void
    {
        foreach (['/seguimiento-egresado/ingresar', '/seguimiento-egresado/recuperar', '/seguimiento-egresado/restablecer/x'] as $ruta) {
            $this->get($ruta)->assertNotFound();
        }
        $this->post('/seguimiento-egresado/ingresar')->assertStatus(404);
    }

    public function test_cada_enlace_abre_solo_el_expediente_de_su_dueno(): void
    {
        [$unoId] = $this->invitado();
        $otro = $this->crearEgresado();
        $this->crearInvitacion($otro, $this->crearCampania(), ['token_hash' => EnlaceInvitacion::hashDe('otro-token-distinto-9876543210')]);

        $this->get(self::ENLACE);
        $this->assertSame($unoId, session('seguimiento-egresado.identidad')['egresado_id']);

        $this->flushSession();
        $this->get('/seguimiento-egresado/acceso/otro-token-distinto-9876543210');
        $this->assertSame($otro, session('seguimiento-egresado.identidad')['egresado_id']);
    }
}
