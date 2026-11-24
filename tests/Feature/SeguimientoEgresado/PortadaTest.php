<?php

namespace Tests\Feature\SeguimientoEgresado;

use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Portada del módulo: lo que está por cerrar y cuántas respuestas faltan. */
class PortadaTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    private function campaniaDeEgresados(string $nombre, int $diasParaCerrar, int $invitados, int $respondieron): int
    {
        $cohorte = $this->cohorte(10);
        $campania = $this->campania(['nombre' => $nombre, 'tipo' => 'EGRESADOS', 'fecha_fin' => now('America/Lima')->addDays($diasParaCerrar)->toDateString()]);
        for ($i = 0; $i < $invitados; $i++) {
            $invitacion = $this->invitacionSeguimiento($campania, $this->egresado($cohorte));
            if ($i < $respondieron) {
                $this->responder($invitacion);
            }
        }

        return $campania;
    }

    public function test_el_personal_ve_las_campanias_abiertas_con_cuanto_falta(): void
    {
        $this->entrarComo();
        $this->campaniaDeEgresados('Seguimiento a egresados 2026-II', 30, 3, 1);

        $this->get(route('seguimiento-egresado.index'))->assertOk()
            ->assertSee('Por cerrar')
            ->assertSee('Seguimiento a egresados 2026-II')
            ->assertSee('1 de 3')
            ->assertSee('faltan 2')
            ->assertSee('Cierra en 30 días', false)
            ->assertDontSee('esquema de datos');
    }

    public function test_se_muestran_hasta_seis_ordenadas_por_la_que_cierra_primero(): void
    {
        $this->entrarComo();
        foreach (range(1, 8) as $n) {
            $this->campaniaDeEgresados("Campaña número {$n}", $n + 1, 1, 0);
        }

        $respuesta = $this->get(route('seguimiento-egresado.index'))->assertOk()
            ->assertSee('Ver todas las campañas (8)');
        foreach (range(1, 6) as $n) {
            $respuesta->assertSee("Campaña número {$n}");
        }
        $respuesta->assertDontSee('Campaña número 7')->assertDontSee('Campaña número 8');
    }

    public function test_la_que_cierra_en_una_semana_o_menos_se_marca_como_urgente(): void
    {
        $this->entrarComo();
        $this->campaniaDeEgresados('Cierra pronto', 3, 2, 0);

        $this->get(route('seguimiento-egresado.index'))->assertSee('sg-aviso-urgente', false);

        $this->campaniaDeEgresados('Cierra lejos', 20, 2, 0);
        $html = $this->get(route('seguimiento-egresado.index'))->getContent();
        $this->assertSame(1, substr_count($html, 'sg-aviso-urgente'), 'solo la de 3 días');
    }

    public function test_las_campanias_de_empleadores_muestran_enlaces_respondidos(): void
    {
        $this->entrarComo();
        $campania = $this->campania(['nombre' => 'Demanda de habilidades 2026', 'tipo' => 'EMPRESAS']);
        $this->invitacionEmpresa($campania, 'MERCADO', null, ['estado' => 'COMPLETADA']);
        $this->invitacionEmpresa($campania, 'MERCADO');

        $this->get(route('seguimiento-egresado.index'))
            ->assertSee('Demanda de habilidades 2026')
            ->assertSee('Para empleadores')
            ->assertSee('1 de 2');
    }

    public function test_sin_campanias_abiertas_lo_dice_y_no_inventa_cifras(): void
    {
        $this->entrarComo();
        $this->campania(['estado' => 'CERRADA']);

        $this->get(route('seguimiento-egresado.index'))->assertOk()
            ->assertSee('No hay campañas abiertas')
            ->assertDontSee('Ver todas las campañas');
    }

    public function test_quien_no_es_personal_no_ve_los_avisos(): void
    {
        $this->campaniaDeEgresados('Campaña interna', 10, 2, 0);

        $this->get(route('seguimiento-egresado.index'))->assertOk()
            ->assertDontSee('Por cerrar')
            ->assertDontSee('Campaña interna');
    }
}
