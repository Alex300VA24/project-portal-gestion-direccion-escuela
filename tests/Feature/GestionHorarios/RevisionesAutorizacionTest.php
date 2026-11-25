<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Livewire\RevisionesOferta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class RevisionesAutorizacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_adscripcion_muestra_el_bloqueo_sin_consultar_datos(): void
    {
        Role::findOrCreate('secretaria', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('secretaria');

        Livewire::actingAs($usuario)->test(RevisionesOferta::class)
            ->assertSee('verificar la escuela autorizada')
            ->assertDontSee('No hay revisiones registradas');
    }
}
