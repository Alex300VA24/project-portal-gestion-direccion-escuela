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
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.informacion.index') }}" class="text-xs sm:text-sm font-medium text-indigo-100 hover:text-white transition">Información Pública</a>
                @auth
                    <div class="hidden md:flex flex-col text-right">
                        <span class="text-xs font-semibold text-white">{{ auth()->user()->name }}</span>
                        <span class="text-[11px] text-indigo-300 font-mono">{{ auth()->user()->email }} ({{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'Sin rol' }})</span>
                    </div>
                    <a href="{{ route('dashboard') }}" class="text-xs bg-indigo-800 hover:bg-indigo-700 px-3 py-1.5 rounded-lg font-semibold text-white transition">Ir al Portal</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs bg-rose-700 hover:bg-rose-600 px-3 py-1.5 rounded-lg font-semibold text-white shadow-sm transition">
                            Cerrar Sesión
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-xs bg-indigo-600 hover:bg-indigo-500 px-3.5 py-1.5 rounded-lg font-semibold text-white shadow-sm transition">Iniciar Sesión</a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full space-y-6">
        @if (session('status'))
            <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
                {{ session('status') }}
            </div>
        @endif

        <div>
            <h2 class="text-2xl font-bold text-slate-900">Módulo de Gestión de Bienestar y Permanencia Estudiantil</h2>
            <p class="text-slate-600 mt-1">Portal integral de orientación normativa, acompañamiento psicopedagógico y detección oportuna de riesgo académico.</p>
        </div>

        <!-- Guía de Cuentas de Acceso por Rol (Demostración) -->
        <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-sm border border-slate-800">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-sm font-bold text-indigo-300 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Control de Acceso Basado en Roles (RBAC) · Cuentas de Prueba
                    </h3>
                    <p class="text-xs text-slate-300 mt-1">
                        Cada submódulo está estrictamente restringido a su rol. Si accedes con un rol diferente obtendrás <span class="font-mono text-rose-300 font-bold">403 Forbidden</span>. Contraseña para todas las cuentas: <span class="font-mono bg-indigo-900/80 px-2 py-0.5 rounded text-amber-300 font-bold">Portal2026!</span>
                    </p>
                </div>
                @auth
                    <div class="bg-indigo-900/60 border border-indigo-700/50 rounded-xl px-4 py-2 text-xs">
                        <span class="text-slate-400 block">Sesión actual:</span>
                        <strong class="text-white">{{ auth()->user()->email }}</strong>
                        <span class="text-emerald-400 font-mono block">Rol: {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'ninguno' }}</span>
                    </div>
                @endauth
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-emerald-400">1. Alumno</span>
                        <span class="text-[10px] bg-emerald-950 text-emerald-300 px-1.5 py-0.5 rounded border border-emerald-800">alumno</span>
                    </div>
                    <code class="font-mono text-slate-200 block">alumno@portal.local</code>
                    <p class="text-[11px] text-slate-400 mt-1">Solicitudes de apoyo socioeconómico y mis citas.</p>
                </div>

                <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-amber-400">2. Docente</span>
                        <span class="text-[10px] bg-amber-950 text-amber-300 px-1.5 py-0.5 rounded border border-amber-800">profesor</span>
                    </div>
                    <code class="font-mono text-slate-200 block">profesor@portal.local</code>
                    <p class="text-[11px] text-slate-400 mt-1">Reportes preventivos de estudiantes en riesgo.</p>
                </div>

                <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-indigo-400">3. Secretaría / Bienestar</span>
                        <span class="text-[10px] bg-indigo-950 text-indigo-300 px-1.5 py-0.5 rounded border border-indigo-800">secretaria</span>
                    </div>
                    <code class="font-mono text-slate-200 block">secretaria@portal.local</code>
                    <p class="text-[11px] text-slate-400 mt-1">Hub operativo, alertas de riesgo, derivaciones y citas.</p>
                </div>

                <div class="bg-slate-800/80 border border-slate-700/60 rounded-xl p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-bold text-purple-400">4. Director Escuela</span>
                        <span class="text-[10px] bg-purple-950 text-purple-300 px-1.5 py-0.5 rounded border border-purple-800">director_escuela</span>
                    </div>
                    <code class="font-mono text-slate-200 block">director@portal.local</code>
                    <p class="text-[11px] text-slate-400 mt-1">Estadísticas agregadas protegidas con k-anonimato.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- 1. Sitio Informativo (Público) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-indigo-300 transition">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded-full">
                            Público · Libre Acceso
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Información Académica</h3>
                    <p class="text-sm text-slate-600 mt-2">Normativa de permanencia, separación por reiterancia, reserva de matrícula y escala aprobatoria.</p>
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('bienestar.informacion.index') }}" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-800">
                        Ver artículos públicos &rarr;
                    </a>
                    <span class="text-[11px] text-slate-400">Sin login requerido</span>
                </div>
            </div>

            <!-- 2. Estudiante: Solicitudes y Citas -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-emerald-300 transition">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">
                            Rol: Alumno
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Portal del Estudiante</h3>
                    <p class="text-sm text-slate-600 mt-2">Gestión de solicitudes de apoyo socioeconómico y seguimiento a sus citas de consejería.</p>

                    @auth
                        @if (auth()->user()->hasRole('alumno') || auth()->user()->can('bienestar.solicitar'))
                            <div class="mt-3 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 inline-block">
                                ✓ Acceso habilitado con tu cuenta actual
                            </div>
                        @else
                            <div class="mt-3 text-[11px] text-rose-700 bg-rose-50 px-2 py-1 rounded border border-rose-200">
                                ⚠️ Tu cuenta no es Alumno (dará 403). Requiere <span class="font-mono font-bold">alumno@portal.local</span>
                            </div>
                        @endif
                    @else
                        <div class="mt-3 text-[11px] text-slate-500 bg-slate-100 px-2 py-1 rounded">
                            Requiere iniciar sesión como <span class="font-mono">alumno@portal.local</span>
                        </div>
                    @endauth
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
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-amber-300 transition">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-full">
                            Rol: Profesor
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Portal Docente</h3>
                    <p class="text-sm text-slate-600 mt-2">Reporte preventivo de estudiantes en riesgo por inasistencias o bajo rendimiento sostenido.</p>

                    @auth
                        @if (auth()->user()->hasRole('profesor') || auth()->user()->can('bienestar.reportar'))
                            <div class="mt-3 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 inline-block">
                                ✓ Acceso habilitado con tu cuenta actual
                            </div>
                        @else
                            <div class="mt-3 text-[11px] text-rose-700 bg-rose-50 px-2 py-1 rounded border border-rose-200">
                                ⚠️ Tu cuenta no es Profesor (dará 403). Requiere <span class="font-mono font-bold">profesor@portal.local</span>
                            </div>
                        @endif
                    @else
                        <div class="mt-3 text-[11px] text-slate-500 bg-slate-100 px-2 py-1 rounded">
                            Requiere iniciar sesión como <span class="font-mono">profesor@portal.local</span>
                        </div>
                    @endauth
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
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full">
                            Rol: Secretaría
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Panel de Bienestar</h3>
                    <p class="text-sm text-slate-600 mt-2">Hub operativo: alertas manuales/automáticas, solicitudes de apoyo, agenda directa y derivaciones.</p>

                    @auth
                        @if (auth()->user()->hasRole('secretaria') || auth()->user()->can('bienestar.gestionar'))
                            <div class="mt-3 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 inline-block">
                                ✓ Acceso habilitado con tu cuenta actual
                            </div>
                        @else
                            <div class="mt-3 text-[11px] text-rose-700 bg-rose-50 px-2 py-1 rounded border border-rose-200">
                                ⚠️ Tu cuenta no es Secretaría (dará 403). Requiere <span class="font-mono font-bold">secretaria@portal.local</span>
                            </div>
                        @endif
                    @else
                        <div class="mt-3 text-[11px] text-slate-500 bg-slate-100 px-2 py-1 rounded">
                            Requiere iniciar sesión como <span class="font-mono">secretaria@portal.local</span>
                        </div>
                    @endauth
                </div>
                <div class="mt-6 pt-4 border-t border-slate-100">
                    <a href="{{ route('bienestar.panel.index') }}" class="inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                        Gestión Bienestar &rarr;
                    </a>
                </div>
            </div>

            <!-- 5. Director de Escuela -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col justify-between hover:border-purple-300 transition">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <span class="text-[11px] font-bold uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200 px-2 py-0.5 rounded-full">
                            Rol: Director Escuela
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Dirección de Escuela</h3>
                    <p class="text-sm text-slate-600 mt-2">Reportes estadísticos agregados, conteos consolidados con k-anonimato y trazabilidad semestral.</p>

                    @auth
                        @if (auth()->user()->hasRole('director_escuela') || auth()->user()->can('bienestar.estadisticas'))
                            <div class="mt-3 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 inline-block">
                                ✓ Acceso habilitado con tu cuenta actual
                            </div>
                        @else
                            <div class="mt-3 text-[11px] text-rose-700 bg-rose-50 px-2 py-1 rounded border border-rose-200">
                                ⚠️ Tu cuenta no es Director (dará 403). Requiere <span class="font-mono font-bold">director@portal.local</span>
                            </div>
                        @endif
                    @else
                        <div class="mt-3 text-[11px] text-slate-500 bg-slate-100 px-2 py-1 rounded">
                            Requiere iniciar sesión como <span class="font-mono">director@portal.local</span>
                        </div>
                    @endauth
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
