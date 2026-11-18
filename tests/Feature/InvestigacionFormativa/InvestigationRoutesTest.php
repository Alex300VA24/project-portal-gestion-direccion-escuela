<?php

namespace Tests\Feature\InvestigacionFormativa;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class InvestigationRoutesTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_guest_is_redirected_from_the_web_entry(): void
    {
        $this->get('/investigacion-formativa')
            ->assertRedirect('/login');
    }

    public function test_guest_receives_json_401_from_the_api(): void
    {
        $this->getJson('/api/investigacion-formativa')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_is_denied(): void
    {
        $this->actingAs($this->datos['users']['sin_mapa'])
            ->get('/investigacion-formativa')
            ->assertForbidden();

        $this->actingAs($this->datos['users']['sin_mapa'])
            ->getJson('/api/investigacion-formativa')
            ->assertForbidden();
    }

    public function test_unrelated_role_is_denied(): void
    {
        $visitante = Role::firstOrCreate(['name' => 'visitante', 'guard_name' => 'web']);
        $usuario = User::factory()->create([
            'name' => 'Visitante IF',
            'email' => 'visitante.if@example.test',
        ]);
        $usuario->assignRole($visitante);

        $this->actingAs($usuario)
            ->get('/investigacion-formativa')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->getJson('/api/investigacion-formativa')
            ->assertForbidden();
    }

    public function test_secretary_role_receives_the_module_metadata(): void
    {
        $this->actingAs($this->datos['users']['secretaria'])
            ->getJson('/api/investigacion-formativa')
            ->assertOk()
            ->assertExactJson(['module' => 'Investigación Formativa']);
    }

    public function test_secretary_without_academic_mapping_is_denied_on_the_web_entry(): void
    {
        $this->actingAs($this->datos['users']['secretaria'])
            ->get('/investigacion-formativa')
            ->assertForbidden();
    }

    public function test_director_entry_redirects_to_the_director_workspace(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get('/investigacion-formativa')
            ->assertRedirect(route('investigacion-formativa.director.index'));

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.index'))
            ->assertOk()
            ->assertSee('Ingeniería Informática');
    }

    public function test_teacher_entry_redirects_to_the_teacher_workspace(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->get('/investigacion-formativa')
            ->assertRedirect(route('investigacion-formativa.docente.cursos.index'));

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.docente.cursos.index'))
            ->assertOk()
            ->assertSee('Investigación Formativa I');
    }

    public function test_student_entry_redirects_to_the_student_workspace(): void
    {
        $this->actingAs($this->datos['users']['alumno_a'])
            ->get('/investigacion-formativa')
            ->assertRedirect(route('investigacion-formativa.alumno.cursos.index'));

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get(route('investigacion-formativa.alumno.cursos.index'))
            ->assertOk()
            ->assertSee('Investigación Formativa I');
    }

    public function test_multi_role_user_gets_the_workspace_selector(): void
    {
        $this->actingAs($this->datos['users']['multi'])
            ->get('/investigacion-formativa')
            ->assertOk()
            ->assertSee('Elige tu espacio de trabajo')
            ->assertSee('Docencia')
            ->assertSee('Estudiante')
            ->assertDontSee('Dirección de escuela');
    }

    public function test_api_returns_minimal_module_metadata(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->getJson('/api/investigacion-formativa')
            ->assertOk()
            ->assertExactJson(['module' => 'Investigación Formativa']);
    }

    public function test_scaffold_routes_are_removed(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get('/investigacion-formativa/crear')
            ->assertNotFound();

        $this->actingAs($this->datos['users']['director'])
            ->post('/investigacion-formativa')
            ->assertStatus(405);
    }

    public function test_workspace_routes_enforce_their_own_permission(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->get('/investigacion-formativa/director')
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->get('/investigacion-formativa/docente/cursos')
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get('/investigacion-formativa/director')
            ->assertForbidden();
    }

    public function test_las_rutas_de_grupos_y_titulos_exigen_autenticacion(): void
    {
        $this->crearGruposInvestigacion();

        $this->get(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertRedirect('/login');
        $this->post(route('investigacion-formativa.alumno.grupos.store', 9901))
            ->assertRedirect('/login');
        $this->get(route('investigacion-formativa.alumno.grupos.show', 9901))
            ->assertRedirect('/login');
        $this->patch(route('investigacion-formativa.alumno.titulos.update', 9901))
            ->assertRedirect('/login');
        $this->get(route('investigacion-formativa.docente.grupos.index', 9901))
            ->assertRedirect('/login');
        $this->get(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertRedirect('/login');
        $this->patch(route('investigacion-formativa.docente.titulos.update', [9901, 9951]))
            ->assertRedirect('/login');
    }

    public function test_las_rutas_de_grupos_separan_los_permisos_de_estudiante_y_docencia(): void
    {
        $this->crearGruposInvestigacion();

        $this->actingAs($this->datos['users']['docente_a'])
            ->get(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertForbidden();
        $this->actingAs($this->datos['users']['docente_a'])
            ->post(route('investigacion-formativa.alumno.grupos.store', 9901))
            ->assertForbidden();
        $this->actingAs($this->datos['users']['docente_a'])
            ->patch(route('investigacion-formativa.alumno.titulos.update', 9901))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get(route('investigacion-formativa.docente.grupos.index', 9901))
            ->assertForbidden();
        $this->actingAs($this->datos['users']['alumno_a'])
            ->get(route('investigacion-formativa.docente.grupos.show', [9901, 9951]))
            ->assertForbidden();
        $this->actingAs($this->datos['users']['alumno_a'])
            ->patch(route('investigacion-formativa.docente.titulos.update', [9901, 9951]))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.alumno.grupos.create', 9901))
            ->assertForbidden();
    }
}
