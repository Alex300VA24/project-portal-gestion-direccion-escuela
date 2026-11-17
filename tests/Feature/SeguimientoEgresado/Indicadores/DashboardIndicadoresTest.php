<?php

namespace Tests\Feature\SeguimientoEgresado\Indicadores;

use App\Modules\SeguimientoEgresado\Database\Seeders\DatosPruebaSeeder;
use App\Modules\SeguimientoEgresado\Database\Seeders\EmpresasIndicadoresDemoSeeder;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FichasIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\SeriesDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\FabricaEmpresasIndicadores;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

class DashboardIndicadoresTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;
    use FabricaEmpresasIndicadores;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->secretaria());
        $this->entrarComo();
    }

    private function sembrarDemo(): void
    {
        $this->seed(DatosPruebaSeeder::class);
        $this->seed(EmpresasIndicadoresDemoSeeder::class);
    }

    /** @return array{encabezado: array<string, string>, filas: array<string, array<string, string>>} */
    private function leerCsv(array $consulta = []): array
    {
        $contenido = $this->get(route('seguimiento-egresado.indicadores.exportar', $consulta + ['formato' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);

        $lineas = array_map(fn ($l) => str_getcsv($l, ';', escape: ''), array_values(array_filter(preg_split('/\R/', ltrim($contenido, "\xEF\xBB\xBF")))));
        $inicio = collect($lineas)->search(fn ($f) => $f[0] === 'Código');
        $columnas = $lineas[$inicio];

        return [
            'encabezado' => collect(array_slice($lineas, 0, $inicio))->filter(fn ($f) => count($f) === 2)->mapWithKeys(fn ($f) => [$f[0] => $f[1]])->all(),
            'filas' => collect(array_slice($lineas, $inicio + 1))->mapWithKeys(fn ($f) => [$f[0] => array_combine($columnas, $f)])->all(),
        ];
    }

    public function test_el_tablero_muestra_cifras_calculadas_y_no_de_ejemplo(): void
    {
        $cohorte = $this->cohorte(8);
        $this->egresado($cohorte);
        $this->egresado($cohorte);

        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()
            ->assertSee('25,0')
            ->assertSee('2 de 8')
            ->assertSee('Sin medición')
            ->assertDontSee('DEMO · Valores ilustrativos')
            ->assertDontSee('DEMO');
    }

    public function test_los_filtros_cambian_las_cifras_y_se_mantienen_visibles(): void
    {
        $a = $this->cohorte(10);
        $b = $this->cohorte(10);
        $this->egresado($a);
        $this->egresado($b);
        $this->egresado($b);

        $this->get(route('seguimiento-egresado.indicadores', ['cohorte' => $a]))->assertSee('1 de 10')->assertDontSee('2 de 10');
        $this->get(route('seguimiento-egresado.indicadores', ['cohorte' => $b]))->assertSee('2 de 10')
            ->assertSee('value="'.$b.'" selected', false);
        $this->get(route('seguimiento-egresado.indicadores', ['horizonte' => 5]))->assertSee('Últimos 5 años');
    }

    public function test_filtros_no_validos_se_ignoran(): void
    {
        $this->cohorte(10);

        foreach ([['horizonte' => 7], ['cohorte' => 'abc'], ['corte' => 'no-es-fecha'], ['corte' => '2999-01-01'], ['cohorte' => "1' OR 1=1"]] as $consulta) {
            $this->get(route('seguimiento-egresado.indicadores', $consulta))->assertOk()->assertSee('Histórico hasta '.now('America/Lima')->year);
        }
    }

    public function test_cada_indicador_muestra_periodo_corte_formula_y_fuente(): void
    {
        $this->sembrarDemo();

        $respuesta = $this->get(route('seguimiento-egresado.indicadores', ['horizonte' => 3]))->assertOk();

        $respuesta->assertSee('Últimos 3 años')->assertSee('Corte '.now('America/Lima')->format('d/m/Y'));
        foreach (['I1', 'I2', 'I3', 'I4', 'I5', 'I6'] as $codigo) {
            $ficha = FichasIndicadores::de($codigo);
            $respuesta->assertSee($codigo)->assertSee($ficha['formula'])->assertSee($ficha['fuente']);
        }
    }

    public function test_cada_tarjeta_y_grafico_lleva_periodo_fuente_n_y_cobertura(): void
    {
        $this->sembrarDemo();

        $respuesta = $this->get(route('seguimiento-egresado.indicadores'))->assertOk();
        $corte = 'Corte '.now('America/Lima')->format('d/m/Y');

        foreach (['I1', 'I2', 'I3', 'I4', 'I5', 'I6'] as $codigo) {
            $respuesta->assertSee('Histórico hasta '.now('America/Lima')->year.' · '.$corte)
                ->assertSee('Fuente: '.FichasIndicadores::de($codigo)['fuente']);
        }

        $respuesta->assertSee('N=', false)->assertSee('Cobertura')
            ->assertSee('cohortes')->assertSee('campañas');
    }

    public function test_paneles_de_gestion_y_direccion_usan_datos_reales(): void
    {
        $this->sembrarDemo();
        DB::table('seguimiento_egresado.importacion_padron')->where('id', DB::table('seguimiento_egresado.importacion_padron')->min('id'))->update(['estado' => 'CON_ERRORES']);
        DB::table('seguimiento_egresado.contacto_egresado')->where('id', DB::table('seguimiento_egresado.contacto_egresado')->min('id'))->update(['vigente_hasta' => DB::raw("vigente_desde + interval '1 day'")]);

        $respuesta = $this->get(route('seguimiento-egresado.indicadores'))->assertOk();

        $respuesta->assertSee('Gestión diaria')->assertSee('Importaciones pendientes')->assertSee('En 1 cohorte')
            ->assertSee('Ir a campañas')->assertSee(route('seguimiento-egresado.padron'), false);

        $this->entrarComo('DIRECTOR');
        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()
            ->assertSee('Logro por objetivo educacional')->assertSee('Evidencia de las mejoras')
            ->assertSee('Resolución de Decanato R-0210-2023-D-FACINF')->assertSee(route('seguimiento-egresado.institucional'), false);

        $gestion = app(SeriesDashboard::class)->gestion(new FiltroIndicadores);
        $this->assertSame(1, $gestion['importaciones_pendientes']);
        $this->assertSame(1, $gestion['sin_contacto']);
        $this->assertSame(1, $gestion['cohortes_sin_contacto']);
    }

    public function test_el_dorado_y_los_patrones_de_la_guia_siguen_presentes(): void
    {
        $this->sembrarDemo();

        $this->get(route('seguimiento-egresado.indicadores'))->assertSee('sg-kpi-programa')->assertSee('dorado')->assertSee('patron');
    }

    public function test_la_fecha_de_corte_usa_la_hora_de_peru(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-06 03:00:00', 'UTC'));

        $this->assertSame('2026-11-05', (new FiltroIndicadores)->fechaCorte());
        $this->travelBack();
    }

    public function test_i6_se_presenta_como_cantidad_y_no_como_porcentaje(): void
    {
        $this->mejora();

        $this->get(route('seguimiento-egresado.indicadores'))->assertSee('Cantidad absoluta')->assertSee('No corresponde');
    }

    public function test_los_graficos_tienen_alternativa_en_tabla_y_datos_reales(): void
    {
        $this->sembrarDemo();

        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()
            ->assertSee('data-sg-grafico', false)
            ->assertSee('role="img"', false)
            ->assertSee('Ver datos')
            ->assertSee('data-sg-abrir-modal', false)
            ->assertSee('<dialog class="sg-modal"', false)
            ->assertSee('data-sg-cerrar-modal', false)
            ->assertSee('js/seguimiento-egresado/vendor/chart-4.4.9.umd.js', false)
            ->assertSee('No responder no significa estar sin empleo');
    }

    public function test_sin_datos_los_graficos_lo_dicen_y_no_dibujan(): void
    {
        $this->get(route('seguimiento-egresado.indicadores'))->assertOk()
            ->assertSee('No hay cohortes para este filtro.')
            ->assertDontSee('data-sg-grafico', false);
    }

    /** S3-27: tablero, servicio y exportación coinciden con el mismo filtro. */
    public function test_exportacion_y_tablero_coinciden_para_el_mismo_filtro(): void
    {
        $this->sembrarDemo();
        $cohorte = (int) DB::table('seguimiento_egresado.cohorte')->where('codigo_promocion', 'PROM-2018')->value('id');

        foreach ([[], ['horizonte' => 3], ['horizonte' => 5], ['horizonte' => 10], ['cohorte' => $cohorte], ['cohorte' => $cohorte, 'horizonte' => 3]] as $consulta) {
            $calculados = app(CalculadoraIndicadores::class)->todos(FiltroIndicadores::desdeConsulta($consulta));
            $csv = $this->leerCsv($consulta);
            $pagina = $this->get(route('seguimiento-egresado.indicadores', $consulta))->assertOk();
            $filtro = FiltroIndicadores::desdeConsulta($consulta);

            $this->assertSame($filtro->periodo(), $csv['encabezado']['Periodo']);
            $this->assertSame($filtro->corte->format('d/m/Y'), $csv['encabezado']['Fecha de corte']);

            foreach ($calculados as $codigo => $r) {
                $fila = $csv['filas'][$codigo];
                $resultado = $r->valor === null ? $r->etiquetaEstado() : ($r->unidad === '%' ? number_format($r->valor, 2, '.', '') : (string) (int) $r->valor);

                $this->assertSame($resultado, $fila['Resultado'], $codigo);
                $this->assertSame((string) ($r->numerador ?? ''), $fila['Numerador'], $codigo);
                $this->assertSame($r->unidad === '%' ? (string) ($r->denominador ?? '') : 'No corresponde', $fila['Denominador'], $codigo);
                $this->assertSame($r->etiquetaEstado(), $fila['Estado'], $codigo);

                if ($r->valor !== null && $r->unidad === '%') {
                    $pagina->assertSee(number_format($r->valor, 1, ',', '.').' <small>%</small>', false);
                    $pagina->assertSee($r->numerador.' de '.$r->denominador);
                }
            }
        }
    }

    /** @return string XML de la hoja del Excel descargado */
    private function hojaExcel(array $consulta = []): string
    {
        $respuesta = $this->get(route('seguimiento-egresado.indicadores.exportar', $consulta))->assertOk();
        $this->assertStringContainsString('.xlsx', $respuesta->headers->get('Content-Disposition'));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()));
        foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml'] as $parte) {
            $this->assertNotFalse($zip->getFromName($parte), $parte);
        }
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotFalse(simplexml_load_string($hoja));

        return $hoja;
    }

    public function test_el_excel_trae_anchos_filtros_y_cifras_del_tablero(): void
    {
        $this->sembrarDemo();

        $hoja = $this->hojaExcel(['horizonte' => 3]);
        $i2 = app(CalculadoraIndicadores::class)->i2(new FiltroIndicadores(null, 3));

        $this->assertStringContainsString('customWidth="1"', $hoja);
        $this->assertStringContainsString('state="frozen"', $hoja);
        $this->assertStringContainsString('Últimos 3 años', $hoja);
        $this->assertStringContainsString('Empleabilidad en la carrera', $hoja);
        $this->assertStringContainsString('<v>'.($i2->valor / 100).'</v>', $hoja);
        $this->assertStringContainsString('<v>'.$i2->numerador.'</v>', $hoja);
    }

    public function test_el_excel_no_ejecuta_texto_registrado_como_formula(): void
    {
        $cohorte = $this->cohorte(10);
        DB::table('seguimiento_egresado.cohorte')->where('id', $cohorte)->update(['codigo_promocion' => '=HACK()&<x>']);

        $hoja = $this->hojaExcel(['cohorte' => $cohorte]);

        $this->assertStringContainsString('=HACK()&amp;&lt;x&gt;', $hoja);
        $this->assertStringNotContainsString('<f>', $hoja);
    }

    public function test_el_excel_marca_sin_datos_y_no_inventa_ceros(): void
    {
        $hoja = $this->hojaExcel();

        $this->assertStringContainsString('Sin datos', $hoja);
        $this->assertStringNotContainsString('<v>0</v>', $hoja);
    }

    public function test_el_csv_esta_ordenado_y_no_inventa_ceros(): void
    {
        $this->cohorte(10);

        $csv = $this->leerCsv();

        $this->assertSame(['I1', 'I2', 'I3', 'I4', 'I5', 'I6'], array_keys($csv['filas']));
        $this->assertSame('Todas las cohortes', $csv['encabezado']['Cohorte']);
        $this->assertSame(now('America/Lima')->format('d/m/Y'), $csv['encabezado']['Fecha de corte']);
        $this->assertNotEmpty($csv['encabezado']['Generado']);
        $this->assertSame('Sin datos', $csv['filas']['I2']['Resultado']);
        $this->assertSame('', $csv['filas']['I2']['Cobertura (%)']);
        $this->assertSame('No corresponde', $csv['filas']['I1']['Cobertura (%)']);
    }

    public function test_el_csv_presenta_i6_como_cantidad(): void
    {
        $this->mejora();

        $fila = $this->leerCsv()['filas']['I6'];

        $this->assertSame('1', $fila['Resultado']);
        $this->assertSame('mejoras', $fila['Unidad']);
        $this->assertSame('No corresponde', $fila['Denominador']);
    }

    public function test_el_csv_neutraliza_formulas_de_texto_registrado(): void
    {
        $cohorte = $this->cohorte(10);
        DB::table('seguimiento_egresado.cohorte')->where('id', $cohorte)->update(['codigo_promocion' => '=HACK()']);

        $this->assertSame("'=HACK()", $this->leerCsv(['cohorte' => $cohorte])['encabezado']['Cohorte']);
    }

    public function test_la_semilla_demo_cubre_los_tres_horizontes_y_empresas(): void
    {
        $this->sembrarDemo();
        $calculadora = app(CalculadoraIndicadores::class);

        foreach ([3, 5, 10] as $h) {
            $this->assertNotNull($calculadora->i2(new FiltroIndicadores(null, $h))->valor, 'I2 a '.$h.' años');
        }

        $i5 = $calculadora->i5(new FiltroIndicadores);
        $this->assertSame(6, $i5->numerador);
        $this->assertSame(8, $i5->denominador);
        $this->assertSame(9, $i5->poblacionElegible);
        $this->assertSame(0, DB::table('seguimiento_egresado.respuesta_empresa as r')->join('seguimiento_egresado.invitacion_empresa as i', 'i.id', '=', 'r.id_invitacion_empresa')
            ->where('i.contexto', 'MERCADO')->whereNotNull('r.id_nivel_satisfaccion')->count());
    }
}
