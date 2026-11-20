<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Livewire\MiDisponibilidad;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class InicioModuloTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_no_ve_navegacion_administrativa(): void
    {
        $this->withoutVite();
        $this->get('/gestion-horarios')->assertOk()->assertSee('Horarios publicados')
            ->assertDontSee(route('gestion-horarios.oferta'))->assertDontSee(route('gestion-horarios.restricciones'));
    }

    public function test_roles_sin_adscripcion_no_ven_pantallas_academicas(): void
    {
        $this->withoutVite();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            $respuesta = $this->actingAs($usuario)->get('/gestion-horarios')->assertOk();
            $respuesta->assertSee('Ingreso administrativo confirmado');
            foreach (['periodos', 'oferta', 'secciones', 'asignacion-docente', 'disponibilidad-docente', 'ambientes', 'restricciones', 'revisiones', 'preparacion'] as $ruta) {
                $respuesta->assertDontSee(route('gestion-horarios.'.$ruta));
            }
            $respuesta->assertSee('adscripción institucional');
        }
    }

    public function test_usuario_sin_rol_no_ve_accesos_administrativos(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/gestion-horarios')->assertOk()
            ->assertDontSee(route('gestion-horarios.oferta'))->assertDontSee(route('gestion-horarios.restricciones'));
    }

    public function test_escuela_autorizada_solo_ve_accesos_con_alcance_implementado(): void
    {
        $this->withoutVite();
        Role::findOrCreate('secretaria', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('secretaria');
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [7];
            }
        });

        $respuesta = $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()
            ->assertSee(route('gestion-horarios.oferta'))->assertSee(route('gestion-horarios.secciones'));
        foreach (['asignacion-docente', 'disponibilidad-docente', 'ambientes', 'restricciones', 'periodos', 'preparacion', 'horarios'] as $ruta) {
            $respuesta->assertDontSee(route('gestion-horarios.'.$ruta));
        }
    }

    public function test_director_y_secretaria_autorizados_ven_solo_herramientas_habilitadas(): void
    {
        $this->withoutVite();
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [7];
            }
        });
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()
                ->assertSee(route('gestion-horarios.oferta'))->assertSee(route('gestion-horarios.secciones'))
                ->assertDontSee(route('gestion-horarios.asignacion-docente'))->assertDontSee(route('gestion-horarios.mi-disponibilidad'));
        }
    }

    public function test_secretaria_sin_permiso_no_puede_usar_consultas_academicas(): void
    {
        $this->withoutVite();
        Role::findOrCreate('secretaria', 'web');
        $usuario = User::factory()->create();
        $usuario->assignRole('secretaria');
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [7];
            }
        });
        $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()->assertSee('no tiene acreditado el permiso')
            ->assertDontSee(route('gestion-horarios.oferta'));
        Livewire::actingAs($usuario)->test(OfertaAcademica::class)->assertForbidden();
    }

    public function test_profesor_y_alumno_ven_sus_areas_y_las_rutas_respetan_roles(): void
    {
        $this->withoutVite();
        foreach (['profesor', 'alumno'] as $rol) {
            Role::findOrCreate($rol, 'web');
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            $respuesta = $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()
                ->assertSee(route('gestion-horarios.consulta-publicados'))->assertDontSee(route('gestion-horarios.oferta'))
                ->assertDontSee('inicie sesión con una cuenta');
            if ($rol === 'profesor') {
                $respuesta->assertSee('Mi espacio docente')->assertSee(route('gestion-horarios.mi-disponibilidad'));
                $this->get(route('gestion-horarios.mi-disponibilidad'))->assertOk()->assertSee('No se pudo verificar la vinculación docente');
                $this->get(route('gestion-horarios.mi-disponibilidad'))->assertOk()->assertSee('Acceso docente confirmado');
            } else {
                $respuesta->assertSee('Mi espacio académico')->assertDontSee('Mi disponibilidad docente')->assertDontSee(route('gestion-horarios.mi-disponibilidad'));
                $this->get(route('gestion-horarios.mi-disponibilidad'))->assertForbidden();
                Livewire::actingAs($usuario)->test(MiDisponibilidad::class)->assertForbidden();
            }
            $this->get(route('gestion-horarios.oferta'))->assertForbidden();
        }
    }
}
