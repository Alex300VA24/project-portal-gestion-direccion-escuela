<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_no_accede_a_administracion_ni_api(): void
    {
        $this->get('/gestion-horarios/administracion')->assertRedirect('/login');
        $this->get('/gestion-horarios/crear')->assertRedirect('/login');
        $this->get('/gestion-horarios/mi-disponibilidad')->assertRedirect('/login');
        $this->getJson('/api/gestion-horarios/administracion')->assertUnauthorized();
        $this->post('/gestion-horarios')->assertRedirect('/login');
    }

    public function test_usuario_sin_rol_no_accede(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/gestion-horarios/administracion')->assertForbidden();
        $this->get('/gestion-horarios/crear')->assertForbidden();
        $this->getJson('/api/gestion-horarios/administracion')->assertForbidden();
        $this->post('/gestion-horarios')->assertForbidden();
    }

    public function test_roles_institucionales_consultan_pero_no_crean(): void
    {
        foreach (['director_escuela', 'secretaria'] as $rol) {
            Role::findOrCreate($rol, 'web');
            $usuario = User::factory()->create();
            $usuario->assignRole($rol);
            $this->actingAs($usuario)->get('/gestion-horarios/administracion')->assertOk();
            $this->getJson('/api/gestion-horarios/administracion')->assertOk();
            $this->get('/gestion-horarios/crear')->assertForbidden();
            $this->post('/gestion-horarios')->assertForbidden();
        }
    }

    public function test_presentacion_publica_no_expone_datos_academicos(): void
    {
        $this->get('/gestion-horarios')->assertOk()->assertSee('Gestión de Horarios');
        $this->getJson('/api/gestion-horarios')->assertOk()->assertExactJson([
            'module' => 'Gestión de Horarios',
            'schema' => 'gestion_horarios',
        ]);
    }

    public function test_consulta_publica_solo_devuelve_metadatos_publicados(): void
    {
        DB::statement("ATTACH DATABASE ':memory:' AS gestion_horarios");
        DB::statement('CREATE TABLE gestion_horarios.horario (id_horario INTEGER, id_periodo INTEGER, ambito TEXT, version INTEGER, estado TEXT, publicado_en TEXT, id_creado_por INTEGER)');
        DB::table('gestion_horarios.horario')->insert([
            ['id_horario' => 1, 'id_periodo' => 1, 'ambito' => 'GENERAL', 'version' => 1, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-01-01', 'id_creado_por' => 42],
            ['id_horario' => 2, 'id_periodo' => 1, 'ambito' => 'GENERAL', 'version' => 2, 'estado' => 'APROBADO', 'publicado_en' => null, 'id_creado_por' => 42],
            ['id_horario' => 3, 'id_periodo' => 1, 'ambito' => 'GENERAL', 'version' => 3, 'estado' => 'PUBLICADO', 'publicado_en' => null, 'id_creado_por' => 42],
        ]);
        $this->get('/gestion-horarios/catalogo-publico')->assertOk()->assertSee('GENERAL · versión 1')
            ->assertDontSee('GENERAL · versión 2')->assertDontSee('GENERAL · versión 3')->assertDontSee('id_creado_por');
        $this->getJson('/gestion-horarios/publicados')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_horario', 1)->assertJsonMissingPath('data.0.id_creado_por');
    }
}
