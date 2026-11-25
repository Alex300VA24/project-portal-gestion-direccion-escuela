<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

final class ConsultaPublicadosInterfazTest extends TestCase
{
    use RefreshDatabase;

    public function test_exige_autenticacion(): void
    {
        $this->get('/gestion-horarios/consulta-publicados')->assertRedirect('/login');
    }

    public function test_error_de_consulta_no_se_presenta_como_lista_vacia(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/gestion-horarios/consulta-publicados')
            ->assertStatus(503)->assertSee('No se pudo consultar los horarios publicados')
            ->assertDontSee('No se encontraron horarios publicados');
    }

    #[RunInSeparateProcess]
    public function test_muestra_solo_metadatos_publicados_y_filtra_periodo(): void
    {
        $this->withoutVite();
        DB::statement("ATTACH DATABASE ':memory:' AS gestion_horarios");
        DB::statement("ATTACH DATABASE ':memory:' AS core");
        foreach ([
            'CREATE TABLE core.periodo_academico (id_periodo INTEGER, codigo TEXT)',
            'CREATE TABLE core.seccion (id_seccion INTEGER, id_periodo INTEGER, id_curso_plan INTEGER, codigo TEXT)',
            'CREATE TABLE core.curso_plan (id_curso_plan INTEGER, id_curso INTEGER, ciclo INTEGER, id_plan INTEGER)',
            'CREATE TABLE core.curso (id_curso INTEGER, codigo TEXT, nombre TEXT)',
            'CREATE TABLE core.plan_estudios (id_plan INTEGER, nombre TEXT)',
            'CREATE TABLE core.ambiente (id_ambiente INTEGER, codigo TEXT, nombre TEXT)',
            'CREATE TABLE core.docente (id_docente INTEGER, id_persona INTEGER)',
            'CREATE TABLE core.persona (id_persona INTEGER, nombres TEXT, apellido_paterno TEXT, apellido_materno TEXT)',
            'CREATE TABLE gestion_horarios.detalle_horario (id_horario INTEGER, id_actividad INTEGER, id_ambiente INTEGER)',
            'CREATE TABLE gestion_horarios.actividad_academica (id_actividad INTEGER, id_seccion INTEGER)',
            'CREATE TABLE gestion_horarios.asignacion_docente (id_actividad INTEGER, id_docente INTEGER, estado TEXT)',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('core.periodo_academico')->insert([['id_periodo' => 11, 'codigo' => '2026-I'], ['id_periodo' => 22, 'codigo' => '2026-II']]);
        DB::statement('CREATE TABLE gestion_horarios.horario (id_horario INTEGER, id_periodo INTEGER, ambito TEXT, version INTEGER, estado TEXT, publicado_en TEXT, dato_privado TEXT)');
        DB::table('gestion_horarios.horario')->insert([
            ['id_horario' => 1, 'id_periodo' => 11, 'ambito' => 'GENERAL', 'version' => 1, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-01-01', 'dato_privado' => 'PRIVADO'],
            ['id_horario' => 2, 'id_periodo' => 11, 'ambito' => 'BORRADOR OCULTO', 'version' => 2, 'estado' => 'BORRADOR', 'publicado_en' => null, 'dato_privado' => 'PRIVADO'],
            ['id_horario' => 3, 'id_periodo' => 22, 'ambito' => 'OTRO PERIODO', 'version' => 1, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-01-01', 'dato_privado' => 'PRIVADO'],
            ['id_horario' => 4, 'id_periodo' => 11, 'ambito' => 'SIN FECHA', 'version' => 3, 'estado' => 'PUBLICADO', 'publicado_en' => null, 'dato_privado' => 'PRIVADO'],
        ]);
        for ($id = 100; $id < 121; $id++) {
            DB::table('gestion_horarios.horario')->insert(['id_horario' => $id, 'id_periodo' => 11, 'ambito' => 'GENERAL', 'version' => $id, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-01-01', 'dato_privado' => 'PRIVADO']);
        }

        $this->actingAs(User::factory()->create())->get('/gestion-horarios/consulta-publicados?periodo=11')
            ->assertOk()->assertSee('GENERAL')->assertDontSee('BORRADOR OCULTO')->assertDontSee('OTRO PERIODO')
            ->assertDontSee('SIN FECHA')->assertDontSee('PRIVADO')->assertSee('periodo=11&amp;page=2', false);
        $this->get('/gestion-horarios/consulta-publicados?periodo=99')->assertOk()->assertSee('No se encontraron horarios publicados');
    }
}
