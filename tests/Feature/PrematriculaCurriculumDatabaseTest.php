<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class PrematriculaCurriculumDatabaseTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar el modelo curricular.');
        }

        if (! DB::table('information_schema.views')
            ->where('table_schema', 'gestion_matriculas')
            ->where('table_name', 'v_historial_academico')
            ->exists()) {
            $this->markTestSkipped('Las migraciones curriculares aún no se ejecutaron.');
        }

        $this->createPrematriculaAcademicData();
    }

    public function test_required_curriculum_and_enrollment_objects_exist(): void
    {
        $coreTables = DB::table('information_schema.tables')
            ->where('table_schema', 'core')
            ->where('table_type', 'BASE TABLE')
            ->pluck('table_name');

        $this->assertTrue($coreTables->contains('persona'));
        $this->assertTrue($coreTables->contains('plan_estudios'));
        $this->assertTrue($coreTables->contains('curso'));
        $this->assertTrue($coreTables->contains('curso_plan'));
        $this->assertTrue($coreTables->contains('curso_prerequisito'));

        $matriculaTables = DB::table('information_schema.tables')
            ->where('table_schema', 'gestion_matriculas')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $this->assertSame([
            'acta_notas',
            'asistencia',
            'componente_evaluacion',
            'cronograma_matricula',
            'limite_creditos',
            'matricula',
            'matricula_detalle',
            'nota',
            'reserva_matricula',
        ], $matriculaTables);
    }

    public function test_user_is_linked_to_person_and_not_directly_to_student(): void
    {
        $columns = Schema::getColumnListing('seguridad.usuario');

        $this->assertContains('id_persona', $columns);
        $this->assertNotContains('id_estudiante', $columns);

        $student = DB::table('seguridad.usuario as u')
            ->join('core.estudiante as e', 'e.id_persona', '=', 'u.id_persona')
            ->where('u.id_usuario', 801)
            ->value('e.id_estudiante');

        $this->assertNull($student);
    }

    public function test_canonical_duplicate_tables_do_not_exist(): void
    {
        $this->assertFalse(Schema::hasTable('core.historial_academico'));
        $this->assertFalse(Schema::hasTable('core.prerrequisito_curso'));
    }

    public function test_prerequisite_cannot_reference_itself(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core.curso_prerequisito')->insert([
            'id_curso_plan' => 501,
            'id_curso_plan_requisito' => 501,
        ]);
    }

    public function test_prerequisite_courses_must_belong_to_the_same_study_plan(): void
    {
        DB::table('core.plan_estudios')->insert([
            'id_plan' => 51,
            'id_escuela' => 20,
            'codigo' => 'PLAN-2027',
            'nombre' => 'Plan de estudios 2027',
            'version' => '2027',
            'anio_aprobacion' => 2027,
            'fecha_vigencia_inicio' => '2027-01-01',
            'es_predeterminado' => false,
            'estado' => 'Vigente',
        ]);
        DB::table('core.curso_plan')->insert([
            'id_curso_plan' => 503,
            'id_plan' => 51,
            'id_curso' => 702,
            'ciclo' => 1,
            'tipo' => 'Obligatorio',
            'id_area' => 60,
            'creditos_requeridos' => 0,
        ]);

        $this->expectException(QueryException::class);

        DB::table('core.curso_prerequisito')->insert([
            'id_curso_plan' => 502,
            'id_curso_plan_requisito' => 503,
        ]);
    }

    public function test_history_view_publishes_approved_course_and_supports_prerequisite_check(): void
    {
        DB::table('core.curso_prerequisito')->insert([
            'id_curso_plan' => 502,
            'id_curso_plan_requisito' => 501,
        ]);
        DB::table('gestion_matriculas.cronograma_matricula')->insert([
            'id_cronograma' => 1,
            'id_periodo' => 202602,
            'tipo' => 'Regular',
            'fecha_inicio' => '2026-07-01 08:00:00-05',
            'fecha_fin' => '2026-07-15 18:00:00-05',
        ]);
        DB::table('gestion_matriculas.matricula')->insert([
            'id_matricula' => 1,
            'id_estudiante' => 1001,
            'id_periodo' => 202602,
            'id_cronograma' => 1,
            'id_solicitud_prematricula' => null,
            'estado' => 'Activa',
            'registrado_por' => 801,
        ]);
        DB::table('gestion_matriculas.matricula_detalle')->insert([
            'id_detalle' => 1,
            'id_matricula' => 1,
            'id_seccion' => 901,
            'estado' => 'Matriculado',
            'nota_final' => 15,
        ]);

        $history = DB::table('gestion_matriculas.v_historial_academico')
            ->where('id_estudiante', 1001)
            ->where('id_curso_plan', 501)
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Aprobado', $history->condicion);
        $this->assertEquals(15, $history->nota_final);

        $meetsPrerequisites = DB::selectOne(<<<'SQL'
            SELECT NOT EXISTS (
                SELECT 1
                FROM core.curso_prerequisito p
                WHERE p.id_curso_plan = ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM gestion_matriculas.v_historial_academico h
                      WHERE h.id_estudiante = ?
                        AND h.id_curso_plan = p.id_curso_plan_requisito
                        AND h.condicion = 'Aprobado'
                  )
            ) AS cumple
            SQL, [502, 1001]);

        $this->assertTrue($meetsPrerequisites->cumple);
    }
}
