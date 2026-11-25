<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Modules\GestionHorarios\Services\PlanificarSeccionesPorDemanda;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\GestionHorarios\soporte\ProveedorEscuelasFicticio;
use Tests\Feature\GestionHorarios\soporte\ProveedorPermisoGestionFicticio;
use Tests\TestCase;

final class PlanificacionDemandaDemoTest extends TestCase
{
    public function test_creacion_aditiva_y_propuesta_obsoleta(): void
    {
        if (getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS' || getenv('DB_DATABASE') !== 'horarios_demo_2026_ii_demanda') {
            $this->markTestSkipped('Requiere exclusivamente la base de demostración con demanda.');
        }
        $db = DB::connection();
        self::assertTrue(app()->environment('testing'));
        self::assertSame('pgsql', $db->getDriverName());
        self::assertContains($db->getConfig('host'), ['127.0.0.1', 'localhost', '::1']);
        self::assertSame('horarios_demo_2026_ii_demanda', $db->selectOne('SELECT current_database() AS nombre')->nombre);
        $plan = $db->table('core.plan_estudios')->where('codigo', 'QA-HORARIOS')->firstOrFail();
        $periodo = (int) $db->table('core.periodo_academico')->where('codigo', '2026-II')->value('id_periodo');
        $curso = (int) $db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')->where('cp.id_plan', $plan->id_plan)->where('c.codigo', '13034')->value('id_curso_plan');
        $demanda = (new RepositorioDemandaPostgres($db))->vigente($periodo);
        self::assertNotNull($demanda);
        self::assertGreaterThan(0, $curso);
        $usuario = \Mockery::mock(User::class)->makePartial();
        $usuario->forceFill(['id' => 1]);
        $usuario->shouldReceive('can')->with('gestion-horarios.view')->andReturn(true);
        $this->actingAs($usuario);
        $this->app->instance(ProveedorEscuelasAutorizadas::class, new ProveedorEscuelasFicticio([1 => [(int) $plan->id_escuela]]));
        $this->app->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);
        $db->beginTransaction();
        try {
            $filas = $db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso);
            $originales = (clone $filas)->orderBy('id_seccion')->get()->all();
            $plazas = (int) (clone $filas)->where('estado', 'ACTIVA')->sum('capacidad');
            $filaDemanda = $db->table('gestion_horarios.demanda_curso')->where('id_demanda', $demanda->id_demanda)->where('id_curso_plan', $curso);
            self::assertSame(1, $filaDemanda->update(['total' => $plazas + 41, 'manana' => 0, 'tarde' => 0]));
            $servicio = new PlanificarSeccionesPorDemanda($db);
            $previa = $servicio->previsualizar($usuario, $periodo, (int) $plan->id_plan, $curso, 40);
            self::assertCount(2, $previa['codigos']);
            $filaDemanda->update(['total' => $plazas + 42]);
            try {
                $servicio->aplicar($usuario, $periodo, (int) $plan->id_plan, $curso, 40, $previa['huella']);
                self::fail('No debe aplicarse una propuesta obsoleta.');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('propuesta', $e->errors());
            }
            self::assertEquals($originales, (clone $filas)->orderBy('id_seccion')->get()->all());
            $actual = $servicio->previsualizar($usuario, $periodo, (int) $plan->id_plan, $curso, 40);
            self::assertSame(2, $servicio->aplicar($usuario, $periodo, (int) $plan->id_plan, $curso, 40, $actual['huella']));
            self::assertSame(count($originales) + 2, (clone $filas)->count());
            self::assertSame([], $servicio->previsualizar($usuario, $periodo, (int) $plan->id_plan, $curso, 40)['codigos']);
        } finally {
            $db->rollBack();
        }
    }
}
