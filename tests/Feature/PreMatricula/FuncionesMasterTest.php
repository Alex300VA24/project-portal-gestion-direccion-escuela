<?php

namespace Tests\Feature\PreMatricula;

use App\Models\User;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
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

    private function usuario(string $correo, string $rol): User
    {
        $user = User::factory()->create(['email' => $correo, 'email_verified_at' => now()]);
        $user->assignRole(Role::findOrCreate($rol, 'web'));

        return $user;
    }
}
