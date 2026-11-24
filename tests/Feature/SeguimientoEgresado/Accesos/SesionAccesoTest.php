<?php

namespace Tests\Feature\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Services\Accesos\InvitacionAcceso;
use App\Modules\SeguimientoEgresado\Services\Accesos\MotivoAcceso;
use App\Modules\SeguimientoEgresado\Services\Accesos\ResultadoAcceso;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class SesionAccesoTest extends TestCase
{
    private const AHORA = '2026-11-10 10:00:00';

    private function sesion(): SesionAcceso
    {
        return new SesionAcceso($this->app->make('session.store'));
    }

    private function accesoPermitido(CarbonImmutable $vence): ResultadoAcceso
    {
        return ResultadoAcceso::permitido(new InvitacionAcceso(
            id: 7, idEgresado: 42, idCampania: 3, proposito: 'SEGUIMIENTO', estado: 'PENDIENTE',
            expiraEn: $vence, codigoMatricula: '1020301921', numeroDocumento: '74519203',
        ));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse(self::AHORA));
    }

    public function test_sin_inicio_de_sesion_no_hay_identidad(): void
    {
        $this->assertNull($this->sesion()->actual());
    }

    public function test_el_egresado_queda_atado_a_su_expediente_y_a_su_invitacion(): void
    {
        $sesion = $this->sesion();
        $sesion->iniciarEgresado($this->accesoPermitido(CarbonImmutable::parse(self::AHORA)->addDays(3)));

        $identidad = $sesion->actual();

        $this->assertSame(Perfil::Egresado, $identidad->perfil);
        $this->assertSame(42, $identidad->egresadoId);
        $this->assertSame(7, $identidad->invitacionId);
    }

    public function test_la_sesion_del_egresado_no_guarda_matricula_ni_documento(): void
    {
        $sesion = $this->sesion();
        $sesion->iniciarEgresado($this->accesoPermitido(CarbonImmutable::parse(self::AHORA)->addDays(3)));

        $guardado = json_encode($this->app->make('session.store')->all());

        $this->assertStringNotContainsString('1020301921', $guardado);
        $this->assertStringNotContainsString('74519203', $guardado);
    }

    public function test_un_acceso_rechazado_no_abre_sesion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sesion()->iniciarEgresado(ResultadoAcceso::rechazado(MotivoAcceso::EnlaceNoValido));
    }

    public function test_la_sesion_del_egresado_termina_cuando_vence_su_invitacion(): void
    {
        $sesion = $this->sesion();
        $sesion->iniciarEgresado($this->accesoPermitido(CarbonImmutable::parse(self::AHORA)->addMinutes(30)));
        $this->assertNotNull($sesion->actual());

        $this->travelTo(CarbonImmutable::parse(self::AHORA)->addMinutes(31));

        $this->assertNull($sesion->actual());
        $this->assertNull($sesion->actual(), 'la identidad vencida se elimina de la sesión');
    }

    public function test_cerrar_sesion_elimina_la_identidad(): void
    {
        $sesion = $this->sesion();
        $sesion->iniciarEgresado($this->accesoPermitido(CarbonImmutable::parse(self::AHORA)->addDays(3)));

        $sesion->cerrar();

        $this->assertNull($sesion->actual());
    }

    public function test_un_valor_corrupto_en_la_sesion_se_trata_como_sin_identidad(): void
    {
        $store = $this->app->make('session.store');
        $sesion = new SesionAcceso($store);

        $store->put('seguimiento-egresado.identidad', 'cualquier-cosa');
        $this->assertNull($sesion->actual());

        $store->put('seguimiento-egresado.identidad', ['perfil' => 'ADMINISTRADOR']);
        $this->assertNull($sesion->actual());
    }
}
