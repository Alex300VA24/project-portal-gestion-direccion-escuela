<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $module }} · Portal de Dirección de Escuela</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">
    <!-- Barra superior -->
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-10 w-auto">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-white">{{ $module }}</h1>
                    <p class="text-xs text-indigo-200">Esquema PostgreSQL: <code class="font-mono bg-indigo-900 px-1 py-0.5 rounded text-indigo-300">{{ $schema }}</code></p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('bienestar.informacion.index') }}" class="text-sm font-medium text-indigo-100 hover:text-white transition">Información Pública</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-xs bg-indigo-800 hover:bg-indigo-700 px-3 py-1.5 rounded font-semibold text-white transition">Ir al Portal</a>
                @else
                    <a href="{{ route('login') }}" class="text-xs bg-indigo-600 hover:bg-indigo-500 px-3 py-1.5 rounded font-semibold text-white transition">Iniciar Sesión</a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        @if (session('status'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-8">
            <h2 class="text-2xl font-bold text-slate-900">Módulo de Gestión de Bienestar y Permanencia Estudiantil</h2>
            <p class="text-slate-600 mt-1">Portal integral de orientación normativa, acompañamiento psicopedagógico y detección oportuna de riesgo académico.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- 1. Sitio Informativo (Público) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Información Académica</h3>
                    <p class="text-sm text-slate-600 mt-2">Normativa de permanencia, separación por reiterancia, reserva de matrícula y escala aprobatoria.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-800">
                        Ver artículos públicos &rarr;
                    </a>
                </div>
            </div>

            <!-- 2. Estudiante: Solicitudes y Citas -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Portal del Estudiante</h3>
                    <p class="text-sm text-slate-600 mt-2">Gestión de solicitudes de apoyo socioeconómico y seguimiento a sus citas de consejería.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center space-x-4">
                    <a href="{{ route('bienestar.alumno.solicitudes.index') }}" class="inline-flex items-center text-sm font-semibold text-emerald-600 hover:text-emerald-800">
                        Mis Solicitudes &rarr;
                    </a>
                    <a href="{{ route('bienestar.alumno.citas.index') }}" class="inline-flex items-center text-xs font-medium text-slate-500 hover:text-slate-800">
                        Mis Citas
                    </a>
                </div>
            </div>

            <!-- 3. Docente: Reporte de Riesgo -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Portal Docente</h3>
                    <p class="text-sm text-slate-600 mt-2">Reporte preventivo de estudiantes en riesgo por inasistencias o bajo rendimiento sostenido.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.profesor.index') }}" class="inline-flex items-center text-sm font-semibold text-amber-600 hover:text-amber-800">
                        Acceso Docente &rarr;
                    </a>
                </div>
            </div>

            <!-- 4. Personal de Bienestar (Secretaría) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Panel de Bienestar</h3>
                    <p class="text-sm text-slate-600 mt-2">Hub operativo: alertas manuales/automáticas, solicitudes de apoyo, agenda directa y derivaciones.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.panel.index') }}" class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                        Gestión Bienestar &rarr;
                    </a>
                </div>
            </div>

            <!-- 5. Director de Escuela -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Dirección de Escuela</h3>
                    <p class="text-sm text-slate-600 mt-2">Reportes estadísticos agregados, conteos consolidados con k-anonimato y trazabilidad semestral.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.director.index') }}" class="inline-flex items-center text-sm font-semibold text-purple-600 hover:text-purple-800">
                        Métricas y Estadísticas &rarr;
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Pie de página -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo · 2026
    </footer>
</body>
</html>
