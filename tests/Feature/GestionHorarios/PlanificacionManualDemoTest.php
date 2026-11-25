<?php

namespace Tests\Feature\GestionHorarios;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Contracts\ProveedorPermisoGestionAcademica;
use App\Modules\GestionHorarios\Livewire\DistribucionSecciones;
use App\Modules\GestionHorarios\Livewire\OfertaAcademica;
use App\Modules\GestionHorarios\Services\PlanificarSeccion;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\GestionHorarios\soporte\ProveedorEscuelasFicticio;
use Tests\Feature\GestionHorarios\soporte\ProveedorPermisoGestionFicticio;
use Tests\TestCase;

final class PlanificacionManualDemoTest extends TestCase
{
    public function test_planificar_con_el_esquema_versionado_sin_demanda_ni_asociacion(): void
    {
        if (getenv('HORARIOS_DEMO_PLAN') !== 'QA-HORARIOS') {
            $this->markTestSkipped('Requiere el catálogo aislado QA-HORARIOS y su seeder.');
        }
        $db = DB::connection();
        self::assertTrue(app()->environment('testing'));
        self::assertSame('pgsql', $db->getDriverName());
        self::assertSame('127.0.0.1', $db->getConfig('host'));
        self::assertSame('horarios_demo_2026_ii', $db->selectOne('select current_database() as nombre')->nombre);
        self::assertFalse($db->getSchemaBuilder()->hasTable('gestion_horarios.demanda_curso'));
        self::assertFalse($db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion'));
        $plan = $db->table('core.plan_estudios')->where('codigo', 'QA-HORARIOS')->firstOrFail();
        $periodo = $db->table('core.periodo_academico')->where('codigo', '2026-II')->value('id_periodo');
        $curso = $db->table('core.curso_plan as cp')->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('cp.id_plan', $plan->id_plan)->where('c.codigo', '13034')->value('id_curso_plan');
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1]);
        $user->shouldReceive('can')->with('gestion-horarios.view')->andReturn(true);
        $this->actingAs($user);
        $escuelas = new ProveedorEscuelasFicticio([1 => [(int) $plan->id_escuela]]);
        $this->app->instance(ProveedorEscuelasAutorizadas::class, $escuelas);
        $this->app->instance(ProveedorPermisoGestionAcademica::class, new ProveedorPermisoGestionFicticio);

        $db->beginTransaction();
        try {
            Livewire::withQueryParams(['plan' => $plan->id_plan, 'periodo' => $periodo])
                ->test(OfertaAcademica::class)->assertSee('13034')->set('buscar', 'no-existe')
                ->assertSee('No se encontraron cursos');
            $component = Livewire::withQueryParams(['plan' => $plan->id_plan, 'periodo' => $periodo])
                ->test(DistribucionSecciones::class)->assertSee('13034')
                ->set('ciclo', '2')->set('seccionSemana', 'A')->assertSee('Actividades registradas · Ciclo 2 · Sección A')
                ->assertSee('Cuadrícula semanal de actividades')->set('buscar', '13034')->call('abrirSeccion', $curso)
                ->set('codigo', 'A')->set('capacidad', '0')->call('guardarSeccion')->assertHasErrors('capacidad')
                ->set('capacidad', '35')->call('guardarSeccion')->assertHasErrors('codigo')
                ->set('codigo', 'c')->call('guardarSeccion')->assertHasNoErrors()->assertSee('Sección C guardada.');
            $filas = $db->table('core.seccion')->where('id_periodo', $periodo)->where('id_curso_plan', $curso);
            $creada = (clone $filas)->where('codigo', 'C')->firstOrFail();
            $component->call('abrirSeccion', $curso, $creada->id_seccion)
                ->set('codigo', 'D')->set('capacidad', '40')->call('guardarSeccion')->assertHasNoErrors();
            self::assertSame(40, (int) (clone $filas)->where('codigo', 'D')->value('capacidad'));

            $component->call('abrirSeccion', $curso, $creada->id_seccion);
            (clone $filas)->where('id_seccion', $creada->id_seccion)->update(['capacidad' => 32]);
            $component->set('capacidad', '31')->call('guardarSeccion')->assertHasErrors('seccion')
                ->call('cancelarEdicion')->assertSet('cursoEnEdicion', null);

            $programada = (clone $filas)->where('codigo', 'A')->firstOrFail();
            $component->call('abrirSeccion', $curso, $programada->id_seccion)
                ->set('capacidad', '42')->call('guardarSeccion')->assertHasNoErrors();
            self::assertTrue($db->table('gestion_horarios.actividad_academica')->where('id_seccion', $programada->id_seccion)->exists());
            $escuelas->revocar(1);
            try {
                (new PlanificarSeccion($db))->guardar($user, $periodo, $plan->id_plan, $curso, 'E', 30);
                self::fail('La adscripción revocada debe impedir la escritura.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                self::assertSame(403, $e->getStatusCode());
            }
            self::assertFalse((clone $filas)->where('codigo', 'E')->exists());
        } finally {
            $db->rollBack();
        }
    }
}
