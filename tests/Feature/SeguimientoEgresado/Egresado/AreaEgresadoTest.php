<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

/**
 * Recorrido HTTP del egresado (S3-22) sobre el esquema vigente. La identidad se entrega por sesión
 * (contrato de SesionAcceso), sin depender del acceso por enlace, que es solo de pruebas locales.
 */
class AreaEgresadoTest extends TestCase
{
    use ComoPersonal, FabricaSeguimiento, UsaEsquemaPostgres;

    private const BASE = '/seguimiento-egresado/egresado';

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

    private function comoEgresado(int $egresadoId, ?int $invitacionId): static
    {
        return $this->withSession(['seguimiento-egresado.identidad' => [
            'perfil' => Perfil::Egresado->value,
            'egresado_id' => $egresadoId,
            'invitacion_id' => $invitacionId,
            'vence_en' => now()->addDay()->toIso8601String(),
        ]]);
    }

    /** @return array{0:int,1:int} egresado e invitación de seguimiento pendiente */
    private function conEncuestaPendiente(): array
    {
        $egresado = $this->crearEgresado();

        return [$egresado, $this->crearInvitacion($egresado, $this->crearCampania())];
    }

    private function respuesta(array $cambios = []): array
    {
        return $this->datosDeEncuesta($cambios) + ['contacto' => ['correo_personal' => 'persona@correo.test', 'celular_whatsapp' => '+51 900 000 001']];
    }

    public function test_sin_sesion_no_se_accede_a_ninguna_pagina(): void
    {
        foreach (['', '/contacto', '/encuesta', '/historial'] as $ruta) {
            $this->assertContains($this->get(self::BASE.$ruta)->getStatusCode(), [302, 401], "Sin sesión, {$ruta} debe pedir acceso");
        }
    }

    #[DataProvider('perfilesInternos')]
    public function test_el_personal_interno_no_entra_al_area_del_egresado(Perfil $perfil): void
    {
        // El personal entra con su cuenta del portal, no con un enlace: sin enlace se le explica cómo entrar.
        $this->actingAs($perfil === Perfil::Director ? $this->director() : $this->secretaria());

        foreach (['', '/contacto', '/encuesta', '/historial'] as $ruta) {
            $this->get(self::BASE.$ruta)->assertRedirect(route('seguimiento-egresado.acceso'));
        }
    }

    /** @return iterable<string, array{Perfil}> */
    public static function perfilesInternos(): iterable
    {
        yield 'Secretaría' => [Perfil::Secretaria];
        yield 'Director' => [Perfil::Director];
    }

    public function test_el_inicio_muestra_solo_el_expediente_propio(): void
    {
        [$egresado, $invitacion] = $this->conEncuestaPendiente();
        $this->crearEgresado(['nombres' => 'Otra', 'apellido_paterno' => 'Persona']);

        $this->comoEgresado($egresado, $invitacion)->get(self::BASE)
            ->assertOk()->assertSee('Tienes una encuesta por responder')->assertDontSee('Otra Persona');
    }

    public function test_el_contacto_se_guarda_con_version_y_completa_el_contacto_inicial(): void
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, null);
        $datos = ['correo_personal' => 'primero@correo.test', 'celular_whatsapp' => '+51 900 000 001'];

        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/contacto', $datos)->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/contacto', ['correo_personal' => 'segundo@correo.test'] + $datos);

        $versiones = DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->orderBy('vigente_desde')->get();
        $this->assertCount(2, $versiones, 'el cambio crea una versión nueva y conserva la anterior');
        $this->assertNotNull($versiones[0]->vigente_hasta);
        $this->assertNull($versiones[1]->vigente_hasta);
        $this->assertSame('COMPLETADA', DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion)->estado);
    }

    public function test_un_contacto_invalido_no_se_guarda_y_muestra_errores_en_espanol(): void
    {
        $egresado = $this->crearEgresado();

        $this->comoEgresado($egresado, null)->from(self::BASE.'/contacto')
            ->post(self::BASE.'/contacto', ['correo_personal' => 'no-es-correo', 'celular_whatsapp' => '12'])
            ->assertRedirect(self::BASE.'/contacto')->assertSessionHasErrors(['correo_personal', 'celular_whatsapp']);

        $this->assertSame(0, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->count());
    }

    public function test_la_encuesta_se_registra_una_sola_vez_y_aparece_en_el_historial(): void
    {
        [$egresado, $invitacion] = $this->conEncuestaPendiente();

        $this->comoEgresado($egresado, $invitacion)->get(self::BASE.'/encuesta')->assertOk()->assertSee('Encuesta de seguimiento a egresados');
        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta())->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion)->count());
        $this->assertSame('COMPLETADA', DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion)->estado);

        // Un segundo envío o una segunda apertura no crean otra respuesta.
        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta(['nombre_empleador_cliente' => 'Otra']))->assertRedirect();
        $this->comoEgresado($egresado, $invitacion)->get(self::BASE.'/encuesta')->assertRedirect(route('seguimiento-egresado.egresado.historial'));
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->count());

        $this->comoEgresado($egresado, $invitacion)->get(self::BASE.'/historial')->assertOk()->assertSee('Respondida')->assertSee('Ver respuesta');
    }

    #[DataProvider('encuestasInvalidas')]
    public function test_cada_dato_invalido_de_la_encuesta_se_rechaza_y_no_guarda_nada(array $cambios, string $campo): void
    {
        [$egresado, $invitacion] = $this->conEncuestaPendiente();

        $this->comoEgresado($egresado, $invitacion)->from(self::BASE.'/encuesta')
            ->post(self::BASE.'/encuesta', $this->respuesta($cambios))
            ->assertRedirect(self::BASE.'/encuesta')->assertSessionHasErrors($campo);

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
        $this->assertSame('PENDIENTE', DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion)->estado);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function encuestasInvalidas(): iterable
    {
        $formacion = ['tipo' => 'CURSO', 'estado' => 'CULMINADA', 'area_formacion' => 'Redes', 'institucion' => 'Instituto Ficticio', 'fecha_inicio' => '2025-01-01', 'fecha_fin' => '2025-06-01'];

        yield 'situación desconocida' => [['situacion' => 'JUBILADO'], 'situacion'];
        yield 'país de residencia inexistente' => [['pais_residencia' => 'ZZ'], 'pais_residencia'];
        yield 'ciudad con símbolos' => [['ciudad_residencia' => '<script>alert(1)</script>'], 'ciudad_residencia'];
        yield 'nivel de inglés desconocido' => [['nivel_ingles' => 'NATIVO'], 'nivel_ingles'];
        yield 'empleador de un solo carácter' => [['nombre_empleador_cliente' => 'A'], 'nombre_empleador_cliente'];
        yield 'empleador con carácter de control' => [['nombre_empleador_cliente' => 'Empresa Ficticia'], 'nombre_empleador_cliente'];
        yield 'jefe con texto libre' => [['contacto_jefe' => 'mi jefe'], 'contacto_jefe'];
        yield 'jefe con correo sin terminación' => [['contacto_jefe' => 'jefe@empresa'], 'contacto_jefe'];
        yield 'comentario demasiado largo' => [['comentario' => str_repeat('a', 1001)], 'comentario'];
        yield 'satisfacción inexistente' => [['satisfaccion' => 'ENCANTADO'], 'satisfaccion'];
        yield 'habilidad repetida' => [['brechas' => ['CLOUD', 'CLOUD']], 'brechas.0'];
        yield 'otra habilidad sin detalle' => [['brechas' => ['OTRA'], 'detalle_brecha_otra' => ''], 'detalle_brecha_otra'];
        yield 'contacto: correo inválido' => [['contacto' => ['correo_personal' => 'nada', 'celular_whatsapp' => '+51 900 000 001']], 'contacto.correo_personal'];
        yield 'contacto: celular inválido' => [['contacto' => ['correo_personal' => 'persona@correo.com', 'celular_whatsapp' => '123']], 'contacto.celular_whatsapp'];
        yield 'formación: fecha de inicio futura' => [['formaciones' => [['fecha_inicio' => now()->addYear()->toDateString()] + $formacion]], 'formaciones.0.fecha_inicio'];
        yield 'formación: fecha de inicio demasiado antigua' => [['formaciones' => [['fecha_inicio' => '1900-01-01'] + $formacion]], 'formaciones.0.fecha_inicio'];
        yield 'formación: fecha de fin futura' => [['formaciones' => [['fecha_fin' => now()->addYear()->toDateString()] + $formacion]], 'formaciones.0.fecha_fin'];
        yield 'formación: fin anterior al inicio' => [['formaciones' => [['fecha_fin' => '2024-01-01'] + $formacion]], 'formaciones.0.fecha_fin'];
        yield 'formación: fecha con formato inválido' => [['formaciones' => [['fecha_inicio' => '01/02/2025'] + $formacion]], 'formaciones.0.fecha_inicio'];
        yield 'formación: institución de un carácter' => [['formaciones' => [['institucion' => 'X'] + $formacion]], 'formaciones.0.institucion'];
    }

    public function test_los_errores_quedan_asociados_a_cada_campo_en_la_pagina(): void
    {
        $egresado = $this->crearEgresado();

        $html = $this->comoEgresado($egresado, null)->followingRedirects()->from(self::BASE.'/contacto')
            ->post(self::BASE.'/contacto', ['correo_personal' => 'no-es-correo', 'celular_whatsapp' => '123'])->getContent();

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="correo_personal-error"', $html);
        $this->assertStringContainsString('id="correo_personal-error"', $html);
        $this->assertStringContainsString('Ingresa un correo personal con formato válido', $html);
        $this->assertStringContainsString('value="no-es-correo"', $html, 'conserva lo escrito');
    }

    public function test_quien_no_trabaja_responde_sin_datos_de_empleo(): void
    {
        [$egresado, $invitacion] = $this->conEncuestaPendiente();

        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta(['situacion' => 'NO_TRABAJA']))->assertRedirect(route('seguimiento-egresado.egresado.historial'));

        $medicion = DB::table('seguimiento_egresado.medicion_laboral')->first();
        $this->assertNull($medicion->nombre_empleador_cliente);
        $this->assertNull($medicion->contacto_jefe);
    }

    public function test_una_encuesta_incompleta_se_rechaza_y_conserva_lo_escrito(): void
    {
        [$egresado, $invitacion] = $this->conEncuestaPendiente();

        $this->comoEgresado($egresado, $invitacion)->from(self::BASE.'/encuesta')
            ->post(self::BASE.'/encuesta', $this->respuesta(['nombre_empleador_cliente' => '', 'comentario' => 'Texto que no debe perderse']))
            ->assertRedirect(self::BASE.'/encuesta')->assertSessionHasErrors('nombre_empleador_cliente')->assertSessionHasInput('comentario', 'Texto que no debe perderse');

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    public function test_el_enlace_de_contacto_inicial_no_abre_la_encuesta(): void
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, null);

        $this->comoEgresado($egresado, $invitacion)->get(self::BASE.'/encuesta')->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta())->assertRedirect(route('seguimiento-egresado.egresado.inicio'))->assertSessionHas('error');
        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    public function test_no_se_puede_responder_con_la_invitacion_de_otro_egresado(): void
    {
        [, $invitacionAjena] = $this->conEncuestaPendiente();
        $yo = $this->crearEgresado();

        $this->comoEgresado($yo, $invitacionAjena)->post(self::BASE.'/encuesta', $this->respuesta())->assertRedirect(route('seguimiento-egresado.egresado.inicio'));

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    public function test_una_campania_cerrada_no_recibe_respuestas(): void
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, $this->crearCampania(['estado' => 'CERRADA']));

        $this->comoEgresado($egresado, $invitacion)->get(self::BASE.'/encuesta')->assertRedirect(route('seguimiento-egresado.egresado.inicio'))->assertSessionHas('error');
        $this->comoEgresado($egresado, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta())->assertRedirect(route('seguimiento-egresado.egresado.inicio'));
        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    public function test_el_detalle_de_una_respuesta_ajena_responde_404(): void
    {
        [$dueno, $invitacion] = $this->conEncuestaPendiente();
        $this->comoEgresado($dueno, $invitacion)->post(self::BASE.'/encuesta', $this->respuesta());
        $medicion = (int) DB::table('seguimiento_egresado.medicion_laboral')->value('id');
        $otro = $this->crearEgresado();

        $this->comoEgresado($dueno, $invitacion)->get(self::BASE."/historial/{$medicion}")->assertOk()->assertSee('Empresa Ficticia S.A.C.');
        $this->comoEgresado($otro, null)->get(self::BASE."/historial/{$medicion}")->assertNotFound();
        $this->comoEgresado($otro, null)->get(self::BASE.'/historial/999999')->assertNotFound();
    }

    public function test_el_historial_conserva_las_respuestas_de_varias_campanias(): void
    {
        $egresado = $this->crearEgresado();
        $primera = $this->crearInvitacion($egresado, $this->crearCampania(['nombre' => 'Campaña A', 'periodo' => '2025-II']));
        $this->comoEgresado($egresado, $primera)->post(self::BASE.'/encuesta', $this->respuesta());
        $segunda = $this->crearInvitacion($egresado, $this->crearCampania(['nombre' => 'Campaña B', 'periodo' => '2026-II']));
        $this->comoEgresado($egresado, $segunda)->post(self::BASE.'/encuesta', $this->respuesta(['situacion' => 'NO_TRABAJA']));

        $this->assertSame(2, DB::table('seguimiento_egresado.medicion_laboral')->count());
        $this->comoEgresado($egresado, $segunda)->get(self::BASE.'/historial')->assertSee('Campaña A')->assertSee('Campaña B');
    }
}
