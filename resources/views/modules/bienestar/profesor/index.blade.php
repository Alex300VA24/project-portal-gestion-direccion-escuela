<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportes Docentes de Riesgo Estudiantil · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-indigo-200 font-medium">Portal Docente</span>
            </div>
            <div class="flex items-center space-x-3">
                <span class="text-xs text-indigo-300">Docente: <strong>{{ $actor->nombreCompleto }}</strong> ({{ $actor->codigoDocente ?? 'DOC-001' }})</span>
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

    <main class="flex-1 max-w-6xl mx-auto px-4 py-8 w-full">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mis Reportes de Riesgo Estudiantil</h1>
                <p class="text-slate-600 text-sm mt-1">Seguimiento al estado de atención de los estudiantes que has reportado para acompañamiento de Bienestar.</p>
            </div>
            <a href="{{ route('bienestar.profesor.create') }}" class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold rounded-lg shadow-sm transition">
                + Reportar Estudiante en Riesgo
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Estudiante</th>
                        <th class="px-6 py-3 text-left">Curso</th>
                        <th class="px-6 py-3 text-left">Motivo del Reporte</th>
                        <th class="px-6 py-3 text-center">Prioridad</th>
                        <th class="px-6 py-3 text-center">Estado en Bienestar</th>
                        <th class="px-6 py-3 text-right">Fecha de Envío</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reportes as $rep)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900">
                                    {{ $rep->estudiante?->persona?->nombres }} {{ $rep->estudiante?->persona?->apellido_paterno }}
                                </div>
                                <div class="text-xs text-slate-500 font-mono">
                                    Cód. {{ $rep->estudiante?->codigo_universitario }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                                {{ $rep->curso }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 max-w-xs truncate" title="{{ $rep->motivo_reporte }}">
                                {{ $rep->motivo_reporte }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($rep->prioridad === 'alta')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Alta</span>
                                @elseif ($rep->prioridad === 'media')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Media</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">Baja</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if ($rep->estado === 'pendiente')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Pendiente de Revisión</span>
                                @elseif ($rep->estado === 'en_atencion')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">En Atención / Citado</span>
                                @elseif ($rep->estado === 'atendida')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Atendido por Bienestar</span>
                                @elseif ($rep->estado === 'descartada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">Descartado</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">{{ ucfirst($rep->estado) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs text-slate-500">
                                {{ $rep->created_at?->format('d/m/Y H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                No has emitido reportes de riesgo estudiantil todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $reportes->links() }}
        </div>
    </main>
</body>
</html>
