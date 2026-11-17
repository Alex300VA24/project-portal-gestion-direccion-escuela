<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Bienestar Universitario · UNT</title>
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
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-200 font-medium">Panel Operativo de Riesgo</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.panel.alertas.create') }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                    + Nueva Alerta Manual
                </a>
                <a href="{{ route('bienestar.panel.solicitudes.index') }}" class="px-3 py-1.5 bg-indigo-700 hover:bg-indigo-800 text-white rounded-lg text-xs font-bold shadow-sm transition">
                    Solicitudes de Apoyo
                </a>
                <a href="{{ route('bienestar.informacion.create') }}" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-xs font-bold shadow-sm transition">+ Nuevo Artículo</a>
                <a href="{{ route('bienestar.informacion.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Artículos Públicos</a>
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs bg-rose-700/80 hover:bg-rose-600 px-2.5 py-1 rounded text-white font-semibold transition">
                        Cerrar Sesión
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto px-4 py-8 w-full">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <!-- Métricas resumidas -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Alertas Pendientes</span>
                <p class="text-3xl font-extrabold text-amber-600 mt-2">{{ $totales['pendientes'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Alta Prioridad</span>
                <p class="text-3xl font-extrabold text-rose-600 mt-2">{{ $totales['alta_prioridad'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">En Atención / Citados</span>
                <p class="text-3xl font-extrabold text-indigo-600 mt-2">{{ $totales['en_atencion'] }}</p>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Casos Abiertos</span>
                <p class="text-3xl font-extrabold text-slate-800 mt-2">{{ $totales['casos_abiertos'] }}</p>
            </div>
        </div>

        <!-- Filtros de búsqueda -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6">
            <form action="{{ route('bienestar.panel.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Origen</label>
                    <select name="origen" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">Todos los orígenes</option>
                        <option value="automatica" @selected(($filtros['origen'] ?? '') === 'automatica')>Detección Automática</option>
                        <option value="reporte_docente" @selected(($filtros['origen'] ?? '') === 'reporte_docente')>Reporte Docente</option>
                        <option value="manual_bienestar" @selected(($filtros['origen'] ?? '') === 'manual_bienestar')>Registro Manual Bienestar</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Prioridad</label>
                    <select name="prioridad" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">Todas las prioridades</option>
                        <option value="alta" @selected(($filtros['prioridad'] ?? '') === 'alta')>Alta</option>
                        <option value="media" @selected(($filtros['prioridad'] ?? '') === 'media')>Media</option>
                        <option value="baja" @selected(($filtros['prioridad'] ?? '') === 'baja')>Baja</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Estado</label>
                    <select name="estado" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" @selected(($filtros['estado'] ?? '') === 'pendiente')>Pendiente</option>
                        <option value="en_atencion" @selected(($filtros['estado'] ?? '') === 'en_atencion')>En Atención</option>
                        <option value="atendida" @selected(($filtros['estado'] ?? '') === 'atendida')>Atendida</option>
                        <option value="descartada" @selected(($filtros['estado'] ?? '') === 'descartada')>Descartada</option>
                        <option value="resuelta_automaticamente" @selected(($filtros['estado'] ?? '') === 'resuelta_automaticamente')>Resuelta Automática</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Buscar Estudiante</label>
                    <input type="text" name="buscar" value="{{ $filtros['buscar'] ?? '' }}" placeholder="Código o nombre..." class="w-full rounded-lg border-slate-300 text-xs">
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition">
                        Filtrar
                    </button>
                    <a href="{{ route('bienestar.panel.index') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla de alertas -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Estudiante</th>
                        <th class="px-6 py-3 text-left">Origen & Regla</th>
                        <th class="px-6 py-3 text-left">Curso</th>
                        <th class="px-6 py-3 text-center">Prioridad</th>
                        <th class="px-6 py-3 text-center">Estado</th>
                        <th class="px-6 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($alertas as $alt)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900">
                                    {{ $alt->estudiante?->persona?->nombres }} {{ $alt->estudiante?->persona?->apellido_paterno }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Cód. {{ $alt->estudiante?->codigo_universitario }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    @if ($alt->origen === 'automatica')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-100 text-purple-800">
                                            Automática
                                        </span>
                                    @elseif ($alt->origen === 'manual_bienestar')
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                            Manual
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800">
                                            Docente
                                        </span>
                                    @endif
                                    <span class="text-xs font-semibold text-slate-700">
                                        {{ str_replace('_', ' ', ucfirst($alt->regla)) }}
                                    </span>
                                </div>
                                @if ($alt->motivo_reporte)
                                    <p class="text-xs text-slate-500 mt-1 truncate max-w-xs" title="{{ $alt->motivo_reporte }}">
                                        {{ $alt->motivo_reporte }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700 text-xs">
                                {{ $alt->curso }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($alt->prioridad === 'alta')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Alta</span>
                                @elseif ($alt->prioridad === 'media')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Media</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">Baja</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($alt->estado === 'pendiente')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Pendiente</span>
                                @elseif ($alt->estado === 'en_atencion')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">En Atención</span>
                                @elseif ($alt->estado === 'atendida')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Atendida</span>
                                @elseif ($alt->estado === 'descartada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">Descartada</span>
                                @elseif ($alt->estado === 'resuelta_automaticamente')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-700 border border-teal-200">Resuelta Auto</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">{{ ucfirst($alt->estado) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <a href="{{ route('bienestar.panel.atender', $alt) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                                    Atender / Gestionar &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No se encontraron alertas registradas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $alertas->links() }}
        </div>
    </main>
</body>
</html>
