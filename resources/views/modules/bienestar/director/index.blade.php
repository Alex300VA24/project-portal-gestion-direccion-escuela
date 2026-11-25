@extends('modules.bienestar.layout')

@section('title', 'Estadísticas e Indicadores · Bienestar UNT')
@section('page-label', 'Estadísticas y Reportes')

@section('content')
<div class="space-y-6">
    <!-- Encabezado con Botones de Exportación e Impresión -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-[var(--unt-borde)]">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--unt-azul-tinta); font-family: 'Roboto Slab', serif;">Estadísticas y Reportes Institucionales</h1>
            <p class="text-xs mt-1" style="color: var(--unt-texto-suave);">
                Monitoreo analítico agregado del bienestar estudiantil. Conforme a la Ley N° 29733, no se exponen identidades ni diagnósticos confidenciales. Se muestran todos los valores reales.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('bienestar.director.exportar-csv', request()->all()) }}" class="btn-unt-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Descargar CSV</span>
            </a>
            <a href="{{ route('bienestar.director.imprimir', request()->all()) }}" target="_blank" class="btn-unt-outline">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Vista Imprimible / PDF</span>
            </a>
        </div>
    </div>

    <!-- Filtros de periodo y rango de fechas -->
    <div class="card-unt p-4">
        <form method="GET" action="{{ route('bienestar.director.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Periodo Académico</label>
                <select name="periodo" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs" onchange="this.form.submit()">
                    @foreach ($periodosDisponibles as $p)
                        <option value="{{ $p }}" @selected($p === $periodo)>Periodo {{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Fecha Desde</label>
                <input type="date" name="fecha_inicio" value="{{ $fechaInicio }}" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
            </div>
            <div>
                <label class="block text-xs font-bold mb-1" style="color: var(--unt-texto);">Fecha Hasta</label>
                <input type="date" name="fecha_fin" value="{{ $fechaFin }}" class="w-full text-xs rounded-lg border-[var(--unt-borde)] focus:border-[var(--unt-dorado)] focus:ring-[var(--unt-dorado)] shadow-xs">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="w-full btn-unt-primary">
                    Filtrar Datos
                </button>
                @if ($fechaInicio || $fechaFin)
                    <a href="{{ route('bienestar.director.index', ['periodo' => $periodo]) }}" class="btn-unt-outline">
                        Limpiar
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Tarjetas de Indicadores Clave (KPIs) con valores reales sin ocultar menores a 5 -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Total Alertas</span>
            <div class="text-2xl font-extrabold mt-1" style="color: var(--unt-azul-tinta);">{{ $kpis['total_alertas'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">Periodo {{ $periodo }}</span>
        </div>
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider text-red-700">Alta Urgencia</span>
            <div class="text-2xl font-extrabold text-red-700 mt-1">{{ $kpis['alertas_alta'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">Riesgo inminente</span>
        </div>
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Total Casos</span>
            <div class="text-2xl font-extrabold mt-1" style="color: var(--unt-azul-tinta);">{{ $kpis['total_casos'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">Casos asistenciales</span>
        </div>
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider text-unt-dorado-accessible">Casos Abiertos</span>
            <div class="text-2xl font-extrabold mt-1 text-unt-dorado-accessible">{{ $kpis['casos_abiertos'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">En seguimiento</span>
        </div>
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Citas Totales</span>
            <div class="text-2xl font-extrabold mt-1" style="color: var(--unt-azul-tinta);">{{ $kpis['total_citas'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">Atenciones</span>
        </div>
        <div class="card-unt p-4">
            <span class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--unt-texto-suave);">Solicitudes</span>
            <div class="text-2xl font-extrabold mt-1" style="color: var(--unt-azul-tinta);">{{ $kpis['total_solicitudes'] }}</div>
            <span class="text-[10px]" style="color: var(--unt-texto-suave);">Trámites UNT</span>
        </div>
    </div>

    <!-- Fila 1 de Gráficos: Casos por Estado & Origen de Alertas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Gráfico 1: Casos por Estado -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Distribución de Casos por Estado</h2>
                    <p class="text-xs text-slate-500">Estatus del expediente social</p>
                </div>
            </div>
            <div class="h-64">
                <canvas id="chartCasosEstado"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Casos por Estado">
                    <caption class="sr-only">Casos por Estado</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left"><th class="pb-1">Estado</th><th class="pb-1 text-right">Casos Reales</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($casosPorEstado as $est => $cant)
                            <tr><td class="py-1">{{ $est }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-unt-texto-suave text-center">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráfico 2: Origen de Alertas (Anillo / Donut) -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Origen de las Alertas Detectadas</h2>
                <p class="text-xs text-slate-500">Detección automática vs. Reporte de docentes</p>
            </div>
            <div class="h-64 flex items-center justify-center">
                <canvas id="chartOrigenAlertas"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Origen de Alertas">
                    <caption class="sr-only">Origen de Alertas</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left"><th class="pb-1">Origen</th><th class="pb-1 text-right">Alertas Reales</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($alertasPorOrigen as $orig => $cant)
                            <tr><td class="py-1">{{ ucwords(str_replace('_', ' ', $orig)) }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-unt-texto-suave text-center">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Fila 2 de Gráficos: Alertas por Regla & Citas por Mes -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Gráfico 3: Alertas por Regla o Motivo -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Alertas por Regla Disparadora</h2>
                <p class="text-xs text-slate-500">Causas académicas y de permanencia universitaria</p>
            </div>
            <div class="h-64">
                <canvas id="chartAlertasRegla"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Alertas por Regla">
                    <caption class="sr-only">Alertas por Regla</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left"><th class="pb-1">Regla Disparadora</th><th class="pb-1 text-right">Cantidad</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($alertasPorRegla as $reg => $cant)
                            <tr><td class="py-1">{{ ucwords(str_replace('_', ' ', $reg)) }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-unt-texto-suave text-center">Sin registros</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráfico 4: Citas por Mes -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Atenciones y Citas por Mes</h2>
                <p class="text-xs text-slate-500">Volumen mensual de atención a estudiantes</p>
            </div>
            <div class="h-64">
                <canvas id="chartCitasMes"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Citas por Mes">
                    <caption class="sr-only">Citas por Mes</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left"><th class="pb-1">Mes</th><th class="pb-1 text-right">Citas</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($citasPorMes as $mes => $cant)
                            <tr><td class="py-1 font-mono">{{ $mes }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-unt-texto-suave text-center">Sin citas registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
        <div><h2 class="text-sm font-bold text-slate-900">Alertas por Prioridad</h2><p class="text-xs text-slate-500">Nivel de urgencia de las alertas del periodo</p></div>
        <div class="h-48"><canvas id="chartPrioridad"></canvas></div>
        <div class="border-t border-slate-100 pt-3"><table class="w-full text-xs text-slate-700"><caption class="sr-only">Alertas por prioridad</caption>
            <thead><tr class="text-unt-texto-suave text-left"><th class="pb-1">Prioridad</th><th class="pb-1 text-right">Cantidad</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse ($alertasPorPrioridad as $p => $cant)<tr><td class="py-1">{{ ucfirst($p) }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td></tr>@empty<tr><td colspan="2" class="py-1 text-center">Sin registros</td></tr>@endforelse</tbody>
        </table></div>
    </div>

    <!-- Fila 3: Tendencia por Periodo & Solicitudes por Tipo -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Gráfico 5: Tendencia por Periodo Académico (Línea) -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Tendencia de Alertas y Casos</h2>
                <p class="text-xs text-slate-500">Evolución de estudiantes en riesgo por ciclo académico</p>
            </div>
            <div class="h-64">
                <canvas id="chartTendencia"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Tendencia por Periodo">
                    <caption class="sr-only">Tendencia por Periodo</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left"><th class="pb-1">Periodo</th><th class="pb-1 text-right">Alertas</th><th class="pb-1 text-right">Casos</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($tendenciaPeriodo as $per => $cant)
                            <tr><td class="py-1 font-mono">{{ $per }}</td><td class="py-1 text-right font-mono font-bold">{{ $cant }}</td><td class="py-1 text-right font-mono font-bold">{{ $tendenciaCasos[$per] ?? 0 }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="py-1 text-unt-texto-suave text-center">Sin datos históricos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gráfico 6: Solicitudes por Tipo y Estado -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-xs space-y-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Solicitudes por Procedimiento UNT</h2>
                <p class="text-xs text-slate-500">Demanda de trámites según estado actual</p>
            </div>
            <div class="h-64">
                <canvas id="chartSolicitudesTipo"></canvas>
            </div>
            <!-- Tabla accesible WCAG AA -->
            <div class="border-t border-slate-100 pt-3 overflow-x-auto">
                <table class="w-full text-xs text-slate-700" aria-label="Tabla de datos: Solicitudes por Tipo">
                    <caption class="sr-only">Solicitudes por Tipo</caption>
                    <thead>
                        <tr class="text-unt-texto-suave font-semibold text-left">
                            <th class="pb-1">Trámite</th>
                            <th class="pb-1 text-center">Enviada</th>
                            <th class="pb-1 text-center">Recibida</th>
                            <th class="pb-1 text-center">Observada</th>
                            <th class="pb-1 text-center">Remitida</th>
                            <th class="pb-1 text-center">Aprobada</th>
                            <th class="pb-1 text-center">Rechazada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($solicitudesPorTipo as $tipo => $estados)
                            <tr>
                                <td class="py-1 font-medium">{{ $tipo }}</td>
                                <td class="py-1 text-center font-mono">{{ $estados['enviada'] ?? 0 }}</td>
                                <td class="py-1 text-center font-mono">{{ $estados['recibida'] ?? 0 }}</td>
                                <td class="py-1 text-center font-mono">{{ $estados['observada'] ?? 0 }}</td>
                                <td class="py-1 text-center font-mono">{{ $estados['remitida'] ?? 0 }}</td>
                                <td class="py-1 text-center font-mono font-bold text-unt-azul">{{ $estados['aprobada'] ?? 0 }}</td>
                                <td class="py-1 text-center font-mono">{{ $estados['rechazada'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-1 text-unt-texto-suave text-center">Sin solicitudes</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Inicialización de Chart.js con paleta institucional UNT -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const oscuro = () => document.documentElement.dataset.theme === 'dark';
    const paleta = {
        azul: '#12377B',         // Azul Primario UNT
        azulOscuro: '#092C6C',   // Azul Oscuro UNT
        azulNoche: '#0B1F44',    // Azul Noche UNT
        dorado: '#E6AD09',       // Dorado Acento UNT
        doradoOscuro: '#BD8E07', // Dorado Hover UNT
        doradoTexto: '#8A6700',  // Dorado Accesible WCAG UNT
        rojo: '#B91C1C',         // Alta Urgencia
        exito: '#BD8E07',        // Aprobadas / Éxito
        gris: '#5F6670'          // Metadatos / Neutro
    };

    // 1. Casos por Estado
    const casosData = @json($casosPorEstado);
    new Chart(document.getElementById('chartCasosEstado'), {
        type: 'bar',
        data: {
            labels: Object.keys(casosData),
            datasets: [{
                label: 'Casos Reales',
                data: Object.values(casosData),
                backgroundColor: [paleta.azul, paleta.dorado, paleta.exito, paleta.rojo, paleta.azulOscuro],
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 2. Origen de Alertas (Donut)
    const origenData = @json($alertasPorOrigen);
    new Chart(document.getElementById('chartOrigenAlertas'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(origenData).map(k => k.replace('_', ' ').toUpperCase()),
            datasets: [{
                data: Object.values(origenData),
                backgroundColor: [paleta.azul, paleta.dorado, paleta.gris]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 3. Alertas por Regla
    const reglaData = @json($alertasPorRegla);
    new Chart(document.getElementById('chartAlertasRegla'), {
        type: 'bar',
        data: {
            labels: Object.keys(reglaData).map(k => k.replace(/_/g, ' ')),
            datasets: [{
                label: 'Cantidad de Alertas',
                data: Object.values(reglaData),
                backgroundColor: paleta.azul,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 4. Citas por Mes
    const citasData = @json($citasPorMes);
    new Chart(document.getElementById('chartCitasMes'), {
        type: 'bar',
        data: {
            labels: Object.keys(citasData),
            datasets: [{
                label: 'Atenciones / Citas',
                data: Object.values(citasData),
                backgroundColor: paleta.dorado,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // 5. Tendencia Histórica por Periodo
    const casosTendencia = @json((object) $tendenciaCasos);
    const tendenciaData = @json($tendenciaPeriodo);
    new Chart(document.getElementById('chartTendencia'), {
        type: 'line',
        data: {
            labels: Object.keys(tendenciaData),
            datasets: [{
                label: 'Total de Alertas de Riesgo',
                data: Object.values(tendenciaData),
                borderColor: paleta.azul,
                backgroundColor: 'rgba(18, 55, 123, 0.1)',
                fill: true,
                tension: 0.2
            }, {
                label: 'Casos abiertos desde alertas',
                data: Object.keys(tendenciaData).map((p) => casosTendencia[p] ?? 0),
                borderColor: paleta.dorado,
                backgroundColor: 'transparent',
                tension: 0.2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    const prioridadData = @json((object) $alertasPorPrioridad);
    new Chart(document.getElementById('chartPrioridad'), {
        type: 'bar',
        data: { labels: Object.keys(prioridadData), datasets: [{ label: 'Alertas', data: Object.values(prioridadData), backgroundColor: paleta.azul, borderRadius: 4 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    // 6. Solicitudes por Tipo
    const solData = @json($solicitudesPorTipo);
    const tiposLabels = Object.keys(solData);
    new Chart(document.getElementById('chartSolicitudesTipo'), {
        type: 'bar',
        data: {
            labels: tiposLabels,
            datasets: [
                {
                    label: 'Enviadas',
                    data: tiposLabels.map(t => solData[t]?.enviada || 0),
                    backgroundColor: paleta.dorado
                },
                {
                    label: 'Remitidas',
                    data: tiposLabels.map(t => solData[t]?.remitida || 0),
                    backgroundColor: paleta.azul
                },
                {
                    label: 'Aprobadas',
                    data: tiposLabels.map(t => solData[t]?.aprobada || 0),
                    backgroundColor: paleta.exito
                },
                {
                    label: 'Recibidas',
                    data: tiposLabels.map(t => solData[t]?.recibida || 0),
                    backgroundColor: '#5F6670'
                },
                {
                    label: 'Observadas',
                    data: tiposLabels.map(t => solData[t]?.observada || 0),
                    backgroundColor: '#8FA8D6'
                },
                {
                    label: 'Rechazadas',
                    data: tiposLabels.map(t => solData[t]?.rechazada || 0),
                    backgroundColor: '#B91C1C'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 14 } } },
            scales: {
                x: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                y: { stacked: true, ticks: { callback(v) { const t = this.getLabelForValue(v); return t.length > 26 ? t.slice(0, 25) + '…' : t; } } }
            }
        }
    });

    // Sobre fondo oscuro el azul institucional y el gris no se distinguen: se aclaran
    const claros = { '#12377B': '#7EA6F0', '#092C6C': '#A9C4F5', '#5F6670': '#AAB5C8', '#BD8E07': '#F2C84B', '#B91C1C': '#F87171', 'rgba(18, 55, 123, 0.1)': 'rgba(126, 166, 240, 0.15)' };
    const graficos = ['chartCasosEstado', 'chartOrigenAlertas', 'chartAlertasRegla', 'chartCitasMes', 'chartTendencia', 'chartSolicitudesTipo', 'chartPrioridad']
        .map((id) => Chart.getChart(id)).filter(Boolean);
    const originales = graficos.map((g) => g.data.datasets.map((ds) => ({ fondo: ds.backgroundColor, borde: ds.borderColor })));
    const convertir = (c) => Array.isArray(c) ? c.map(convertir) : (oscuro() ? (claros[c] ?? c) : c);
    const pintar = () => graficos.forEach((g, i) => {
        const texto = oscuro() ? '#EEF2F7' : '#5F6670';
        const linea = oscuro() ? 'rgba(255, 255, 255, .14)' : 'rgba(0, 0, 0, .1)';
        g.data.datasets.forEach((ds, j) => {
            ds.backgroundColor = convertir(originales[i][j].fondo);
            if (originales[i][j].borde) ds.borderColor = convertir(originales[i][j].borde);
            if (g.config.type === 'doughnut') ds.borderColor = oscuro() ? '#0B1F44' : '#FFFFFF';
        });
        g.options.color = texto;
        Object.values(g.options.scales ?? {}).forEach((e) => {
            e.ticks = { ...e.ticks, color: texto };
            e.grid = { ...e.grid, color: linea };
        });
        g.update('none');
    });
    pintar();
    window.addEventListener('portal-theme', pintar);
});
</script>
@endsection
