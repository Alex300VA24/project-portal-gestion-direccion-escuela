<?php

namespace Tests\Feature\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\Accesos\InvitacionAcceso;
use App\Modules\SeguimientoEgresado\Services\Accesos\InvitacionRepositorio;
use App\Modules\SeguimientoEgresado\Services\Accesos\MotivoAcceso;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnlaceInvitacionTest extends TestCase
{
    public const TOKEN = 'token-secreto-de-prueba-0123456789';

    private const AHORA = '2026-11-10 10:00:00';

    private function servicio(?InvitacionAcceso $invitacion): EnlaceInvitacion
    {
        $repositorio = new class($invitacion) implements InvitacionRepositorio
        {
            public function __construct(private ?InvitacionAcceso $invitacion) {}

            public function buscarPorHash(string $hash): ?InvitacionAcceso
            {
                return $this->invitacion !== null && $hash === EnlaceInvitacion::hashDe(EnlaceInvitacionTest::TOKEN)
                    ? $this->invitacion
                    : null;
            }
        };

        return new EnlaceInvitacion($repositorio);
    }

    private function invitacion(array $cambios = []): InvitacionAcceso
    {
        $datos = array_merge([
            'id' => 7,
            'idEgresado' => 42,
            'idCampania' => 3,
            'proposito' => 'SEGUIMIENTO',
            'estado' => 'PENDIENTE',
            'expiraEn' => CarbonImmutable::parse(self::AHORA)->addDays(10),
            'codigoMatricula' => '1020301921',
            'numeroDocumento' => '74519203',
        ], $cambios);

        return new InvitacionAcceso(...$datos);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse(self::AHORA));
    }

    public function test_el_hash_es_sha256_en_hexadecimal_de_64_caracteres(): void
    {
        $hash = EnlaceInvitacion::hashDe(self::TOKEN);

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', self::TOKEN), $hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function test_con_el_enlace_valido_se_entra_directo_y_solo_a_su_expediente(): void
    {
        $resultado = $this->servicio($this->invitacion())->abrir(self::TOKEN);

        $this->assertTrue($resultado->esPermitido());
        $sesion = $resultado->datosDeSesion();
        $this->assertSame(42, $sesion['egresado_id']);
        $this->assertSame(7, $sesion['invitacion_id']);
        $this->assertSame(3, $sesion['campania_id']);
        $this->assertSame('SEGUIMIENTO', $sesion['proposito']);
        $this->assertArrayNotHasKey('numero_documento', $sesion);
        $this->assertArrayNotHasKey('codigo_matricula', $sesion);
    }

    public function test_la_sesion_vence_cuando_vence_la_invitacion(): void
    {
        $vence = CarbonImmutable::parse(self::AHORA)->addDays(10);

        $sesion = $this->servicio($this->invitacion(['expiraEn' => $vence]))->abrir(self::TOKEN)->datosDeSesion();

        $this->assertSame($vence->toIso8601String(), $sesion['vence_en']);
    }

    public function test_un_enlace_desconocido_o_vacio_no_abre_nada(): void
    {
        $servicio = $this->servicio($this->invitacion());

        $this->assertSame(MotivoAcceso::EnlaceNoValido, $servicio->abrir('otro-token-cualquiera-00000')->motivo);
        $this->assertSame(MotivoAcceso::EnlaceNoValido, $servicio->abrir('')->motivo);
    }

    #[DataProvider('invitacionesSinAcceso')]
    public function test_no_se_entra_con_una_invitacion_anulada_expirada_o_vencida(array $cambios): void
    {
        $resultado = $this->servicio($this->invitacion($cambios))->abrir(self::TOKEN);

        $this->assertFalse($resultado->esPermitido());
        $this->assertSame(MotivoAcceso::EnlaceNoValido, $resultado->motivo);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invitacionesSinAcceso(): iterable
    {
        yield 'anulada' => [['estado' => 'ANULADA']];
        yield 'expirada' => [['estado' => 'EXPIRADA']];
        yield 'vencida por fecha' => [['expiraEn' => CarbonImmutable::parse(self::AHORA)->subSecond()]];
        yield 'vence justo ahora' => [['expiraEn' => CarbonImmutable::parse(self::AHORA)]];
    }

    public function test_una_invitacion_completada_sigue_dando_acceso_para_consultar_el_expediente_hasta_que_venza(): void
    {
        $this->assertTrue($this->servicio($this->invitacion(['estado' => 'COMPLETADA']))->abrir(self::TOKEN)->esPermitido());
    }

    public function test_el_enlace_de_contacto_inicial_tambien_entra_sin_campania(): void
    {
        $resultado = $this->servicio($this->invitacion(['proposito' => 'CONTACTO_INICIAL', 'idCampania' => null]))->abrir(self::TOKEN);

        $this->assertTrue($resultado->esPermitido());
        $this->assertNull($resultado->datosDeSesion()['campania_id']);
    }
}
