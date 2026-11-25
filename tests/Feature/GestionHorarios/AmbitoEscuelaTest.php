<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Livewire\HorariosRegistrados;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\GestionOfertaAcademica;
use App\Modules\GestionHorarios\Services\GestionOfertaSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AmbitoEscuelaTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_sin_adscripcion_verificable_no_acceden_a_pantallas_academicas(): void
    {
        $this->withoutVite();
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            // El permiso general del módulo no acredita una escuela.
            self::assertSame([], (new AmbitoConsultaAdministrativa)->escuelas($usuario));
            $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()->assertSee('adscripción institucional')->assertDontSee(route('gestion-horarios.oferta'));
            foreach (['oferta-academica', 'secciones', 'asignacion-docente', 'horarios', 'preparacion-generacion'] as $pagina) {
                $this->get('/gestion-horarios/administracion/'.$pagina.'?plan=999')->assertForbidden();
            }
            // Revisiones muestra únicamente un aviso seguro, sin consultar datos de otra escuela.
            $this->get('/gestion-horarios/administracion/revisiones')->assertOk()
                ->assertSee('verificar la escuela autorizada')
                ->assertDontSee('No hay revisiones registradas')
                ->assertDontSee('Revisiones pendientes');
        }
    }

    public function test_descarga_y_escrituras_son_denegadas_antes_de_consultar_datos(): void
    {
        Role::findOrCreate('director_escuela', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('director_escuela');
        $this->actingAs($usuario);
        foreach ([
            fn () => (new HorariosRegistrados)->descargar('pdf'),
            fn () => (new HorariosRegistrados)->descargar('xlsx'),
            fn () => (new GestionOfertaAcademica(DB::connection()))->crear($usuario, ['periodo' => 1, 'curso' => 999]),
            fn () => (new GestionOfertaSeccion(DB::connection()))->asociar($usuario, 1, 1, 999, null),
        ] as $operacion) {
            try {
                $operacion();
                self::fail('La operación debe estar bloqueada.');
            } catch (HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_escuelas_simuladas_en_la_identidad_no_superan_el_bloqueo_institucional(): void
    {
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
            foreach ([1, 2, null] as $escuela) {
                $usuario = User::factory()->create();
                $usuario->assignRole($rol);
                $usuario->setAttribute('id_escuela', $escuela);
                self::assertSame([], (new AmbitoConsultaAdministrativa)->escuelas($usuario));
            }
        }
        $sinRol = User::factory()->create();
        $sinRol->setAttribute('id_escuela', 1);
        self::assertSame([], (new AmbitoConsultaAdministrativa)->escuelas($sinRol));

        $multiplesEscuelas = User::factory()->create();
        $multiplesEscuelas->assignRole('director_escuela');
        $multiplesEscuelas->setAttribute('escuelas_autorizadas', [1, 2]);
        self::assertSame([], (new AmbitoConsultaAdministrativa)->escuelas($multiplesEscuelas));
    }
}
