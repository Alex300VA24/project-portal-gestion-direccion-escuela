<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Derivacion;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class DirectorEstadisticasController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::ESTADISTICAS);

        $periodo = (string) ($request->input('periodo')
            ?? DB::table('core.periodo_academico')->where('estado', 'ACTIVO')->value('codigo')
            ?? '2026-I');

        $periodosDisponibles = DB::table('core.periodo_academico')
            ->orderBy('codigo', 'desc')
            ->pluck('codigo')
            ->toArray();

        if (empty($periodosDisponibles)) {
            $periodosDisponibles = ['2026-I'];
        }

        // 1. Conteos agregados por origen
        $porOrigenRaw = AlertaRiesgo::where('periodo', $periodo)
            ->select('origen', DB::raw('count(*) as total'))
            ->groupBy('origen')
            ->pluck('total', 'origen')
            ->toArray();

        // 2. Conteos agregados por regla
        $porReglaRaw = AlertaRiesgo::where('periodo', $periodo)
            ->select('regla', DB::raw('count(*) as total'))
            ->groupBy('regla')
            ->pluck('total', 'regla')
            ->toArray();

        // 3. Conteos agregados por estado
        $porEstadoRaw = AlertaRiesgo::where('periodo', $periodo)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        // 4. Casos y derivaciones agregados
        $totalCasos = CasoBienestar::count();
        $totalDerivaciones = Derivacion::count();

        // Aplicación estricta de k-anonimato: Celdas menores a 5 se muestran como "menos de 5"
        $formatK = static function (int $count): string {
            if ($count > 0 && $count < 5) {
                return 'menos de 5';
            }

            return (string) $count;
        };

        $porOrigen = array_map($formatK, $porOrigenRaw);
        $porRegla = array_map($formatK, $porReglaRaw);
        $porEstado = array_map($formatK, $porEstadoRaw);

        return view('modules.bienestar.director.index', [
            'periodo' => $periodo,
            'periodosDisponibles' => $periodosDisponibles,
            'porOrigen' => $porOrigen,
            'porRegla' => $porRegla,
            'porEstado' => $porEstado,
            'totalCasos' => $formatK($totalCasos),
            'totalDerivaciones' => $formatK($totalDerivaciones),
        ]);
    }

    /**
     * Cualquier endpoint de mutación invocado por Director devuelve 403 Forbidden.
     */
    public function mutacionBloqueada(): Response
    {
        abort(403, 'Acceso denegado: El rol Director de Escuela solo posee permisos de consulta estadística agregada.');
    }
}
