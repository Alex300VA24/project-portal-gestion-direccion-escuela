<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Livewire\MiDisponibilidad;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

    #[RunInSeparateProcess]
    public function test_escuela_autorizada_solo_ve_accesos_con_alcance_implementado(): void
    {
        $this->withoutVite();
        $this->prepararContextoAcademico();
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

        $this->actingAs($usuario)->get('/gestion-horarios')->assertOk()
            ->assertSee('PLAN-AUTORIZADO')->assertDontSee('PLAN-AJENO')
            ->assertDontSee(route('gestion-horarios.oferta'))->assertDontSee(route('gestion-horarios.secciones'));
        $this->get('/gestion-horarios/administracion?etapa=preparacion&plan=72&periodo=11')->assertOk()
            ->assertDontSee(route('gestion-horarios.oferta'))->assertDontSee(route('gestion-horarios.secciones'));
        $respuesta = $this->get('/gestion-horarios/administracion?etapa=preparacion&plan=71&periodo=11')->assertOk()
            ->assertSee(route('gestion-horarios.oferta', ['plan' => 71, 'periodo' => 11]))
            ->assertSee(route('gestion-horarios.secciones', ['plan' => 71, 'periodo' => 11]));
        foreach (['asignacion-docente', 'disponibilidad-docente', 'ambientes', 'restricciones', 'periodos', 'preparacion', 'horarios'] as $ruta) {
            $respuesta->assertDontSee(route('gestion-horarios.'.$ruta));
        }
    }

    #[RunInSeparateProcess]
    public function test_director_y_secretaria_autorizados_ven_solo_herramientas_habilitadas(): void
    {
        $this->withoutVite();
        $this->prepararContextoAcademico();
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
            $this->actingAs($usuario)->get('/gestion-horarios/administracion?etapa=preparacion&plan=71&periodo=11')->assertOk()
                ->assertSee(route('gestion-horarios.oferta', ['plan' => 71, 'periodo' => 11]))
                ->assertSee(route('gestion-horarios.secciones', ['plan' => 71, 'periodo' => 11]))
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
                $respuesta->assertSee('Consulta académica')->assertDontSee('Mi disponibilidad docente')->assertDontSee(route('gestion-horarios.mi-disponibilidad'));
                $this->get(route('gestion-horarios.mi-disponibilidad'))->assertForbidden();
                Livewire::actingAs($usuario)->test(MiDisponibilidad::class)->assertForbidden();
            }
            $this->get(route('gestion-horarios.oferta'))->assertForbidden();
        }
    }

    /** Fixture de lectura aislada, sin autorizar recursos compartidos. */
    private function prepararContextoAcademico(): void
    {
        DB::statement("ATTACH DATABASE ':memory:' AS core");
        DB::statement("ATTACH DATABASE ':memory:' AS gestion_horarios");
        foreach ([
            'CREATE TABLE core.plan_estudios (id_plan INTEGER, codigo TEXT, nombre TEXT, id_escuela INTEGER)',
            'CREATE TABLE core.periodo_academico (id_periodo INTEGER, codigo TEXT, fecha_inicio TEXT)',
            'CREATE TABLE core.curso_plan (id_curso_plan INTEGER, id_plan INTEGER)',
            'CREATE TABLE core.seccion (id_periodo INTEGER, id_curso_plan INTEGER)',
            'CREATE TABLE gestion_horarios.oferta_curso (id_periodo INTEGER, id_curso_plan INTEGER)',
            'CREATE TABLE gestion_horarios.demanda_instantanea (id_demanda INTEGER, id_periodo INTEGER)',
            'CREATE TABLE gestion_horarios.demanda_curso (id_demanda INTEGER, id_curso_plan INTEGER)',
            'CREATE TABLE gestion_horarios.revision_oferta_pendiente (id_revision INTEGER)',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('core.plan_estudios')->insert([
            ['id_plan' => 71, 'codigo' => 'PLAN-AUTORIZADO', 'nombre' => 'Plan autorizado', 'id_escuela' => 7],
            ['id_plan' => 72, 'codigo' => 'PLAN-AJENO', 'nombre' => 'Plan ajeno', 'id_escuela' => 8],
        ]);
        DB::table('core.periodo_academico')->insert(['id_periodo' => 11, 'codigo' => '2026-II', 'fecha_inicio' => '2026-08-01']);
        DB::table('core.curso_plan')->insert(['id_curso_plan' => 1, 'id_plan' => 71]);
        DB::table('gestion_horarios.oferta_curso')->insert(['id_periodo' => 11, 'id_curso_plan' => 1]);
    }

}
