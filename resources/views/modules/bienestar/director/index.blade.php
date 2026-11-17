<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Métricas Consolidadas de Bienestar · Dirección de Escuela UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Dirección de Escuela</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-200 font-medium">Estadísticas Agregadas de Bienestar</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver al Módulo</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs bg-rose-700/80 hover:bg-rose-600 px-2.5 py-1 rounded text-white font-semibold transition">
                        Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto px-4 py-8 w-full space-y-8">
        <!-- Aviso de k-anonimato y confidencialidad -->
        <div class="p-4 rounded-xl bg-purple-50 border border-purple-200 text-purple-900 text-xs flex items-start space-x-3">
            <svg class="w-5 h-5 text-purple-700 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <strong class="font-bold">Política de Privacidad y K-Anonimato (Ley N° 29733):</strong>
                En estricto cumplimiento del principio de proporcionalidad, la Dirección de Escuela accede únicamente a indicadores agregados y frecuencias consolidadas por periodo lectivo. Para resguardar la identidad de los estudiantes, cualquier celda o conteo menor a cinco casos se reporta con la etiqueta <span class="font-bold font-mono bg-purple-100 px-1 py-0.5 rounded text-purple-900">"menos de 5"</span>. Este módulo no expone nombres, registros clínicos ni identificadores individuales.
            </div>
        </div>

        <!-- Selector de periodo -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Métricas y Reportes de Permanencia Estudiantil</h1>
                <p class="text-xs text-slate-500 mt-1">Periodo académico activo evaluado: <strong class="font-mono text-indigo-700">{{ $periodo }}</strong></p>
            </div>
            <form action="{{ route('bienestar.director.index') }}" method="GET" class="flex items-center space-x-2">
                <label for="periodo" class="text-xs font-bold text-slate-600">Periodo:</label>
                <select name="periodo" id="periodo" onchange="this.form.submit()" class="rounded-lg border-slate-300 text-xs font-mono font-semibold">
                    @foreach ($periodosDisponibles as $p)
                        <option value="{{ $p }}" @selected($periodo === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <!-- Tarjetas resumen consolidadas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Alertas Automáticas</span>
                <p class="text-3xl font-extrabold text-indigo-700 mt-2 font-mono">
                    {{ $porOrigen['automatica'] ?? '0' }}
                </p>
                <span class="text-xs text-slate-400 mt-1 block">Detectadas por el sistema</span>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Reportes Docentes</span>
                <p class="text-3xl font-extrabold text-amber-600 mt-2 font-mono">
                    {{ $porOrigen['reporte_docente'] ?? '0' }}
                </p>
                <span class="text-xs text-slate-400 mt-1 block">Enviados por docentes</span>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Casos en Acompañamiento</span>
                <p class="text-3xl font-extrabold text-emerald-600 mt-2 font-mono">
                    {{ $totalCasos }}
                </p>
                <span class="text-xs text-slate-400 mt-1 block">Casos de bienestar abiertos</span>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Derivaciones Especializadas</span>
                <p class="text-3xl font-extrabold text-purple-600 mt-2 font-mono">
                    {{ $totalDerivaciones }}
                </p>
                <span class="text-xs text-slate-400 mt-1 block">Especialistas externos</span>
            </div>
        </div>

        <!-- Tablas de distribución agregada -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Por Causal / Regla -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Distribución por Causal de Riesgo</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Tercera Matrícula (Separación 1 año)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['tercera_matricula'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Cuarta Matrícula (Retiro definitivo)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['cuarta_matricula'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Inhabilitación por Inasistencias (&gt;30%)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['inasistencia_inhabilitacion'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Inasistencias Preventivas (20% - 30%)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['inasistencia_preventiva'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Carga Mínima Insuficiente (&lt;12 créditos)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['creditos_insuficientes'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Reporte Directo del Docente</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-900">{{ $porRegla['reporte_docente'] ?? '0' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Por Estado de Atención -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Distribución por Estado de Atención</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Pendiente de Atención</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-amber-700">{{ $porEstado['pendiente'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">En Atención / Acompañamiento</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-indigo-700">{{ $porEstado['en_atencion'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Atendida y Concluida</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-emerald-700">{{ $porEstado['atendida'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Descartada Justificadamente</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-slate-600">{{ $porEstado['descartada'] ?? '0' }}</td>
                        </tr>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-3.5 text-slate-700">Resuelta Automáticamente (Cese de condición)</td>
                            <td class="px-6 py-3.5 text-right font-mono font-bold text-teal-700">{{ $porEstado['resuelta_automaticamente'] ?? '0' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        Información estadística para toma de decisiones académicas · Dirección de Escuela de Informática UNT
    </footer>
</body>
</html>
