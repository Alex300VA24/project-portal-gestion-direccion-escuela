<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Citas · Bienestar UNT</title>
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
                <span class="text-sm text-indigo-200 font-medium">Mis Citas de Acompañamiento</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Mis Solicitudes</a>
                <a href="{{ route('bienestar.informacion.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Información Normativa</a>
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto px-4 py-8 w-full space-y-6">
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Mis Citas de Atención y Consejería</h1>
                <p class="text-xs text-slate-500 mt-1">Estudiante: <strong>{{ $actor->nombreCompleto }}</strong> · Cód. {{ $actor->codigoEstudiante }}</p>
            </div>
            <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition">
                Ver Mis Solicitudes de Apoyo
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-3 text-left">Área / Motivo</th>
                        <th class="px-6 py-3 text-left">Especialista / Atención</th>
                        <th class="px-6 py-3 text-center">Fecha y Hora</th>
                        <th class="px-6 py-3 text-center">Modalidad</th>
                        <th class="px-6 py-3 text-right">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($citas as $cita)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 block">{{ $cita->caso?->tipoCaso?->nombre ?? 'Atención Bienestar' }}</span>
                                <span class="text-xs text-slate-500">Caso #{{ $cita->id_caso }}</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-700">
                                {{ $cita->especialista?->usuario?->name ?? 'Personal de Bienestar' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-xs text-slate-600 font-mono">
                                <div>{{ $cita->inicio?->format('d/m/Y') }}</div>
                                <div class="text-slate-400 text-[11px]">{{ $cita->inicio?->format('H:i') }} - {{ $cita->fin?->format('H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $cita->modalidad === 'Virtual' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                                    {{ $cita->modalidad }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                @if ($cita->estado === 'Programada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Programada</span>
                                @elseif ($cita->estado === 'Realizada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Realizada</span>
                                @elseif ($cita->estado === 'Cancelada')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">Cancelada</span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-700">{{ $cita->estado }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                No tiene citas de consejería o acompañamiento programadas actualmente.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $citas->links() }}
        </div>
    </main>
</body>
</html>