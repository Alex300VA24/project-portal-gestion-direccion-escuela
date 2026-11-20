<?php

namespace Tests\Feature\SeguimientoEstudiante;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

final class LotesSecretariaTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL con las migraciones modulares.');
        }
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-19 12:00', 'America/Lima'));
        $this->createPrematriculaAcademicData();
    }

    public function test_scoped_reception_versions_changed_references_and_can_confirm_only_the_latest_valid_revision(): void
    {
        $cuenta = $this->cuenta(1002);
        $guardar = route('seguimiento-estudiante.secretaria.lotes.guardar');
        $form = fn () => $this->formulario('matriculas', "codigo_estudiante,curso,seccion,vez\nE2026001,CP-501,B,1");
        $this->actingAs($cuenta)->postJson($guardar, $form())->assertNotFound();
        DB::table('seguimiento_estudiante.secretaria_ambito')->insert([
            'id_persona' => 1002, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $this->get(route('seguimiento-estudiante.secretaria.lotes.index'))
            ->assertOk()->assertSee('Ingeniería Informática');
        $this->postJson($guardar, array_replace($form(), ['ambito' => '21-202602']))->assertNotFound();
        $this->post($guardar, $form())->assertRedirect();
        $lote = DB::table('seguimiento_estudiante.recepcion_lote')->value('id_lote');
        $mostrar = fn (int $revision) => route('seguimiento-estudiante.secretaria.lotes.mostrar', [$lote, $revision]);
        $confirmar = fn (int $revision) => route('seguimiento-estudiante.secretaria.lotes.confirmar', [$lote, $revision]);

        $this->get($mostrar(1))->assertOk()->assertSee('0 de 1 filas con referencias verificadas.')
            ->assertDontSee('Confirmar validación')->assertDontSee('E2026001');
        $this->postJson($confirmar(1))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->post($guardar, $form())->assertRedirect($mostrar(1));
        $this->assertDatabaseCount('seguimiento_estudiante.recepcion_revision', 1);

        DB::table('core.seccion')->where('id_seccion', 901)->update(['codigo' => 'B']);
        $this->post($guardar, $form())->assertRedirect($mostrar(2));
        $this->get($mostrar(2))->assertOk()->assertSee('1 de 1 filas con referencias verificadas.')->assertSee('Confirmar validación');
        $this->postJson($confirmar(1))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->post($confirmar(2))->assertRedirect($mostrar(2));
        $this->post($confirmar(2))->assertRedirect($mostrar(2));
        $this->get($mostrar(2))->assertOk()->assertSee('Validación confirmada para entrega.');
        $this->assertDatabaseHas('seguimiento_estudiante.recepcion_revision', [
            'id_lote' => $lote, 'version' => 2, 'confirmado_por' => $cuenta->getKey(),
        ]);
        $this->assertDatabaseCount('gestion_matriculas.matricula', 0);
        $this->assertDatabaseCount('seguimiento_estudiante.recepcion_revision', 2);

        $this->actingAs($this->cuenta(1001))->get($mostrar(2))->assertNotFound();
        $this->postJson($confirmar(2))->assertNotFound();
    }

    public function test_notes_and_attendance_resolve_official_enrollment_without_modifying_it(): void
    {
        $cuenta = $this->cuenta(1002);
        DB::table('seguimiento_estudiante.secretaria_ambito')->insert([
            'id_persona' => 1002, 'id_escuela' => 20, 'id_periodo' => 202602,
        ]);
        $cronograma = DB::table('gestion_matriculas.cronograma_matricula')->insertGetId([
            'id_periodo' => 202602, 'tipo' => 'Regular', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-09-01',
        ], 'id_cronograma');
        $matricula = DB::table('gestion_matriculas.matricula')->insertGetId([
            'id_estudiante' => 1001, 'id_periodo' => 202602, 'id_cronograma' => $cronograma, 'estado' => 'Matriculada',
            'registrado_por' => 801,
        ], 'id_matricula');
        $detalle = DB::table('gestion_matriculas.matricula_detalle')->insertGetId([
            'id_matricula' => $matricula, 'id_seccion' => 901, 'estado' => 'Matriculado',
        ], 'id_detalle');
        $componente = DB::table('gestion_matriculas.componente_evaluacion')->insertGetId([
            'id_seccion' => 901, 'nombre' => 'Parcial 1', 'orden' => 1, 'peso' => 100,
        ], 'id_componente');
        $this->actingAs($cuenta);
        $guardar = route('seguimiento-estudiante.secretaria.lotes.guardar');
        $notas = <<<CSV
            registration,student,course,section,unit,value
            DETALLE:$detalle,,,,Parcial 1,18
            ,E2026001,CP-501,A,Parcial 1,16
            DETALLE:$detalle,E2026002,,,Parcial 1,17
            DETALLE:$detalle,,,,NPR,12
            CSV;
        $this->post($guardar, $this->formulario('notas', $notas))->assertRedirect();
        $this->post($guardar, $this->formulario('asistencia', "student,course,section,total,absent\nE2026001,CP-501,A,20,3"))->assertRedirect();

        $revision = fn (string $tipo) => json_decode(DB::table('seguimiento_estudiante.recepcion_revision as revision')
            ->join('seguimiento_estudiante.recepcion_lote as lote', 'lote.id_lote', '=', 'revision.id_lote')
            ->where('lote.tipo', $tipo)->value('revision.resultado'), true);
        $resultado = $revision('notas');
        $this->assertSame([2], array_keys($resultado['registros']));
        $this->assertSame([3, 4, 5], array_keys($resultado['errores']));
        $this->assertSame($componente, $resultado['registros'][2]['id_componente']);
        $this->assertStringNotContainsString('E2026002', json_encode($resultado));
        $this->assertSame('3', $revision('asistencia')['registros'][2]['absent']);
        $this->assertDatabaseCount('gestion_matriculas.nota', 0);
        $this->assertDatabaseCount('gestion_matriculas.asistencia', 0);
    }

    private function cuenta(int $persona): User
    {
        $cuenta = User::factory()->create();
        DB::table('seguimiento_estudiante.identidad_cuenta')->insert(['id_usuario' => $cuenta->getKey(), 'id_persona' => $persona]);
        foreach (['view', 'secretaria.lotes'] as $permiso) {
            $cuenta->givePermissionTo(Permission::findOrCreate('seguimiento-estudiante.'.$permiso, 'web'));
        }

        return $cuenta;
    }

    private function formulario(string $tipo, string $contenido): array
    {
        return [
            'ambito' => '20-202602',
            'tipo' => $tipo,
            'referencia' => 'Corte académico de noviembre',
            'fecha_corte' => '2026-11-18',
            'archivo' => UploadedFile::fake()->createWithContent('corte.csv', $contenido),
        ];
    }
}
