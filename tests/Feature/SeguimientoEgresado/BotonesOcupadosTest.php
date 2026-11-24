<?php

namespace Tests\Feature\SeguimientoEgresado;

use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Las acciones que tardan (enviar correos, cargar un archivo) dejan el botón ocupado en vez de parecer trabadas. */
class BotonesOcupadosTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    public function test_las_pantallas_internas_traen_el_comportamiento_de_boton_ocupado(): void
    {
        $this->entrarComo();

        $html = $this->get(route('seguimiento-egresado.index'))->assertOk()->getContent();

        $this->assertStringContainsString("classList.add('sg-ocupado')", $html);
        $this->assertStringContainsString('.sg-giro', file_get_contents(public_path('css/seguimiento-egresado.css')));
    }

    public function test_cada_accion_lenta_dice_que_esta_haciendo(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10, 2019);
        $this->egresado($promocion, 'PROXIMO');
        $campania = $this->campania(['tipo' => 'EGRESADOS']);

        $this->get(route('seguimiento-egresado.padron.promocion', $promocion))->assertOk()
            ->assertSee('data-sg-cargando="Cargando alumnos…"', false)
            ->assertSee('data-sg-cargando="Enviando enlaces…"', false)
            ->assertSee('Se envía un correo a cada alumno', false);
        $this->get(route('seguimiento-egresado.campanias.detalle', $campania))->assertOk()
            ->assertSee('data-sg-cargando="Enviando invitaciones…"', false)
            ->assertSee('data-sg-cargando="Enviando recordatorios…"', false);
    }
}
