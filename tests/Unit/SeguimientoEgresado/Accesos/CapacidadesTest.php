<?php

namespace Tests\Unit\SeguimientoEgresado\Accesos;

use App\Modules\SeguimientoEgresado\Support\Accesos\Capacidades;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Comprueba la matriz contra la hoja «Permisos» del Sprint 3. */
class CapacidadesTest extends TestCase
{
    public function test_el_egresado_solo_accede_a_su_propio_expediente_y_a_responder(): void
    {
        $this->assertSame(
            ['campanias.responder', 'expediente.propio', 'seguimiento.propio'],
            Capacidades::de(Perfil::Egresado),
        );
    }

    public function test_secretaria_gestiona_padron_y_campanias_pero_no_registra_ni_valida_lo_institucional(): void
    {
        $this->assertTrue(Capacidades::permite(Perfil::Secretaria, 'padron.gestionar'));
        $this->assertTrue(Capacidades::permite(Perfil::Secretaria, 'campanias.administrar'));
        $this->assertTrue(Capacidades::permite(Perfil::Secretaria, 'institucional.consultar'));
        $this->assertFalse(Capacidades::permite(Perfil::Secretaria, 'institucional.registrar'));
        $this->assertFalse(Capacidades::permite(Perfil::Secretaria, 'institucional.validar'));
    }

    public function test_director_consulta_padron_y_campanias_pero_solo_el_registra_y_valida_lo_institucional(): void
    {
        $this->assertTrue(Capacidades::permite(Perfil::Director, 'padron.consultar'));
        $this->assertFalse(Capacidades::permite(Perfil::Director, 'padron.gestionar'));
        $this->assertFalse(Capacidades::permite(Perfil::Director, 'campanias.administrar'));
        $this->assertTrue(Capacidades::permite(Perfil::Director, 'institucional.registrar'));
        $this->assertTrue(Capacidades::permite(Perfil::Director, 'institucional.validar'));
    }

    public function test_el_director_ve_agregados_y_la_secretaria_consulta_el_seguimiento_individual(): void
    {
        $this->assertTrue(Capacidades::permite(Perfil::Director, 'seguimiento.agregados'));
        $this->assertFalse(Capacidades::permite(Perfil::Director, 'seguimiento.consultar'));
        $this->assertTrue(Capacidades::permite(Perfil::Secretaria, 'seguimiento.consultar'));
        $this->assertFalse(Capacidades::permite(Perfil::Secretaria, 'seguimiento.agregados'));
    }

    #[DataProvider('capacidadesInternas')]
    public function test_el_egresado_no_tiene_ninguna_capacidad_interna(string $capacidad): void
    {
        $this->assertFalse(Capacidades::permite(Perfil::Egresado, $capacidad));
    }

    /** @return iterable<string, array{string}> */
    public static function capacidadesInternas(): iterable
    {
        foreach (array_keys(Capacidades::MATRIZ) as $capacidad) {
            if (! in_array($capacidad, ['expediente.propio', 'campanias.responder', 'seguimiento.propio'], true)) {
                yield $capacidad => [$capacidad];
            }
        }
    }

    public function test_una_capacidad_desconocida_se_deniega_siempre(): void
    {
        foreach (Perfil::cases() as $perfil) {
            $this->assertFalse(Capacidades::permite($perfil, 'inexistente.capacidad'));
        }
        $this->assertFalse(Capacidades::existe('inexistente.capacidad'));
        $this->assertTrue(Capacidades::existe('padron.consultar'));
    }

    public function test_cada_capacidad_pertenece_al_menos_a_un_perfil(): void
    {
        foreach (Capacidades::MATRIZ as $capacidad => $perfiles) {
            $this->assertNotEmpty($perfiles, "La capacidad {$capacidad} no tiene perfiles.");
        }
    }

    public function test_los_perfiles_internos_se_resuelven_desde_el_rol_de_la_bd(): void
    {
        $this->assertSame(Perfil::Secretaria, Perfil::desdeRolInterno('SECRETARIA'));
        $this->assertSame(Perfil::Director, Perfil::desdeRolInterno('DIRECTOR'));
        $this->assertNull(Perfil::desdeRolInterno('EGRESADO'));
        $this->assertNull(Perfil::desdeRolInterno('ADMIN'));
    }
}
