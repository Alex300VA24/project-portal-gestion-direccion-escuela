<?php

namespace Tests\Feature\SeguimientoEgresado\Indicadores;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\EmpresasIndicadoresDemoSeeder;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\SeriesDashboard;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/**
 * Contrasta el tablero I1–I6 con el CSV consolidado de Reportes (seguimiento-egresado.reportes.indicadores.csv)
 * y con los filtros por cohorte, campaña y año que ese reporte usa. Solo corre con PostgreSQL.
 */
class ContrasteReportesDiegoTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL: los reportes leen las vistas vw_i*.');
        }
        if (! Route::has('seguimiento-egresado.reportes.indicadores.csv') || ! class_exists(IndicadoresService::class)) {
            $this->markTestSkipped('Requiere el módulo de Reportes integrado en la rama.');
        }

        $this->seed(DatosPruebaSeeder::class);
        $this->seed(EmpresasIndicadoresDemoSeeder::class);
    }

    public function test_el_csv_de_reportes_coincide_con_el_tablero_con_cada_filtro(): void
    {
        $calculadora = app(CalculadoraIndicadores::class);
        $filtros = [[]];
        foreach (DB::table('seguimiento_egresado.cohorte')->pluck('id') as $id) {
            $filtros[] = ['cohorte' => (int) $id];
        }
        foreach (FiltroIndicadores::HORIZONTES as $h) {
            $filtros[] = ['horizonte' => $h];
        }

        foreach ($filtros as $consulta) {
            $filas = $this->filasCsv($consulta);
            $tablero = $calculadora->todos(FiltroIndicadores::desdeConsulta($consulta));
            $etiqueta = json_encode($consulta);

            $this->assertSame(array_keys($tablero), array_keys($filas), $etiqueta);
            foreach ($tablero as $codigo => $r) {
                $this->assertSame($r->estado, $filas[$codigo][10], $codigo.' '.$etiqueta);
                if ($r->numerador !== null) {
                    $this->assertSame($r->numerador, (int) $filas[$codigo][4], $codigo.' '.$etiqueta);
                }
                if ($r->denominador !== null) {
                    $this->assertSame($r->denominador, (int) $filas[$codigo][5], $codigo.' '.$etiqueta);
                }
                if ($r->valor !== null && $codigo !== 'I6') {
                    $this->assertEqualsWithDelta($r->valor, (float) $filas[$codigo][9], 0.001, $codigo.' '.$etiqueta);
                }
            }
        }
    }

    public function test_el_filtro_por_cohorte_da_lo_mismo_en_ambos_modulos(): void
    {
        $servicio = app(IndicadoresService::class);
        $calculadora = app(CalculadoraIndicadores::class);

        foreach (DB::table('seguimiento_egresado.cohorte')->pluck('id') as $id) {
            $reporte = collect($servicio->getIndicadorI1((int) $id)['data'])->first();
            $tablero = $calculadora->i1(new FiltroIndicadores((int) $id));

            $this->assertSame($reporte['numerador'] ?? 0, $tablero->numerador, 'I1 cohorte '.$id);
            $this->assertSame($reporte['denominador'] ?? $tablero->denominador, $tablero->denominador, 'I1 cohorte '.$id);
            if ($tablero->denominador > 0) {
                $this->assertEqualsWithDelta($reporte['resultado'], $tablero->valor, 0.001, 'I1 cohorte '.$id);
            }
        }
    }

    public function test_el_filtro_por_campania_da_lo_mismo_en_ambos_modulos(): void
    {
        $servicio = app(IndicadoresService::class);
        $campanias = app(SeriesDashboard::class)->campanias(new FiltroIndicadores);
        $this->assertNotEmpty($campanias);

        foreach ($campanias as $c) {
            $i2 = collect($servicio->getIndicadorI2($c['id_campania'])['data'])->first();
            $i4 = collect($servicio->getIndicadorI4($c['id_campania'])['data'])->first();

            $this->assertSame($i2['numerador'], $c['trabajan_en_carrera'], 'I2 campaña '.$c['id_campania']);
            $this->assertSame($i2['denominador'], $c['respondieron'], 'I2 campaña '.$c['id_campania']);
            $this->assertSame($i4['numerador'] ?? 0, $c['satisfechos'], 'I4 campaña '.$c['id_campania']);
            $this->assertSame($i4['denominador'] ?? 0, $c['valoraciones'], 'I4 campaña '.$c['id_campania']);
        }
    }

    public function test_el_filtro_por_anio_de_i6_da_lo_mismo_en_ambos_modulos(): void
    {
        $servicio = app(IndicadoresService::class);
        $porAnio = app(CalculadoraIndicadores::class)->i6PorAnio(new FiltroIndicadores);
        $this->assertNotEmpty($porAnio);

        foreach ($porAnio as $anio => $cantidad) {
            $reporte = collect($servicio->getIndicadorI6($anio)['data'])->first();
            $this->assertSame($cantidad, $reporte['resultado'], 'I6 '.$anio);
        }
    }

    public function test_el_filtro_por_horizonte_del_tablero_coincide_con_las_campanias_del_reporte(): void
    {
        $servicio = app(IndicadoresService::class);
        $series = app(SeriesDashboard::class);

        foreach (FiltroIndicadores::HORIZONTES as $h) {
            foreach ($series->campanias(new FiltroIndicadores(null, $h)) as $c) {
                $this->assertSame($h, $c['horizonte'], 'Horizonte '.$h);
                $i2 = collect($servicio->getIndicadorI2($c['id_campania'])['data'])->first();
                $this->assertSame($i2['denominador'], $c['respondieron'], 'I2 a '.$h.' años, campaña '.$c['id_campania']);
            }
        }
    }

    /** @return array<string, list<string>> fila de datos del CSV por código de indicador */
    private function filasCsv(array $consulta): array
    {
        $respuesta = $this->get(route('seguimiento-egresado.reportes.indicadores.csv', $consulta));
        $respuesta->assertOk();

        $contenido = preg_replace('/^ï»¿/', '', $respuesta->streamedContent());
        $filas = [];
        foreach (preg_split('/\R/', trim($contenido)) as $linea) {
            $f = str_getcsv($linea);
            if (preg_match('/^I[1-6]$/', $f[0] ?? '')) {
                $filas[$f[0]] = $f;
            }
        }

        return $filas;
    }
}
