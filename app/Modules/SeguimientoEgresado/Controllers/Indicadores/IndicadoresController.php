<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Indicadores;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ExcelIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador;
use App\Modules\SeguimientoEgresado\Services\Indicadores\SeriesDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class IndicadoresController extends Controller
{
    public function index(Request $request, CalculadoraIndicadores $calculadora, SeriesDashboard $series): View
    {
        $filtro = FiltroIndicadores::desdeConsulta($request->query());
        $indicadores = $calculadora->todos($filtro);

        $datos = [
            'filtro' => $filtro,
            'indicadores' => $indicadores,
            'error' => false,
            'verGestion' => $this->puede($request, 'campanias.administrar'),
            'verDireccion' => $this->puede($request, 'institucional.validar'),
        ];

        try {
            $datos += [
                'cohortes' => DB::table('seguimiento_egresado.cohorte')->orderByDesc('anio_ingreso')->get(['id', 'codigo_promocion', 'anio_ingreso']),
                'resumen' => $series->resumen($filtro),
                'i1PorCohorte' => $calculadora->i1PorCohorte($filtro),
                'campanias' => $series->campanias($filtro),
                'tendencia' => $series->tendencia($filtro),
                'horizontes' => $series->horizontes($filtro),
                'laboral' => $series->laboral($filtro),
                'i6PorAnio' => $calculadora->i6PorAnio($filtro),
                'gestion' => $series->gestion($filtro),
                'direccion' => $series->direccion($filtro),
            ];
        } catch (Throwable $e) {
            report($e);
            $datos = [
                'error' => true, 'cohortes' => collect(), 'resumen' => null, 'i1PorCohorte' => [], 'campanias' => [],
                'tendencia' => [], 'horizontes' => [], 'laboral' => null, 'i6PorAnio' => [], 'gestion' => null, 'direccion' => null,
            ] + $datos;
        }

        return view('modules.seguimiento-egresado.paginas.indicadores', $datos);
    }

    /** Sin el módulo de accesos integrado se muestran ambos paneles; con él, cada perfil ve el suyo. */
    private function puede(Request $request, string $capacidad): bool
    {
        if (! class_exists(SesionAcceso::class)) {
            return true;
        }

        return (bool) SesionAcceso::desde($request)->actual()?->puede($capacidad);
    }

    /** Excel con el mismo filtro y el mismo cálculo que el tablero. Si el servidor no tiene ZipArchive, entrega CSV. */
    public function exportar(Request $request, CalculadoraIndicadores $calculadora, ExcelIndicadores $excel): Response
    {
        $filtro = FiltroIndicadores::desdeConsulta($request->query());
        $indicadores = $calculadora->todos($filtro);
        $generado = now(FiltroIndicadores::ZONA)->format('d/m/Y H:i');

        if ($request->query('formato') === 'csv' || ! ExcelIndicadores::disponible()) {
            return $this->comoCsv($filtro, $indicadores, $generado);
        }

        return response()->download(
            $excel->generar($filtro, $indicadores, $generado),
            'indicadores_I1-I6_'.$filtro->fechaCorte().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    /**
     * CSV para intercambio de datos: punto y coma y punto decimal, como Excel en Perú (es-PE).
     *
     * @param  array<string, ResultadoIndicador>  $indicadores
     */
    private function comoCsv(FiltroIndicadores $filtro, array $indicadores, string $generado): StreamedResponse
    {
        return response()->streamDownload(function () use ($filtro, $indicadores, $generado) {
            $salida = fopen('php://output', 'w');
            $fila = fn (array $celdas) => fputcsv($salida, array_map(self::celdaSegura(...), $celdas), ';', escape: '');

            fwrite($salida, "\xEF\xBB\xBF");
            $fila(['Seguimiento al Egresado - Ingeniería Informática - Universidad Nacional de Trujillo']);
            $fila(['Reporte de indicadores I1 a I6']);
            $fila([]);
            $fila(['Promoción', $indicadores['I1']->cohorte]);
            $fila(['Periodo', $filtro->periodo()]);
            $fila(['Fecha de corte', $filtro->corte->format('d/m/Y')]);
            $fila(['Generado', $generado]);
            $fila([]);
            $fila(['Código', 'Indicador', 'Resultado', 'Unidad', 'Numerador', 'Denominador', 'Población', 'Muestra (N)', 'Cobertura (%)', 'Estado', 'Fuente', 'Fórmula']);

            foreach ($indicadores as $r) {
                $esConteo = $r->unidad !== '%';
                $fila([
                    $r->codigo,
                    $r->nombre,
                    $r->valor === null ? $r->etiquetaEstado() : ($esConteo ? (int) $r->valor : number_format($r->valor, 2, '.', '')),
                    $r->unidad,
                    $r->numerador,
                    $esConteo ? 'No corresponde' : $r->denominador,
                    $esConteo ? 'No corresponde' : $r->poblacionElegible,
                    $r->n,
                    $r->cobertura === null ? ($esConteo || $r->codigo === 'I1' ? 'No corresponde' : '') : number_format($r->cobertura, 2, '.', ''),
                    $r->etiquetaEstado(),
                    $r->fuente,
                    $r->formula,
                ]);
            }

            fclose($salida);
        }, 'indicadores_I1-I6_'.$filtro->fechaCorte().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita que Excel interprete como fórmula un texto registrado por una persona. */
    private static function celdaSegura(mixed $valor): mixed
    {
        return is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
