<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Livewire\AmbientesAcademicos;
use App\Modules\GestionHorarios\Livewire\AsignacionDocente;
use App\Modules\GestionHorarios\Livewire\DisponibilidadDocente;
use App\Modules\GestionHorarios\Livewire\DistribucionSecciones;
use App\Modules\GestionHorarios\Livewire\HorariosRegistrados;
use App\Modules\GestionHorarios\Livewire\MiDisponibilidad;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use App\Modules\GestionHorarios\Livewire\PeriodosAcademicos;
use App\Modules\GestionHorarios\Livewire\PreparacionGeneracion;
use App\Modules\GestionHorarios\Livewire\RestriccionesAcademicas;
use App\Modules\GestionHorarios\Livewire\RevisionesOferta;
use App\Modules\GestionHorarios\Services\AmbitoRecursosCompartidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AmbitoConsultaAdministrativaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_escuela_no_accede_a_consultas_academicas(): void
    {
        Role::findOrCreate('secretaria', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('secretaria');
        $this->actingAs($usuario)->get('/gestion-horarios/administracion')->assertOk()
            ->assertSee('no se ha podido verificar su adscripción')
            ->assertDontSee('Revisar preparación');
        foreach ([OfertaAcademica::class, DistribucionSecciones::class, AsignacionDocente::class, DisponibilidadDocente::class, AmbientesAcademicos::class, RestriccionesAcademicas::class, PreparacionGeneracion::class, HorariosRegistrados::class, PeriodosAcademicos::class] as $componente) {
            Livewire::actingAs($usuario)->test($componente)->assertForbidden();
        }
    }

    public function test_escuela_autorizada_no_desbloquea_recursos_sin_contrato(): void
    {
        Role::findOrCreate('director_escuela', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('director_escuela');
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                return [7];
            }
        });
        foreach ([AsignacionDocente::class, DisponibilidadDocente::class, AmbientesAcademicos::class, RestriccionesAcademicas::class, PreparacionGeneracion::class, HorariosRegistrados::class, PeriodosAcademicos::class] as $componente) {
            Livewire::actingAs($usuario)->test($componente)->assertForbidden();
        }
    }

    public function test_proveedor_de_escuelas_indisponible_no_expone_consultas_administrativas(): void
    {
        Role::findOrCreate('secretaria', 'web')->givePermissionTo(Permission::findOrCreate('gestion-horarios.view', 'web'));
        $usuario = User::factory()->create();
        $usuario->assignRole('secretaria');
        app()->instance(ProveedorEscuelasAutorizadas::class, new class implements ProveedorEscuelasAutorizadas
        {
            public function escuelasVigentes(User $usuario): array
            {
                throw new RuntimeException('Seguridad/Core sin respuesta.');
            }
        });

        $this->actingAs($usuario)->get('/gestion-horarios/administracion')
            ->assertOk()->assertSee('no se ha podido verificar su adscripción');
        Livewire::actingAs($usuario)->test(OfertaAcademica::class)->assertForbidden();
        Livewire::actingAs($usuario)->test(RevisionesOferta::class)
            ->assertSee('verificar la escuela autorizada')->assertDontSee('No hay revisiones registradas');
    }

    public function test_proveedor_docente_indisponible_no_muestra_asignaciones(): void
    {
        $usuario = User::factory()->create();
        Role::findOrCreate('profesor', 'web');
        $usuario->assignRole('profesor');
        app()->instance(ProveedorIdentidadDocente::class, new class implements ProveedorIdentidadDocente
        {
            public function docenteId(User $usuario): ?int
            {
                throw new RuntimeException('Identidad docente temporalmente indisponible.');
            }
        });

        Livewire::actingAs($usuario)->test(MiDisponibilidad::class)
            ->assertSee('No se pudo verificar la vinculación docente')
            ->assertDontSee('Sus asignaciones pueden pertenecer a distintos ciclos');
    }

    public function test_contratos_de_recursos_no_habilitan_consultas_globales(): void
    {
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
        app()->instance(ProveedorAlcanceRecursosAcademicos::class, new class implements ProveedorAlcanceRecursosAcademicos
        {
            public function recursosVisibles(User $usuario, int $escuela, int $periodo): array
            {
                return ['escuela_id' => $escuela, 'periodo_id' => $periodo, 'docentes' => [41, 99], 'ambientes' => [101, 900]];
            }
        });

        self::assertSame([41, 99], (new AmbitoRecursosCompartidos)->consultar($usuario, 7, 1)['docentes']);
        foreach ([AsignacionDocente::class, DisponibilidadDocente::class, AmbientesAcademicos::class, RestriccionesAcademicas::class, PreparacionGeneracion::class, HorariosRegistrados::class, PeriodosAcademicos::class] as $componente) {
            Livewire::actingAs($usuario)->test($componente)->assertForbidden();
        }
    }
}
