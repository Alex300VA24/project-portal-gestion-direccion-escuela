<?php

namespace Tests\Feature\SeguimientoEgresado\Indicadores;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\EmpresasIndicadoresDemoSeeder;
use App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\SeriesDashboard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * S3-27: contrasta los cálculos del tablero con las vistas oficiales de PostgreSQL.
 * Solo corre con DB_CONNECTION=pgsql, porque las vistas no existen en SQLite.
 */
class ContrasteVistasPostgresTest extends TestCase
{
    use UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL: las vistas vw_i* solo existen allí.');
        }

        $this->seed(DatosPruebaSeeder::class);
        $this->seed(EmpresasIndicadoresDemoSeeder::class);
    }

    public function test_i1_por_cohorte_coincide_con_vw_i1(): void
    {
        $vista = DB::table('seguimiento_egresado.vw_i1_cohorte_actual')->get()->keyBy('id_cohorte');
        $calculado = app(CalculadoraIndicadores::class)->i1PorCohorte(new FiltroIndicadores);

        $this->assertNotEmpty($calculado);
        $this->assertCount($vista->count(), $calculado);

        foreach ($calculado as $fila) {
            $v = $vista[$fila['id_cohorte']];
            $this->assertSame((int) $v->total_egresados, $fila['numerador'], $fila['cohorte']);
            $this->assertSame((int) $v->total_ingresantes, $fila['denominador'], $fila['cohorte']);
            $this->assertEqualsWithDelta((float) $v->porcentaje, $fila['valor'], 0.001, $fila['cohorte']);
        }
    }

    public function test_campanias_coinciden_con_vw_i2_vw_i4_y_cobertura(): void
    {
        $i2 = DB::table('seguimiento_egresado.vw_i2_campania')->get()->keyBy('id_campania');
        $i4 = DB::table('seguimiento_egresado.vw_i4_campania')->get()->keyBy('id_campania');
        $cobertura = DB::table('seguimiento_egresado.vw_cobertura_campania')->get()->keyBy('id_campania');

        $campanias = app(SeriesDashboard::class)->campanias(new FiltroIndicadores);
        $this->assertNotEmpty($campanias);

        foreach ($campanias as $c) {
            $id = $c['id_campania'];
            $this->assertSame((int) $i2[$id]->encuestados, $c['respondieron']);
            $this->assertSame((int) $i2[$id]->trabajan_en_carrera, $c['trabajan_en_carrera']);
            $this->assertEqualsWithDelta((float) $i2[$id]->i2_pct, $c['i2'], 0.001);
            // vw_i4_campania omite las campañas sin respuestas (JOIN con medicion_laboral).
            if (! isset($i4[$id])) {
                $this->assertSame(0, $c['respondieron']);
                $this->assertSame(0, $c['valoraciones']);
                $this->assertNull($c['i4']);
            } else {
                $this->assertSame((int) $i4[$id]->valoraciones, $c['valoraciones']);
                $this->assertSame((int) $i4[$id]->satisfechos, $c['satisfechos']);
                $this->assertEqualsWithDelta((float) $i4[$id]->i4_pct, $c['i4'], 0.001);
            }
            $this->assertSame((int) $cobertura[$id]->invitados, $c['invitados']);
            $this->assertSame((int) $cobertura[$id]->sin_respuesta, $c['pendientes'] + $c['vencidas']);
            $this->assertEqualsWithDelta((float) $cobertura[$id]->cobertura_pct, $c['cobertura'], 0.001);
        }
    }

    public function test_el_filtro_de_horizonte_coincide_con_su_vista_y_con_las_filas(): void
    {
        $calculadora = app(CalculadoraIndicadores::class);

        foreach (FiltroIndicadores::HORIZONTES as $h) {
            $filtro = new FiltroIndicadores(null, $h);
            $filas = DB::table('seguimiento_egresado.vw_i2_i4_cohorte_horizonte')->where('horizonte_anios', $h)->get()
                ->groupBy(fn ($f) => (string) $f->anio_egreso)
                ->flatMap(fn ($g) => $g->where('anio_campania', $g->max('anio_campania')));

            $i2 = $calculadora->i2($filtro);
            $this->assertSame((int) $filas->sum('trabajan_en_carrera'), $i2->numerador, 'I2 a '.$h);
            $this->assertSame((int) $filas->sum('respondieron'), $i2->denominador, 'I2 a '.$h);
            $this->assertSame((int) $filas->sum('invitados'), $i2->poblacionElegible, 'I2 a '.$h);

            // El camino por filas (el que se usa con cohorte o corte pasado) debe dar lo mismo que la vista.
            $porFilas = $calculadora->ultimaRespuestaPorEgresado($calculadora->poblacionEncuesta($filtro));
            $this->assertSame($i2->denominador, $porFilas->count(), 'Filas a '.$h);
            $this->assertSame($i2->numerador, $porFilas->filter(fn ($f) => $f->situacion === 'TRABAJA' && (bool) $f->trabajo_relacionado)->count(), 'Filas a '.$h);
        }
    }

    public function test_sin_horizonte_la_ultima_medicion_coincide_con_su_vista(): void
    {
        $calculadora = app(CalculadoraIndicadores::class);
        $filtro = new FiltroIndicadores;

        $vista = DB::table('seguimiento_egresado.vw_ultima_medicion_actual')->pluck('id_medicion', 'id_egresado')->map(fn ($v) => (int) $v)->sortKeys()->all();
        $filas = $calculadora->ultimaRespuestaPorEgresado($calculadora->filasEncuesta($filtro))
            ->pluck('id_medicion', 'id_egresado')->map(fn ($v) => (int) $v)->sortKeys()->all();

        $this->assertNotEmpty($vista);
        $this->assertSame($vista, $filas);
        $this->assertSame(count($vista), $calculadora->i2($filtro)->denominador);
    }

    public function test_i5_coincide_con_vw_i5(): void
    {
        $vista = DB::table('seguimiento_egresado.vw_i5_campania')->get();
        $i5 = app(CalculadoraIndicadores::class)->i5(new FiltroIndicadores);

        $this->assertGreaterThan(0, $i5->denominador);
        $this->assertSame((int) $vista->sum('satisfechos'), $i5->numerador);
        $this->assertSame((int) $vista->sum('valoraciones'), $i5->denominador);
    }

    public function test_i3_coincide_con_vw_i3_en_logrados_y_usa_el_total_oficial(): void
    {
        $vista = DB::table('seguimiento_egresado.vw_i3_oe')->get();
        $i3 = app(CalculadoraIndicadores::class)->i3(new FiltroIndicadores);

        $this->assertSame((int) $vista->sum('oe_logrados'), $i3->numerador);
        $this->assertSame((int) $vista->sum('oe_evaluados'), $i3->n);
        $this->assertSame($vista->count() * 4, $i3->denominador);
    }

    public function test_i6_coincide_con_vw_i6_cuando_todas_tienen_evidencia(): void
    {
        $vista = DB::table('seguimiento_egresado.vw_i6_anual')->pluck('mejoras_implementadas', 'anio')->map(fn ($v) => (int) $v)->sortKeys()->all();
        $calculado = app(CalculadoraIndicadores::class)->i6PorAnio(new FiltroIndicadores);
        ksort($calculado);

        $this->assertSame($vista, $calculado);
    }

    public function test_el_trigger_de_postgresql_respalda_la_regla_de_mercado(): void
    {
        $invitacion = DB::table('seguimiento_egresado.invitacion_empresa')->where('contexto', 'MERCADO')->where('estado', 'PENDIENTE')->value('id');

        $this->expectException(QueryException::class);
        DB::table('seguimiento_egresado.respuesta_empresa')->insert([
            'id_invitacion_empresa' => $invitacion,
            'id_nivel_satisfaccion' => DB::table('seguimiento_egresado.cat_nivel_satisfaccion')->value('id'),
        ]);
    }

    public function test_guardar_una_respuesta_real_funciona_con_bloqueo_en_postgresql(): void
    {
        $campania = DB::table('seguimiento_egresado.campania_seguimiento')->where('tipo', 'EMPRESAS')->where('estado', 'ABIERTA')->value('id');
        $enlace = app(EnlaceEmpresa::class)->generar(['id_campania' => $campania, 'contexto' => 'MERCADO']);

        $this->post(route('seguimiento-egresado.encuesta-empresa.responder', ['token' => $enlace['token']]), [
            'organizacion' => 'Organización Ficticia', 'sector' => 'SOFTWARE_TI', 'demandas' => ['CLOUD'], 'brechas' => ['INGLES'],
        ])->assertRedirect(route('seguimiento-egresado.encuesta-empresa.gracias'));

        $this->assertDatabaseHas('seguimiento_egresado.invitacion_empresa', ['id' => $enlace['id'], 'estado' => 'COMPLETADA']);
    }
}
