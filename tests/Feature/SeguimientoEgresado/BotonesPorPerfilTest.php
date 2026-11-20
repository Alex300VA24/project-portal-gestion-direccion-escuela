<?php

namespace Tests\Feature\SeguimientoEgresado;

use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** La Dirección consulta el padrón: no se le ofrecen botones de escritura que terminarían en un 403. */
class BotonesPorPerfilTest extends TestCase
{
    use FabricaEmpresasIndicadores, UsaBaseDeDatos;

    public function test_la_secretaria_ve_las_acciones_de_la_promocion(): void
    {
        $this->entrarComo();
        $promocion = $this->cohorte(10);

        $this->get(route('seguimiento-egresado.padron'))->assertOk()->assertSee('+ Nueva promoción');
        $this->get(route('seguimiento-egresado.padron.promocion', $promocion))->assertOk()
            ->assertSee('Cargar alumnos')->assertSee('Editar');
        $this->get(route('seguimiento-egresado.padron.consulta'))->assertOk()->assertSee('+ Registrar un alumno');
    }

    public function test_la_direccion_consulta_sin_botones_de_escritura(): void
    {
        $this->entrarComo('DIRECTOR');
        $promocion = $this->cohorte(10);

        $this->get(route('seguimiento-egresado.padron'))->assertOk()->assertDontSee('+ Nueva promoción');
        $this->get(route('seguimiento-egresado.padron.promocion', $promocion))->assertOk()
            ->assertDontSee('Cargar alumnos')->assertDontSee('Enviar enlaces')->assertDontSee('onclick="abrirEditarPromocion')
            ->assertSee('Solo la Secretaría');
        $this->get(route('seguimiento-egresado.padron.consulta'))->assertOk()->assertDontSee('+ Registrar un alumno')
            ->assertDontSee('abrirModalActualizarEstadoDesdeFicha()">Actualizar');
    }

    public function test_la_direccion_sigue_sin_poder_importar(): void
    {
        $this->entrarComo('DIRECTOR');

        $this->post(route('seguimiento-egresado.padron.importar'))->assertForbidden();
    }
}
