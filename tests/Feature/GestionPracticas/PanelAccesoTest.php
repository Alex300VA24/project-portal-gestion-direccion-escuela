<?php

namespace Tests\Feature\GestionPracticas;

use Tests\TestCase;

/** No requiere base de datos: el acceso al panel exige iniciar sesión. */
final class PanelAccesoTest extends TestCase
{
    public function test_las_pantallas_del_panel_exigen_iniciar_sesion(): void
    {
        foreach ([
            'gestion-practicas.panel',
            'gestion-practicas.empresas.index',
            'gestion-practicas.revision.convocatorias.index',
            'gestion-practicas.empresa.convocatorias.index',
            'gestion-practicas.estudiante.convocatorias.index',
            'gestion-practicas.supervisor.practicas.index',
        ] as $ruta) {
            $this->get(route($ruta))->assertRedirect('/login');
        }
    }

    public function test_la_portada_del_modulo_sigue_siendo_publica(): void
    {
        $this->get(route('gestion-practicas.index'))
            ->assertOk()->assertSee('Gestión de Prácticas')->assertSee('gestion_practicas');
    }
}
