<?php

namespace Tests\Feature\SeguimientoEgresado\Egresado;

use App\Modules\SeguimientoEgresado\Services\Egresado\ContactoService;
use App\Modules\SeguimientoEgresado\Services\Egresado\ResultadoContacto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\FabricaSeguimiento;
use Tests\Support\SeguimientoEgresado\UsaEsquemaPostgres;
use Tests\TestCase;

class ContactoServiceTest extends TestCase
{
    use FabricaSeguimiento, UsaEsquemaPostgres;

    private ContactoService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPostgres();
        $this->servicio = new ContactoService;
    }

    protected function tearDown(): void
    {
        $this->revertirPostgres();
        parent::tearDown();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['correo_personal' => 'persona@correo.test', 'celular_whatsapp' => '+51 900 000 001', 'telefono_fijo' => '044 000001', 'direccion' => 'Av. Ficticia 123'], $cambios);
    }

    private function versiones(int $egresadoId): Collection
    {
        return DB::table('seguimiento_egresado.contacto_egresado')->where('id_egresado', $egresadoId)->orderBy('vigente_desde')->get();
    }

    public function test_el_primer_contacto_crea_la_version_vigente_con_origen_formulario(): void
    {
        $egresado = $this->crearEgresado();

        $this->assertSame(ResultadoContacto::Creado, $this->servicio->guardar($egresado, $this->datos()));

        $versiones = $this->versiones($egresado);
        $this->assertCount(1, $versiones);
        $this->assertNull($versiones[0]->vigente_hasta);
        $this->assertSame('FORMULARIO', $versiones[0]->origen_registro);
        $this->assertSame('Av. Ficticia 123', $versiones[0]->direccion);
    }

    public function test_un_cambio_cierra_la_version_anterior_y_crea_otra_sin_sobrescribir(): void
    {
        $egresado = $this->crearEgresado();
        $this->servicio->guardar($egresado, $this->datos());

        $this->assertSame(ResultadoContacto::Actualizado, $this->servicio->guardar($egresado, $this->datos(['correo_personal' => 'nuevo@correo.test'])));

        $versiones = $this->versiones($egresado);
        $this->assertCount(2, $versiones);
        $this->assertSame('persona@correo.test', $versiones[0]->correo_personal, 'la versión anterior se conserva');
        $this->assertNotNull($versiones[0]->vigente_hasta);
        $this->assertSame($versiones[0]->vigente_hasta, $versiones[1]->vigente_desde, 'la nueva empieza donde termina la anterior');
        $this->assertNull($versiones[1]->vigente_hasta);
        $this->assertSame(1, $versiones->whereNull('vigente_hasta')->count(), 'una sola versión vigente');
    }

    public function test_datos_iguales_a_los_vigentes_no_crean_una_version_nueva(): void
    {
        $egresado = $this->crearEgresado();
        $this->servicio->guardar($egresado, $this->datos());

        $this->assertSame(ResultadoContacto::SinCambios, $this->servicio->guardar($egresado, $this->datos(['correo_personal' => '  persona@correo.test  '])));
        $this->assertCount(1, $this->versiones($egresado));
    }

    public function test_cambios_consecutivos_inmediatos_respetan_la_restriccion_de_fechas(): void
    {
        $egresado = $this->crearEgresado();

        foreach (['a', 'b', 'c', 'd'] as $letra) {
            $this->servicio->guardar($egresado, $this->datos(['direccion' => "Dirección {$letra}"]));
        }

        $versiones = $this->versiones($egresado);
        $this->assertCount(4, $versiones);
        $this->assertSame(1, $versiones->whereNull('vigente_hasta')->count());
        foreach ($versiones->slice(0, 3) as $v) {
            $this->assertGreaterThan(strtotime($v->vigente_desde), strtotime($v->vigente_hasta) + 0.000001);
        }
    }

    public function test_los_opcionales_vacios_se_guardan_como_nulos(): void
    {
        $egresado = $this->crearEgresado();

        $this->servicio->guardar($egresado, $this->datos(['telefono_fijo' => '  ', 'direccion' => '']));

        $v = $this->versiones($egresado)[0];
        $this->assertNull($v->telefono_fijo);
        $this->assertNull($v->direccion);
    }

    public function test_secretaria_registra_con_origen_secretaria(): void
    {
        $egresado = $this->crearEgresado();

        $this->servicio->guardar($egresado, $this->datos(), 'SECRETARIA');

        $this->assertSame('SECRETARIA', $this->versiones($egresado)[0]->origen_registro);
    }

    public function test_un_origen_desconocido_se_rechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->servicio->guardar($this->crearEgresado(), $this->datos(), 'EMPRESA');
    }

    public function test_el_contacto_de_un_egresado_no_afecta_al_de_otro(): void
    {
        $uno = $this->crearEgresado();
        $dos = $this->crearEgresado();
        $this->servicio->guardar($uno, $this->datos());
        $this->servicio->guardar($dos, $this->datos(['correo_personal' => 'dos@correo.test']));

        $this->servicio->guardar($uno, $this->datos(['correo_personal' => 'uno-nuevo@correo.test']));

        $this->assertCount(2, $this->versiones($uno));
        $this->assertCount(1, $this->versiones($dos));
    }

    public function test_completa_la_invitacion_de_contacto_inicial_del_propio_egresado(): void
    {
        $egresado = $this->crearEgresado();
        $otro = $this->crearEgresado();
        $invitacion = $this->crearInvitacion($egresado, null);
        $ajena = $this->crearInvitacion($otro, null);

        $this->servicio->guardar($egresado, $this->datos(), 'FORMULARIO', $invitacion);
        $this->servicio->guardar($egresado, $this->datos(['direccion' => 'otra']), 'FORMULARIO', $ajena);

        $propia = DB::table('seguimiento_egresado.invitacion_egresado')->find($invitacion);
        $this->assertSame('COMPLETADA', $propia->estado);
        $this->assertNotNull($propia->respondido_en);
        $this->assertSame('PENDIENTE', DB::table('seguimiento_egresado.invitacion_egresado')->find($ajena)->estado, 'no se completa la invitación de otra persona');
    }

    public function test_una_invitacion_de_seguimiento_no_se_completa_desde_el_contacto(): void
    {
        $egresado = $this->crearEgresado();
        $seguimiento = $this->crearInvitacion($egresado, $this->crearCampania());

        $this->servicio->guardar($egresado, $this->datos(), 'FORMULARIO', $seguimiento);

        $this->assertSame('PENDIENTE', DB::table('seguimiento_egresado.invitacion_egresado')->find($seguimiento)->estado);
    }
}
