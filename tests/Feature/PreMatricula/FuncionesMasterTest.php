<?php

namespace Tests\Feature\PreMatricula;

use App\Models\User;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\HistorialAcademico;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Modules\PreMatricula\Services\PlanEstudios;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesPrematriculaAcademicData;
use Tests\TestCase;

/**
 * Funciones de la rama master de jckba/prematricula adaptadas al portal:
 * períodos, plan de estudios, cuentas, registro, historial y preferencia de turno.
 */
final class FuncionesMasterTest extends TestCase
{
    use CreatesPrematriculaAcademicData;
    use DatabaseTransactions;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar esquemas y restricciones.');
        }

        $this->createPrematriculaAcademicData();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->director = $this->usuario('revisor@example.test', 'director_escuela');
    }

    // ---- Períodos ----

    public function test_la_coordinacion_crea_un_periodo_y_lo_lista(): void
    {
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.periodos.store'), [
            'codigo' => '2027-I', 'fecha_inicio' => '2027-03-01', 'fecha_fin' => '2027-07-15', 'estado' => 'PLANIFICACION',
        ])->assertSessionHasNoErrors()->assertRedirect(route('pre-matricula.coordinacion.periodos'));

        $periodo = PeriodoAcademico::query()->where('codigo', '2027-I')->sole();
        $this->assertSame(2027, (int) $periodo->anio);
        $this->assertSame('I', $periodo->tipo);
        $this->assertSame(20, (int) $periodo->numero_semanas);
        $this->assertFalse(CronogramaPrematricula::query()->where('id_periodo', $periodo->id_periodo)->exists());

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.periodos'))->assertOk()->assertSee('2027-I')->assertSee('Planificación');
    }

    public function test_pasar_a_prematricula_abre_la_ventana_ordinaria_automatica(): void
    {
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.periodos.update', 202602), ['estado' => 'PREMATRICULA'])
            ->assertSessionHasNoErrors();

        $ventana = CronogramaPrematricula::query()->where('id_periodo', 202602)->sole();
        $this->assertSame(CronogramaPrematricula::TIPO_ORDINARIA, $ventana->tipo);
        $this->assertSame('Apertura automática al habilitar período en prematrícula', $ventana->motivo);
        $this->assertTrue($ventana->fecha_inicio->lte(now()) && $ventana->fecha_fin->gt(now()));
        $this->assertSame(801, (int) $ventana->creado_por);

        // Volver a guardar en PREMATRICULA no crea otra ventana.
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.periodos.update', 202602), ['estado' => 'PREMATRICULA']);
        $this->assertSame(1, CronogramaPrematricula::query()->where('id_periodo', 202602)->count());
    }

    public function test_un_periodo_referenciado_conserva_codigo_y_fechas_pero_cambia_de_estado(): void
    {
        DB::table('core.periodo_academico')->where('id_periodo', 202602)->update(['estado' => 'PLANIFICACION']);
        SolicitudPrematricula::query()->create(['id_estudiante' => 1001, 'id_periodo' => 202602, 'estado' => 'BORRADOR']);

        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.periodos.edit', 202602))
            ->assertOk()->assertSee('solo puedes cambiar su estado')->assertDontSee('Eliminar 2026-II');
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.periodos.update', 202602), ['codigo' => '2026-X'])
            ->assertSessionHasErrors('periodo');
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.periodos.update', 202602), ['estado' => 'CERRADO'])
            ->assertSessionHasNoErrors();

        $this->assertSame('CERRADO', PeriodoAcademico::query()->findOrFail(202602)->estado);
        $this->actingAs($this->director)->delete(route('pre-matricula.coordinacion.periodos.destroy', 202602))->assertSessionHas('error');
        $this->assertTrue(PeriodoAcademico::query()->whereKey(202602)->exists());
    }

    public function test_se_elimina_un_periodo_sin_referencias(): void
    {
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.periodos.store'), [
            'codigo' => '2028-I', 'fecha_inicio' => '2028-03-01', 'fecha_fin' => '2028-07-15', 'estado' => 'PLANIFICACION',
        ]);
        $periodo = PeriodoAcademico::query()->where('codigo', '2028-I')->sole();

        $this->actingAs($this->director)->delete(route('pre-matricula.coordinacion.periodos.destroy', $periodo->id_periodo))->assertSessionHas('status');
        $this->assertFalse(PeriodoAcademico::query()->whereKey($periodo->id_periodo)->exists());
    }

    public function test_solo_un_periodo_en_planificacion_o_prematricula_admite_ventanas(): void
    {
        DB::table('core.periodo_academico')->where('id_periodo', 202602)->update(['estado' => 'CERRADO']);

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.ventanas.store'), [
            'id_periodo' => 202602, 'tipo' => 'ORDINARIA', 'fecha_inicio' => '2026-12-01T08:00', 'fecha_fin' => '2026-12-05T18:00', 'motivo' => 'Ventana de prueba',
        ])->assertSessionHasErrors('id_periodo');
    }

    public function test_las_fechas_y_el_estado_se_validan(): void
    {
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.periodos.store'), [
            'codigo' => '2027-II', 'fecha_inicio' => '2027-09-01', 'fecha_fin' => '2027-08-01', 'estado' => 'ACTIVO',
        ])->assertSessionHasErrors(['estado' => 'Elige un estado válido: Planificación, Prematrícula, En curso o Cerrado.']);

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.periodos.store'), [
            'codigo' => '2027-II', 'fecha_inicio' => '2027-09-01', 'fecha_fin' => '2027-08-01', 'estado' => 'PLANIFICACION',
        ])->assertSessionHasErrors(['fecha_fin']);
    }

    // ---- Plan de estudios ----

    public function test_la_coordinacion_ve_la_malla_del_plan_predeterminado(): void
    {
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.plan'))
            ->assertOk()->assertSee('PLAN-2026')->assertSee('CP-501')->assertSee('CP-502');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.plan', ['ciclo' => 2]))
            ->assertOk()->assertSee('CP-502')->assertDontSee('Fundamentos de Programación');
    }

    public function test_se_corrige_un_curso_sin_uso_y_sus_prerrequisitos(): void
    {
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.plan.cursos.edit', 'CP-502'))->assertOk()->assertSee('Programación Avanzada');

        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-502'), [
            'nombre' => 'Programación Avanzada I', 'creditos' => 5, 'ciclo' => 3, 'horas_teoria' => 2, 'horas_practica' => 2, 'horas_laboratorio' => 1, 'prerrequisitos' => 'CP-501', 'estado' => 'Activo',
        ])->assertSessionHas('status');

        $this->assertSame('Programación Avanzada I', DB::table('core.curso')->where('codigo', 'CP-502')->value('nombre'));
        $this->assertSame(3, (int) DB::table('core.curso_plan')->where('id_curso_plan', 502)->value('ciclo'));
        $this->assertSame([501], DB::table('core.curso_prerequisito')->where('id_curso_plan', 502)->pluck('id_curso_plan_requisito')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_un_curso_en_uso_solo_cambia_con_confirmacion_pero_su_estado_siempre(): void
    {
        HistorialAcademico::query()->create(['id_estudiante' => 1001, 'id_curso_plan' => 501, 'id_periodo' => 202602, 'nota_final' => 15, 'estado' => HistorialAcademico::APROBADO]);

        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-501'), ['creditos' => 6])
            ->assertSessionHas('error');
        $this->assertSame(3, (int) DB::table('core.curso')->where('codigo', 'CP-501')->value('creditos'));

        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-501'), ['estado' => 'Inactivo'])
            ->assertSessionHas('status');
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-501'), ['creditos' => 6, 'confirmar_correccion' => '1'])
            ->assertSessionHas('status');

        $this->assertSame(6, (int) DB::table('core.curso')->where('codigo', 'CP-501')->value('creditos'));
        $this->assertSame('Inactivo', DB::table('core.curso')->where('codigo', 'CP-501')->value('estado'));
    }

    public function test_no_se_permiten_prerrequisitos_circulares_ni_inexistentes(): void
    {
        DB::table('core.curso_prerequisito')->insert(['id_curso_plan' => 502, 'id_curso_plan_requisito' => 501]);

        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-501'), ['prerrequisitos' => 'CP-502'])
            ->assertSessionHas('error', 'El curso participa en una dependencia circular.');
        $this->actingAs($this->director)->put(route('pre-matricula.coordinacion.plan.cursos.update', 'CP-501'), ['prerrequisitos' => 'NO-EXISTE'])
            ->assertSessionHas('error', 'El prerrequisito NO-EXISTE no existe en la malla ni en el archivo.');
    }

    public function test_la_malla_se_importa_en_dos_pasos_y_se_exporta(): void
    {
        Storage::fake('local');
        $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.plan.plantilla'))->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $conError = $this->malla([['CP-601', 'Base de Datos', 4, 3, 2, 2, 0, 'CP-999']]);
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.plan.validar'), ['archivo' => $conError])
            ->assertSessionHas('reporte_malla', fn (array $r): bool => $r['valido'] === false && $r['total_errores'] === 1);

        $valido = $this->malla([['CP-601', 'Base de Datos', 4, 3, 2, 2, 0, 'CP-502;CP-501']]);
        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.plan.validar'), ['archivo' => $valido]);
        $reporte = session('reporte_malla');
        $this->assertTrue($reporte['valido']);
        $this->assertSame(1, $reporte['resumen']['crear']);
        $this->assertFalse(DB::table('core.curso')->where('codigo', 'CP-601')->exists(), 'Validar no guarda nada.');

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.plan.confirmar'), ['sha256' => $reporte['sha256'], 'huella' => $reporte['huella']])
            ->assertSessionHas('status');

        $cursoPlan = DB::table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->where('c.codigo', 'CP-601')->first(['cp.id_curso_plan', 'cp.id_plan', 'cp.ciclo']);
        $this->assertSame(50, (int) $cursoPlan->id_plan);
        $this->assertSame(3, (int) $cursoPlan->ciclo);
        $this->assertSame(2, DB::table('core.curso_prerequisito')->where('id_curso_plan', $cursoPlan->id_curso_plan)->count());

        $excel = $this->actingAs($this->director)->get(route('pre-matricula.coordinacion.plan.exportar'));
        $excel->assertOk();
        $ruta = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
        file_put_contents($ruta, $excel->getContent());
        $filas = SimpleXLSX::parse($ruta)->rows();
        $this->assertSame(PlanEstudios::COLUMNAS, $filas[0]);
        $this->assertContains('CP-601', array_column($filas, 0));
    }

    public function test_la_importacion_no_cambia_un_curso_en_uso(): void
    {
        Storage::fake('local');
        HistorialAcademico::query()->create(['id_estudiante' => 1001, 'id_curso_plan' => 501, 'id_periodo' => 202602, 'nota_final' => 15, 'estado' => HistorialAcademico::APROBADO]);

        $this->actingAs($this->director)->post(route('pre-matricula.coordinacion.plan.validar'), ['archivo' => $this->malla([['CP-501', 'Otro nombre', 3, 1, 0, 0, 0, '']])])
            ->assertSessionHas('reporte_malla', fn (array $r): bool => $r['valido'] === false);
    }

    /** @param  list<list<mixed>>  $filas */
    private function malla(array $filas): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'malla').'.xlsx';
        SimpleXLSXGen::fromArray([PlanEstudios::COLUMNAS, ...$filas])->saveAs($ruta);

        return new UploadedFile($ruta, 'malla.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function usuario(string $correo, string $rol): User
    {
        $user = User::factory()->create(['email' => $correo, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate($rol, 'web'));

        return $user;
    }
}
