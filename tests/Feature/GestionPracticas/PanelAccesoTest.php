<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Corre sobre SQLite en memoria: el acceso al panel exige iniciar sesión y la portada decide a dónde enviar. */
final class PanelAccesoTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_quien_tiene_acceso_al_modulo_pasa_directo_al_panel_sin_ver_la_portada(): void
    {
        Permission::findOrCreate(Acceso::VER, 'web');
        $user = User::factory()->create();
        $user->givePermissionTo(Acceso::VER);

        $this->actingAs($user)->get(route('gestion-practicas.index'))
            ->assertRedirect(route('gestion-practicas.panel'));
    }

    public function test_quien_inicio_sesion_sin_permiso_del_modulo_ve_la_portada(): void
    {
        $this->actingAs(User::factory()->create())->get(route('gestion-practicas.index'))
            ->assertOk()->assertSee('Gestión de Prácticas');
    }
}
