<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest;
use App\Modules\SeguimientoEgresado\Services\Egresado\ContactoService;
use App\Modules\SeguimientoEgresado\Services\Egresado\EncuestaService;
use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

class HistorialServiceTest extends TestCase
{
    use FabricaSeguimiento, UsaEsquemaPostgres;

    private HistorialService $historial;

    private EncuestaService $encuesta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPostgres();
        $this->historial = new HistorialService;
        $this->encuesta = new EncuestaService(new ContactoService);
    }

    protected function tearDown(): void
    {
        $this->revertirPostgres();
        parent::tearDown();
    }

    private function responder(int $egresado, int $invitacion, array $cambios = [], ?array $contacto = null): int
    {
        $datos = $this->datosDeEncuesta($cambios);

        return $this->encuesta->registrar($egresado, $invitacion, Validator::make($datos, EncuestaSeguimientoRequest::reglasPara($datos))->validate(), $contacto);
    }

    public function test_lista_las_campanias_de_la_mas_reciente_a_la_mas_antigua_con_su_estado_de_respuesta(): void
    {
        $egresado = $this->crearEgresado();
        $antigua = $this->crearCampania(['nombre' => 'Seguimiento 2024-II', 'periodo' => '2024-II', 'fecha_inicio' => '2024-10-01', 'fecha_fin' => '2024-12-15']);
        $media = $this->crearCampania(['nombre' => 'Seguimiento 2025-II', 'periodo' => '2025-II', 'fecha_inicio' => '2025-10-01', 'fecha_fin' => '2025-12-15']);
        $actual = $this->crearCampania(['nombre' => 'Seguimiento 2026-II', 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2099-12-31']);
        $invAntigua = $this->crearInvitacion($egresado, $antigua);
        $this->crearInvitacion($egresado, $media);
        $this->crearInvitacion($egresado, $actual);

        $this->responder($egresado, $invAntigua);
        DB::table('seguimiento_egresado.campania_seguimiento')->whereIn('id', [$antigua, $media])->update(['estado' => 'CERRADA']);

        $filas = $this->historial->campanias($egresado);

        $this->assertSame(['Seguimiento 2026-II', 'Seguimiento 2025-II', 'Seguimiento 2024-II'], array_column($filas, 'campania'));
        $this->assertSame(['PENDIENTE', 'NO_RESPONDIO', 'RESPONDIDA'], array_column($filas, 'estado_respuesta'));
        $this->assertNull($filas[1]['situacion_laboral'], 'no responder no equivale a ninguna situación laboral');
        $this->assertSame('TRABAJA', $filas[2]['situacion_laboral']);
    }

    public function test_solo_devuelve_las_campanias_del_egresado_indicado(): void
    {
        $uno = $this->crearEgresado();
        $dos = $this->crearEgresado();
        $campania = $this->crearCampania();
        $this->crearInvitacion($uno, $campania);
        $this->crearInvitacion($dos, $campania);

        $this->assertCount(1, $this->historial->campanias($uno));
        $this->assertSame([], $this->historial->campanias($this->crearEgresado()));
    }

    public function test_el_detalle_de_la_medicion_incluye_empleo_habilidades_y_formacion(): void
    {
        $egresado = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, $this->crearCampania());
        $medicion = $this->responder($egresado, $invitacion);

        $d = $this->historial->medicion($egresado, $medicion);

        $this->assertSame('Trabaja', $d['situacion']);
        $this->assertSame('Empresa Ficticia S.A.C.', $d['nombre_empleador_cliente']);
        $this->assertSame('Perú', $d['pais_empleador']);
        $this->assertSame('Satisfecho', $d['satisfaccion']);
        $this->assertEqualsCanonicalizing(['CLOUD', 'OTRA'], array_column($d['brechas'], 'codigo'));
        $this->assertSame(['BACKEND'], array_column($d['fortalezas'], 'codigo'));
        $this->assertCount(1, $d['formaciones']);
        $this->assertSame('Universidad Ficticia', $d['formaciones'][0]['institucion']);
    }

    public function test_no_se_puede_ver_la_medicion_de_otro_egresado(): void
    {
        $egresado = $this->crearEgresado();
        $medicion = $this->responder($egresado, $this->crearInvitacion($egresado, $this->crearCampania()));

        $this->assertNull($this->historial->medicion($this->crearEgresado(), $medicion));
        $this->assertNull($this->historial->medicion($egresado, 999999));
    }

    public function test_las_versiones_de_contacto_se_listan_de_la_mas_reciente_y_solo_una_esta_vigente(): void
    {
        $egresado = $this->crearEgresado();
        $contactos = new ContactoService;
        $contactos->guardar($egresado, ['correo_personal' => 'uno@correo.test', 'celular_whatsapp' => '+51 900 000 001']);
        $contactos->guardar($egresado, ['correo_personal' => 'dos@correo.test', 'celular_whatsapp' => '+51 900 000 001']);

        $versiones = $this->historial->contactos($egresado);

        $this->assertSame(['dos@correo.test', 'uno@correo.test'], array_column($versiones, 'correo_personal'));
        $this->assertNull($versiones[0]['vigente_hasta']);
        $this->assertNotNull($versiones[1]['vigente_hasta']);
    }

    public function test_la_formacion_posterior_se_acumula_entre_campanias(): void
    {
        $egresado = $this->crearEgresado();
        $primera = $this->crearInvitacion($egresado, $this->crearCampania(['periodo' => '2025-II']));
        $segunda = $this->crearInvitacion($egresado, $this->crearCampania(['periodo' => '2026-II']));

        $this->responder($egresado, $primera);
        $this->responder($egresado, $segunda, ['formaciones' => [['tipo' => 'MAESTRIA', 'estado' => 'EN_CURSO', 'area_formacion' => 'IA', 'institucion' => 'Otra Universidad']]]);

        $this->assertCount(2, $this->historial->formaciones($egresado));
        $this->assertCount(1, $this->historial->formaciones($egresado, DB::table('seguimiento_egresado.medicion_laboral')->where('id_invitacion', $segunda)->value('id')));
    }
}
