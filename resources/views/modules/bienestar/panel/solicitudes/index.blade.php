<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitudes de Apoyo Estudiantil · Bienestar UNT</title>
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
                <span class="text-sm text-indigo-200 font-medium">Evaluación de Solicitudes de Apoyo</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('bienestar.panel.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Panel de Alertas</a>
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto px-4 py-8 w-full space-y-6">
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Solicitudes de Apoyo Socioeconómico</h1>
                <p class="text-xs text-slate-500 mt-1">Revisión y dictamen de peticiones de beneficios (Comedor, Becas) presentadas por estudiantes.</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <form action="{{ route('bienestar.panel.solicitudes.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Estado</label>
                    <select name="estado" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">Todos los estados</option>
                        <option value="Pendiente" @selected(($filtros['estado'] ?? '') === 'Pendiente')>Pendiente</option>
                        <option value="Aprobada" @selected(($filtros['estado'] ?? '') === 'Aprobada')>Aprobada</option>
                        <option value="Rechazada" @selected(($filtros['estado'] ?? '') === 'Rechazada')>Rechazada</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Beneficio</label>
                    <select name="id_beneficio" class="w-full rounded-lg border-slate-300 text-xs">
                        <option value="">Todos los beneficios</option>
                        @foreach ($beneficios as $b)
                            <option value="{{ $b->id_beneficio }}" @selected(($filtros['id_beneficio'] ?? '') == $b->id_beneficio)>{{ $b->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition">
                        Filtrar
                    </button>
                    <a href="{{ route('bienestar.panel.solicitudes.index') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Estudiante</th>
                        <th class="px-6 py-3 text-left">Beneficio Solicitado</th>
                        <th class="px-6 py-3 text-left">Motivo / Sustento</th>
                        <th class="px-6 py-3 text-center">Fecha Solicitud</th>
                        <th class="px-6 py-3 text-center">Estado</th>
                        <th class="px-6 py-3 text-right">Evaluación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($solicitudes as $sol)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900">
                                    {{ $sol->estudiante?->persona?->nombres }} {{ $sol->estudiante?->persona?->apellido_paterno }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Cód. {{ $sol->estudiante?->codigo_universitario }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-800 font-semibold text-xs">
                                {{ $sol->beneficio?->nombre }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600 max-w-sm truncate" title="{{ $sol->motivo }}">
                                {{ $sol->motivo }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-slate-500 font-mono">
                                {{ $sol->solicitada_en?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($sol->estado === 'Pendiente')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Pendiente</span>
                                @elseif ($sol->estado === 'Aprobada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Aprobada</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">Rechazada</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if ($sol->estado === 'Pendiente')
                                    <form action="{{ route('bienestar.panel.solicitudes.evaluar', $sol) }}" method="POST" class="inline-flex space-x-2">
                                        @csrf
                                        <button type="submit" name="estado" value="Aprobada" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-bold transition">
                                            Aprobar
                                        </button>
                                        <button type="submit" name="estado" value="Rechazada" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-bold transition">
                                            Rechazar
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400 italic">Evaluada el {{ $sol->resuelta_en?->format('d/m/Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No se encontraron solicitudes registradas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $solicitudes->links() }}
        </div>
    </main>
</body>
</html>
