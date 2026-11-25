<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

final class HorariosSemanalesPublicadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitado_no_accede(): void
    {
        $this->get('/gestion-horarios/semana-publicada?periodo=1&modalidad=ciclo&valor=1')->assertRedirect('/login');
    }

    #[RunInSeparateProcess]
    public function test_filtros_muestran_solo_actividades_publicadas_del_periodo(): void
    {
        $this->withoutVite();
        DB::statement("ATTACH DATABASE ':memory:' AS gestion_horarios");
        DB::statement("ATTACH DATABASE ':memory:' AS core");
        foreach ([
            'CREATE TABLE gestion_horarios.horario (id_horario INTEGER, id_periodo INTEGER, estado TEXT, publicado_en TEXT, ambito TEXT DEFAULT "GENERAL", version INTEGER DEFAULT 1)',
            'CREATE TABLE gestion_horarios.detalle_horario (id_horario INTEGER, id_actividad INTEGER, id_ambiente INTEGER)',
            'CREATE TABLE gestion_horarios.actividad_academica (id_actividad INTEGER, id_seccion INTEGER, dia_semana TEXT, hora_inicio TEXT, hora_fin TEXT, tipo_actividad TEXT)',
            'CREATE TABLE gestion_horarios.asignacion_docente (id_actividad INTEGER, id_docente INTEGER, estado TEXT)',
            'CREATE TABLE core.seccion (id_seccion INTEGER, id_periodo INTEGER, id_curso_plan INTEGER, codigo TEXT)',
            'CREATE TABLE core.curso_plan (id_curso_plan INTEGER, id_curso INTEGER, ciclo INTEGER, id_plan INTEGER DEFAULT 1)',
            'CREATE TABLE core.plan_estudios (id_plan INTEGER, nombre TEXT)',
            'CREATE TABLE core.periodo_academico (id_periodo INTEGER, codigo TEXT)',
            'CREATE TABLE core.curso (id_curso INTEGER, codigo TEXT, nombre TEXT)',
            'CREATE TABLE core.ambiente (id_ambiente INTEGER, codigo TEXT, nombre TEXT)',
            'CREATE TABLE core.docente (id_docente INTEGER, id_persona INTEGER)',
            'CREATE TABLE core.persona (id_persona INTEGER, nombres TEXT, apellido_paterno TEXT, apellido_materno TEXT)',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('core.periodo_academico')->insert([['id_periodo' => 1, 'codigo' => '2026-I'], ['id_periodo' => 2, 'codigo' => '2026-II'], ['id_periodo' => 3, 'codigo' => '2027-I']]);
        DB::table('core.plan_estudios')->insert(['id_plan' => 1, 'nombre' => 'Plan A']);
        DB::table('gestion_horarios.horario')->insert([
            ['id_horario' => 1, 'id_periodo' => 1, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-01-01'],
            ['id_horario' => 2, 'id_periodo' => 1, 'estado' => 'BORRADOR', 'publicado_en' => null],
            ['id_horario' => 3, 'id_periodo' => 1, 'estado' => 'PUBLICADO', 'publicado_en' => null],
            ['id_horario' => 4, 'id_periodo' => 2, 'estado' => 'PUBLICADO', 'publicado_en' => '2026-08-01'],
            ['id_horario' => 5, 'id_periodo' => 3, 'estado' => 'PUBLICADO', 'publicado_en' => '2027-01-01'],
        ]);
        DB::table('core.curso')->insert([['id_curso' => 1, 'codigo' => 'C1', 'nombre' => 'Curso publicado'], ['id_curso' => 2, 'codigo' => 'C2', 'nombre' => 'Curso privado'], ['id_curso' => 3, 'codigo' => 'C3', 'nombre' => 'Curso posterior']]);
        DB::table('core.curso_plan')->insert([['id_curso_plan' => 1, 'id_curso' => 1, 'ciclo' => 2], ['id_curso_plan' => 2, 'id_curso' => 2, 'ciclo' => 2], ['id_curso_plan' => 3, 'id_curso' => 3, 'ciclo' => 3]]);
        DB::table('core.seccion')->insert([['id_seccion' => 1, 'id_periodo' => 1, 'id_curso_plan' => 1, 'codigo' => 'A'], ['id_seccion' => 2, 'id_periodo' => 1, 'id_curso_plan' => 2, 'codigo' => 'A'], ['id_seccion' => 3, 'id_periodo' => 2, 'id_curso_plan' => 2, 'codigo' => 'A'], ['id_seccion' => 4, 'id_periodo' => 2, 'id_curso_plan' => 3, 'codigo' => 'B']]);
        DB::table('core.ambiente')->insert([['id_ambiente' => 7, 'codigo' => 'A7', 'nombre' => 'Aula 7'], ['id_ambiente' => 8, 'codigo' => 'B8', 'nombre' => 'Laboratorio 8'], ['id_ambiente' => 99, 'codigo' => 'PR99', 'nombre' => 'Aula privada']]);
        DB::table('core.docente')->insert([['id_docente' => 8, 'id_persona' => 9], ['id_docente' => 9, 'id_persona' => 10], ['id_docente' => 99, 'id_persona' => 99]]);
        DB::table('core.persona')->insert([['id_persona' => 9, 'nombres' => 'Ana', 'apellido_paterno' => 'Docente', 'apellido_materno' => ''], ['id_persona' => 10, 'nombres' => 'Beatriz', 'apellido_paterno' => 'Profesora', 'apellido_materno' => ''], ['id_persona' => 99, 'nombres' => 'Docente privado', 'apellido_paterno' => 'Oculto', 'apellido_materno' => '']]);
        foreach ([1, 2, 3, 4] as $id) {
            DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => $id, 'id_seccion' => $id === 4 ? 3 : ($id === 1 ? 1 : 2), 'dia_semana' => 'LUNES', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'tipo_actividad' => 'TEORIA']);
            DB::table('gestion_horarios.detalle_horario')->insert(['id_horario' => $id === 2 ? 2 : ($id === 3 ? 3 : 1), 'id_actividad' => $id, 'id_ambiente' => $id === 2 ? 99 : 7]);
            DB::table('gestion_horarios.asignacion_docente')->insert(['id_actividad' => $id, 'id_docente' => $id === 2 ? 99 : 8, 'estado' => 'CONFIRMADA']);
        }
        DB::table('gestion_horarios.actividad_academica')->insert(['id_actividad' => 5, 'id_seccion' => 4, 'dia_semana' => 'MARTES', 'hora_inicio' => '09:00', 'hora_fin' => '11:00', 'tipo_actividad' => 'TEORIA']);
        DB::table('gestion_horarios.detalle_horario')->insert(['id_horario' => 4, 'id_actividad' => 5, 'id_ambiente' => 8]);
        DB::table('gestion_horarios.asignacion_docente')->insert(['id_actividad' => 5, 'id_docente' => 9, 'estado' => 'CONFIRMADA']);
        $this->actingAs(User::factory()->create());
        $this->get('/gestion-horarios/consulta-publicados?periodo=1')->assertOk()
            ->assertSee('2026-I')->assertSee('A7')->assertSee('Ana')->assertDontSee('Beatriz')->assertDontSee('B8')->assertDontSee('PR99')->assertDontSee('Docente privado')
            ->assertSee('Seleccione un ciclo')->assertSee('Seleccione un docente');
        $this->get('/gestion-horarios/consulta-publicados?periodo=2')->assertOk()->assertSee('Beatriz')->assertSee('B8')->assertDontSee('A7');
        $this->get('/gestion-horarios/consulta-publicados?periodo=3')->assertOk()->assertDontSee('Beatriz')->assertDontSee('A7')->assertSee('no tiene actividades publicadas');
        foreach (['ciclo&valor=2&seccion=A', 'ambiente&valor=7', 'docente&valor=8'] as $filtro) {
            $this->get('/gestion-horarios/semana-publicada?periodo=1&modalidad='.$filtro)->assertOk()
                ->assertSee('Curso publicado')->assertSee('Ana Docente')->assertSee('Aula 7')->assertDontSee('Curso privado');
        }
        $this->get('/gestion-horarios/semana-publicada?periodo=1&modalidad=docente&valor=99')->assertSessionHasErrors('valor');
        $this->get('/gestion-horarios/semana-publicada?periodo=1&modalidad=ciclo&valor=2&seccion=B')->assertSessionHasErrors('seccion');
        $this->get('/gestion-horarios/semana-publicada?periodo=2&modalidad=docente&valor=8')->assertSessionHasErrors('valor');
        $this->get('/gestion-horarios/semana-publicada?periodo=1&modalidad=privada&valor=1')->assertSessionHasErrors('modalidad');
    }
}
