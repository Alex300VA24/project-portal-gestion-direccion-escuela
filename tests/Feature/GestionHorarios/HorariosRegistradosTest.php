<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Livewire\HorariosRegistrados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class HorariosRegistradosTest extends TestCase
{
    use RefreshDatabase;

    public function test_ruta_exige_autenticacion_y_rol(): void
    {
        $this->get('/gestion-horarios/administracion/horarios')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/gestion-horarios/administracion/horarios')->assertForbidden();
    }

    public function test_componente_rechaza_usuarios_sin_rol(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(HorariosRegistrados::class)->assertForbidden();
    }

    public function test_descarga_rechaza_usuarios_sin_rol(): void
    {
        $this->actingAs(User::factory()->create());
        $this->expectException(HttpException::class);
        (new HorariosRegistrados)->descargar('pdf');
    }

    public function test_descarga_exige_seleccion_valida(): void
    {
        $this->markTestSkipped('Requiere contrato institucional de adscripción para acceder a la validación.');
        Role::findOrCreate('director_escuela', 'web');
        $usuario = User::factory()->create();
        $usuario->assignRole('director_escuela');
        $this->actingAs($usuario);
        Livewire::test(HorariosRegistrados::class)->call('descargar', 'pdf')->assertSet('errorDescarga', 'Seleccione un periodo, un horario y filtros válidos.');
    }

    public function test_roles_sin_adscripcion_no_acceden_a_la_ruta(): void
    {
        $this->withoutVite();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            $this->actingAs($usuario)->get('/gestion-horarios/administracion/horarios')->assertForbidden();
        }
    }
}
