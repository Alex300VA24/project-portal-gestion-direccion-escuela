<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest;
use App\Modules\SeguimientoEgresado\Services\Egresado\ContactoService;
use App\Modules\SeguimientoEgresado\Services\Egresado\EncuestaService;
use App\Modules\SeguimientoEgresado\Services\Egresado\InvitacionNoDisponible;
use App\Modules\SeguimientoEgresado\Services\Egresado\MotivoInvitacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

class EncuestaServiceTest extends TestCase
{
    use FabricaSeguimiento, UsaEsquemaPostgres;

    private EncuestaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPostgres();
        $this->servicio = new EncuestaService(new ContactoService);
    }

    protected function tearDown(): void
    {
        $this->revertirPostgres();
        parent::tearDown();
    }

    /** Pasa por la misma validación que usará el controlador (con catálogos reales). */
    private function validado(array $cambios = []): array
    {
        $datos = $this->datosDeEncuesta($cambios);

        return Validator::make($datos, EncuestaSeguimientoRequest::reglasPara($datos))->validate();
    }

    private function escenario(array $invitacion = [], array $campania = []): array
    {
        $egresado = $this->crearEgresado();
        $campaniaId = $this->crearCampania($campania);

        return [$egresado, $this->crearInvitacion($egresado, $campaniaId, $invitacion), $campaniaId];
    }

    public function test_guarda_la_respuesta_completa_de_quien_trabaja(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $contacto = ['correo_personal' => 'persona@correo.test', 'celular_whatsapp' => '+51 900 000 001'];

        $id = $this->servicio->registrar($egresado, $invitacion, $this->validado(), $contacto);

        $m = DB::table('seguimiento_egresado.medicion_laboral')->find($id);
        $this->assertSame($invitacion, (int) $m->id_invitacion);
        $this->assertTrue((bool) $m->trabajo_relacionado);
        $this->assertSame('Empresa Ficticia S.A.C.', $m->nombre_empleador_cliente);
        $this->assertSame('LA_LIBERTAD', $m->region_empleo);
        $this->assertSame('INTERMEDIO', $m->nivel_ingles);
        $this->assertTrue((bool) $m->autoriza_contacto_empresa);
        $this->assertSame('jefe@empresa.test', $m->contacto_jefe);

        $this->assertSame(1, DB::table('seguimiento_egresado.satisfaccion_egresado')->where('id_medicion', $id)->count());
        $this->assertSame(2, DB::table('seguimiento_egresado.medicion_habilidad')->where('id_medicion', $id)->where('tipo', 'BRECHA')->count());
        $this->assertSame('Gestión ágil', DB::table('seguimiento_egresado.medicion_habilidad as mh')->join('seguimiento_egresado.cat_habilidad as h', 'h.id', '=', 'mh.id_habilidad')
            ->where('mh.id_medicion', $id)->where('h.codigo', 'OTRA')->value('mh.detalle_otro'));
        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_habilidad')->where('id_medicion', $id)->where('tipo', 'FORTALEZA')->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.formacion_posterior')->where('id_medicion_origen', $id)->where('id_egresado', $egresado)->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->whereNull('vigente_hasta')->count());

        $inv = DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion);
        $this->assertSame('COMPLETADA', $inv->estado);
        $this->assertNotNull($inv->respondido_en);
    }

    public function test_quien_no_trabaja_no_almacena_ningun_campo_de_empleo(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $validado = $this->validado(['situacion' => 'NO_TRABAJA']);

        $id = $this->servicio->registrar($egresado, $invitacion, $validado);

        $m = DB::table('seguimiento_egresado.medicion_laboral')->find($id);
        foreach (['trabajo_relacionado', 'id_modalidad_laboral', 'id_modalidad_trabajo', 'id_sector_laboral', 'nombre_empleador_cliente', 'id_pais_empleador', 'region_empleo', 'autoriza_contacto_empresa', 'contacto_jefe'] as $columna) {
            $this->assertNull($m->{$columna}, "{$columna} debe quedar en NULL");
        }
        $this->assertNotNull($m->id_pais_residencia, 'el país de residencia siempre se guarda');
    }

    public function test_sin_formacion_ni_habilidades_ni_contacto_tambien_se_guarda(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $validado = $this->validado(['brechas' => [], 'fortalezas' => [], 'formaciones' => [], 'detalle_brecha_otra' => null]);

        $id = $this->servicio->registrar($egresado, $invitacion, $validado);

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_habilidad')->where('id_medicion', $id)->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.formacion_posterior')->where('id_egresado', $egresado)->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresado)->count());
    }

    public function test_la_segunda_respuesta_a_la_misma_invitacion_se_rechaza_y_no_cambia_nada(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $this->servicio->registrar($egresado, $invitacion, $this->validado());

        try {
            $this->servicio->registrar($egresado, $invitacion, $this->validado(['situacion' => 'NO_TRABAJA']));
            $this->fail('Debió rechazarse la segunda respuesta.');
        } catch (InvitacionNoDisponible $e) {
            $this->assertSame(MotivoInvitacion::YaRespondida, $e->motivo);
        }

        $this->assertSame(1, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $invitacion)->count());
        $this->assertSame(1, DB::table('seguimiento_egresado.formacion_posterior')->where('id_egresado', $egresado)->count(), 'no se duplicó la formación');
    }

    #[DataProvider('invitacionesNoDisponibles')]
    public function test_no_se_responde_una_invitacion_no_disponible(array $invitacion, array $campania, MotivoInvitacion $esperado): void
    {
        [$egresado, $id] = $this->escenario($invitacion, $campania);

        try {
            $this->servicio->registrar($egresado, $id, $this->validado());
            $this->fail('Debió rechazarse.');
        } catch (InvitacionNoDisponible $e) {
            $this->assertSame($esperado, $e->motivo);
        }

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    /** @return iterable<string, array{array, array, MotivoInvitacion}> */
    public static function invitacionesNoDisponibles(): iterable
    {
        yield 'anulada' => [['estado' => 'ANULADA'], [], MotivoInvitacion::Anulada];
        yield 'expirada' => [['estado' => 'EXPIRADA'], [], MotivoInvitacion::Expirada];
        yield 'vencida por fecha' => [['expira_en' => '2020-01-01 00:00:00+00'], [], MotivoInvitacion::Vencida];
        yield 'campaña cerrada' => [[], ['estado' => 'CERRADA'], MotivoInvitacion::CampaniaCerrada];
        yield 'campaña aún en borrador' => [[], ['estado' => 'BORRADOR'], MotivoInvitacion::CampaniaCerrada];
    }

    public function test_un_egresado_no_puede_responder_la_invitacion_de_otro(): void
    {
        [, $invitacionDeUno] = $this->escenario();
        $otro = $this->crearEgresado();

        try {
            $this->servicio->registrar($otro, $invitacionDeUno, $this->validado());
            $this->fail('Debió rechazarse.');
        } catch (InvitacionNoDisponible $e) {
            $this->assertSame(MotivoInvitacion::NoEncontrada, $e->motivo);
        }

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
    }

    public function test_una_invitacion_de_contacto_inicial_no_sirve_para_la_encuesta(): void
    {
        $egresado = $this->crearEgresado();
        $contactoInicial = $this->crearInvitacion($egresado, null);

        $this->expectException(InvitacionNoDisponible::class);

        $this->servicio->registrar($egresado, $contactoInicial, $this->validado());
    }

    public function test_un_error_a_mitad_revierte_todo_lo_guardado(): void
    {
        [$egresado, $invitacion] = $this->escenario();
        $datos = $this->validado();
        $datos['formaciones'][0]['tipo'] = 'TIPO_INEXISTENTE';

        try {
            $this->servicio->registrar($egresado, $invitacion, $datos, ['correo_personal' => 'x@correo.test', 'celular_whatsapp' => '+51 900 000 009']);
            $this->fail('Debió fallar por el catálogo inexistente.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_laboral')->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.satisfaccion_egresado')->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.medicion_habilidad')->count());
        $this->assertSame(0, DB::table('seguimiento_egresado.contacto_egresado')->count());
        $this->assertSame('PENDIENTE', DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion)->estado);
    }

    public function test_las_respuestas_de_distintas_campanias_se_conservan_sin_sobrescribirse(): void
    {
        $egresado = $this->crearEgresado();
        $primera = $this->crearInvitacion($egresado, $this->crearCampania(['periodo' => '2025-II']));
        $segunda = $this->crearInvitacion($egresado, $this->crearCampania(['periodo' => '2026-II']));

        $uno = $this->servicio->registrar($egresado, $primera, $this->validado(['nombre_empleador_cliente' => 'Primera Empresa']));
        $dos = $this->servicio->registrar($egresado, $segunda, $this->validado(['situacion' => 'NO_TRABAJA']));

        $this->assertNotSame($uno, $dos);
        $this->assertSame(2, DB::table('seguimiento_egresado.medicion_laboral as m')->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')->where('i.id_egresado', $egresado)->count());
        $this->assertSame('Primera Empresa', DB::table('seguimiento_egresado.medicion_laboral')->find($uno)->nombre_empleador_cliente, 'la medición anterior no cambió');
    }
}
