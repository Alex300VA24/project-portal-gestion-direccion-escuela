<?php

namespace App\Modules\Bienestar\Services;

use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\Derivacion;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Shared\Services\ModuleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ReporteEstadisticoService extends ModuleService
{
    /**
     * Retorna todas las métricas y datasets para gráficos y tablas sin censurar valores pequeños.
     * Muestra siempre valores reales incluso si son 1 o 2 casos.
     */
    public function obtenerReporteCompleto(string $periodo, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        // 1. KPIs Generales
        $queryAlertas = AlertaRiesgo::query()->where('periodo', $periodo);
        $queryCasos = CasoBienestar::query();
        $queryCitas = Cita::query();
        $querySolicitudes = SolicitudApoyo::query();
        $queryDerivaciones = Derivacion::query();

        // El periodo acota también casos, citas, derivaciones y solicitudes
        $casoDelPeriodo = function ($q) use ($periodo) {
            $q->where(fn ($c) => $c->whereHas('alertas', fn ($a) => $a->where('periodo', $periodo))
                ->orWhere(fn ($s) => $this->enPeriodo($s->whereDoesntHave('alertas'), 'abierto_en', $periodo)));
        };
        $casoDelPeriodo($queryCasos);
        $queryCitas->whereHas('caso', $casoDelPeriodo);
        $queryDerivaciones->whereHas('caso', $casoDelPeriodo);
        $this->enPeriodo($querySolicitudes, 'solicitada_en', $periodo);

        if ($fechaInicio) {
            $inicio = Carbon::parse($fechaInicio)->startOfDay();
            $queryAlertas->where('fecha_deteccion', '>=', $inicio);
            $queryCasos->where('abierto_en', '>=', $inicio);
            $queryCitas->where('inicio', '>=', $inicio);
            $querySolicitudes->where('solicitada_en', '>=', $inicio);
            $queryDerivaciones->where('derivado_en', '>=', $inicio);
        }

        if ($fechaFin) {
            $fin = Carbon::parse($fechaFin)->endOfDay();
            $queryAlertas->where('fecha_deteccion', '<=', $fin);
            $queryCasos->where('abierto_en', '<=', $fin);
            $queryCitas->where('inicio', '<=', $fin);
            $querySolicitudes->where('solicitada_en', '<=', $fin);
            $queryDerivaciones->where('derivado_en', '<=', $fin);
        }

        $totalAlertas = (clone $queryAlertas)->count();
        $alertasAlta = (clone $queryAlertas)->whereIn('prioridad', ['alta', 'urgente'])->count();
        $totalCasos = (clone $queryCasos)->count();
        $casosAbiertos = (clone $queryCasos)->where('estado', '!=', 'Cerrado')->count();
        $totalCitas = (clone $queryCitas)->count();
        $totalDerivaciones = $queryDerivaciones->count();
        $totalSolicitudes = (clone $querySolicitudes)->count();

        // 2. Gráfico: Casos por Estado (Barras)
        $casosPorEstado = (clone $queryCasos)
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->orderBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        // 3. Gráfico: Alertas por Prioridad y por Regla (Barras)
        $alertasPorPrioridad = (clone $queryAlertas)
            ->select('prioridad', DB::raw('count(*) as total'))
            ->groupBy('prioridad')
            ->pluck('total', 'prioridad')
            ->toArray();

        $alertasPorRegla = (clone $queryAlertas)
            ->select('regla', DB::raw('count(*) as total'))
            ->groupBy('regla')
            ->orderByDesc('total')
            ->pluck('total', 'regla')
            ->toArray();

        // 4. Gráfico: Origen de Alertas (Anillo / Donut)
        $alertasPorOrigen = (clone $queryAlertas)
            ->select('origen', DB::raw('count(*) as total'))
            ->groupBy('origen')
            ->pluck('total', 'origen')
            ->toArray();

        // 5. Gráfico: Solicitudes por Tipo y Estado (Barras)
        $solicitudesPorTipoEstadoRaw = (clone $querySolicitudes)
            ->leftJoin('bienestar.tipo_solicitud as ts', 'ts.id_tipo_solicitud', '=', 'bienestar.solicitud_apoyo.id_tipo_solicitud')
            ->select(
                DB::raw("coalesce(ts.nombre, 'Sin Tipo') as tipo"),
                'bienestar.solicitud_apoyo.estado',
                DB::raw('count(*) as total')
            )
            ->groupBy('tipo', 'bienestar.solicitud_apoyo.estado')
            ->get();

        $solicitudesPorTipo = [];
        foreach ($solicitudesPorTipoEstadoRaw as $row) {
            $solicitudesPorTipo[$row->tipo][$row->estado] = (int) $row->total;
        }

        // 6. Gráfico: Citas por Mes (Barras / Línea)
        $citasPorMesRaw = (clone $queryCitas)
            ->select(
                DB::raw("to_char(inicio, 'YYYY-MM') as mes"),
                DB::raw('count(*) as total')
            )
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        // 7. Gráfico: Tendencia Histórica por Periodo Académico (Línea)
        $tendenciaAlertasPorPeriodo = AlertaRiesgo::query()
            ->select('periodo', DB::raw('count(*) as total'))
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->pluck('total', 'periodo')
            ->toArray();

        $tendenciaCasos = CasoBienestar::query()
            ->join('bienestar.alerta_riesgo as ar', 'ar.id_caso', '=', 'bienestar.caso_bienestar.id_caso')
            ->selectRaw('ar.periodo, count(distinct bienestar.caso_bienestar.id_caso) as total')
            ->groupBy('ar.periodo')
            ->pluck('total', 'periodo')
            ->toArray();

        $periodosDisponibles = DB::table('core.periodo_academico')->pluck('codigo')
            ->merge(array_keys($tendenciaAlertasPorPeriodo))->unique()->sortDesc()->values()->all();

        if (empty($periodosDisponibles)) {
            $periodosDisponibles = ['2026-I', '2025-II', '2025-I'];
        }

        return [
            'periodo' => $periodo,
            'periodosDisponibles' => $periodosDisponibles,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
            'kpis' => [
                'total_alertas' => $totalAlertas,
                'alertas_alta' => $alertasAlta,
                'total_casos' => $totalCasos,
                'casos_abiertos' => $casosAbiertos,
                'total_citas' => $totalCitas,
                'total_derivaciones' => $totalDerivaciones,
                'total_solicitudes' => $totalSolicitudes,
            ],
            'casosPorEstado' => $casosPorEstado,
            'alertasPorPrioridad' => $alertasPorPrioridad,
            'alertasPorRegla' => $alertasPorRegla,
            'alertasPorOrigen' => $alertasPorOrigen,
            'solicitudesPorTipo' => $solicitudesPorTipo,
            'citasPorMes' => $citasPorMesRaw,
            'tendenciaPeriodo' => $tendenciaAlertasPorPeriodo,
            'tendenciaCasos' => $tendenciaCasos,
        ];
    }

    /** Un periodo abarca desde su inicio hasta el inicio del siguiente. */
    private function enPeriodo($query, string $columna, string $periodo)
    {
        $inicio = DB::table('core.periodo_academico')->where('codigo', $periodo)->value('fecha_inicio');
        if (! $inicio) {
            return $query->whereRaw('false');
        }
        $siguiente = DB::table('core.periodo_academico')->where('fecha_inicio', '>', $inicio)->min('fecha_inicio');
        $query->where($columna, '>=', $inicio);

        return $siguiente ? $query->where($columna, '<', $siguiente) : $query;
    }

    /**
     * Genera contenido CSV con todos los valores reales (sin ocultar valores < 5).
     */
    public function exportarCsv(array $data): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['REPORTE ESTADISTICO GENERAL - BIENESTAR UNIVERSITARIO UNT']);
        fputcsv($handle, ['Periodo:', $data['periodo']]);
        fputcsv($handle, ['Generado en:', now()->toDateTimeString()]);
        fputcsv($handle, []);

        // KPIs
        fputcsv($handle, ['INDICADOR KPI', 'TOTAL REAL']);
        foreach ($data['kpis'] as $kpi => $val) {
            fputcsv($handle, [ucwords(str_replace('_', ' ', $kpi)), $val]);
        }
        fputcsv($handle, []);

        // Casos por Estado
        fputcsv($handle, ['CASOS POR ESTADO', 'CANTIDAD']);
        foreach ($data['casosPorEstado'] as $estado => $cant) {
            fputcsv($handle, [$estado, $cant]);
        }
        fputcsv($handle, []);

        // Alertas por Regla
        fputcsv($handle, ['ALERTAS POR REGLA DISPARADORA', 'CANTIDAD']);
        foreach ($data['alertasPorRegla'] as $regla => $cant) {
            fputcsv($handle, [$regla, $cant]);
        }
        fputcsv($handle, []);

        // Alertas por Origen
        fputcsv($handle, ['ORIGEN DE ALERTA', 'CANTIDAD']);
        foreach ($data['alertasPorOrigen'] as $origen => $cant) {
            fputcsv($handle, [$origen, $cant]);
        }
        fputcsv($handle, []);

        // Solicitudes por Tipo y Estado
        fputcsv($handle, ['TIPO DE TRAMITE', 'ESTADO', 'CANTIDAD']);
        foreach ($data['solicitudesPorTipo'] as $tipo => $estados) {
            foreach ($estados as $est => $cant) {
                fputcsv($handle, [$tipo, $est, $cant]);
            }
        }

        foreach ([
            'ALERTAS POR PRIORIDAD' => $data['alertasPorPrioridad'] ?? [],
            'CITAS POR MES' => $data['citasPorMes'] ?? [],
            'TENDENCIA DE ALERTAS POR PERIODO' => $data['tendenciaPeriodo'] ?? [],
            'TENDENCIA DE CASOS POR PERIODO' => $data['tendenciaCasos'] ?? [],
        ] as $titulo => $filas) {
            fputcsv($handle, []);
            fputcsv($handle, [$titulo, 'CANTIDAD']);
            foreach ($filas as $clave => $cant) {
                fputcsv($handle, [$clave, $cant]);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }
}
