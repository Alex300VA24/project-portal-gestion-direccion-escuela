<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Services\ReporteEstadisticoService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\FiltrosConsulta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DirectorEstadisticasController extends Controller
{
    public function __construct(
        private readonly ReporteEstadisticoService $reporteService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::ESTADISTICAS);

        $actor = ActorBienestarResolver::resolve($request->user());

        $periodo = FiltrosConsulta::periodo($request);

        $fechaInicio = FiltrosConsulta::fecha($request, 'fecha_inicio');
        $fechaFin = FiltrosConsulta::fecha($request, 'fecha_fin');

        $datos = $this->reporteService->obtenerReporteCompleto($periodo, $fechaInicio, $fechaFin);

        return view('modules.bienestar.director.index', array_merge($datos, [
            'actor' => $actor,
        ]));
    }

    public function exportarCsv(Request $request): StreamedResponse
    {
        Gate::authorize(BienestarPermissions::ESTADISTICAS);

        $periodo = FiltrosConsulta::periodo($request);

        $fechaInicio = FiltrosConsulta::fecha($request, 'fecha_inicio');
        $fechaFin = FiltrosConsulta::fecha($request, 'fecha_fin');

        $datos = $this->reporteService->obtenerReporteCompleto($periodo, $fechaInicio, $fechaFin);
        $csvContent = $this->reporteService->exportarCsv($datos);

        $filename = "reporte-bienestar-unt-{$periodo}-".now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function imprimir(Request $request): View
    {
        Gate::authorize(BienestarPermissions::ESTADISTICAS);

        $actor = ActorBienestarResolver::resolve($request->user());

        $periodo = FiltrosConsulta::periodo($request);

        $fechaInicio = FiltrosConsulta::fecha($request, 'fecha_inicio');
        $fechaFin = FiltrosConsulta::fecha($request, 'fecha_fin');

        $datos = $this->reporteService->obtenerReporteCompleto($periodo, $fechaInicio, $fechaFin);

        return view('modules.bienestar.reportes.imprimir', array_merge($datos, [
            'actor' => $actor,
        ]));
    }

    /**
     * Cualquier endpoint de mutación invocado por Director devuelve 403 Forbidden.
     */
    public function mutacionBloqueada(): Response
    {
        abort(403, 'Acceso denegado: El rol Director de Escuela solo posee permisos de consulta estadística agregada.');
    }
}
