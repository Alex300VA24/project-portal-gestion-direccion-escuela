<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso Restringido por Control de Roles · Bienestar UNT</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">
    <!-- Barra superior -->
    <header class="bg-indigo-950 text-white shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.index') }}" class="flex items-center space-x-2 text-white hover:text-indigo-200 transition">
                    <img src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática" class="h-8 w-auto">
                    <span class="font-bold text-lg">Bienestar UNT</span>
                </a>
                <span class="text-indigo-400">/</span>
                <span class="text-sm text-rose-300 font-medium">Control de Acceso (403)</span>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('bienestar.informacion.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Información Normativa</a>
                <a href="{{ route('bienestar.index') }}" class="text-xs text-indigo-200 hover:text-white transition">Volver al Módulo</a>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-xs bg-rose-700/80 hover:bg-rose-600 px-3 py-1.5 rounded-lg text-white font-semibold transition shadow-sm">
                            Cerrar Sesión
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 max-w-4xl mx-auto px-4 py-10 w-full flex flex-col items-center justify-center">
        <div class="w-full bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <!-- Encabezado de alerta -->
            <div class="bg-gradient-to-r from-rose-900 to-indigo-950 text-white p-6 sm:p-8 flex items-start space-x-4">
                <div class="w-12 h-12 rounded-xl bg-rose-500/20 border border-rose-400/40 text-rose-300 flex items-center justify-center flex-shrink-0">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <div class="inline-flex items-center gap-2 text-rose-300 text-xs font-bold uppercase tracking-wider mb-1 font-mono">
                        <span>Código HTTP 403</span>
                        <span>·</span>
                        <span>Control de Acceso Basado en Roles (RBAC)</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-white">Esta sección requiere un rol de acceso diferente</h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1">
                        Tu cuenta no cuenta con los permisos necesarios para visualizar o gestionar este submódulo específico.
                    </p>
                </div>
            </div>

            <!-- Cuerpo de la tarjeta -->
            <div class="p-6 sm:p-8 space-y-6">
                <!-- Información de sesión activa -->
                @auth
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div>
                            <span class="text-slate-500 block">Usuario autenticado en esta sesión:</span>
                            <strong class="text-slate-900 text-sm">{{ auth()->user()->name }}</strong>
                            <span class="text-slate-600 font-mono block">{{ auth()->user()->email }}</span>
                        </div>
                        <div class="sm:text-right">
                            <span class="text-slate-500 block">Rol actual asignado:</span>
                            <span class="inline-block px-2.5 py-1 bg-indigo-100 text-indigo-800 rounded-lg font-bold font-mono text-xs">
                                {{ auth()->user()->roles->pluck('name')->implode(', ') ?: 'Sin rol asignado' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900">
                        <strong>Modo Invitado:</strong> No has iniciado sesión. Para acceder a esta sección debes autenticarte con una cuenta autorizada.
                    </div>
                @endauth

                <!-- Motivo legal y arquitectónico -->
                <div class="text-xs text-slate-600 leading-relaxed bg-indigo-50/50 border border-indigo-100 rounded-xl p-4">
                    <p class="font-semibold text-indigo-950 mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Protección de Privacidad Estudiantil (Ley N° 29733)
                    </p>
                    <p>
                        Por normativa institucional de la Universidad Nacional de Trujillo y en resguardo de la confidencialidad de los expedientes socioeconómicos, de salud y de riesgo académico, cada submódulo está estrictamente restringido a su rol correspondiente. No es un fallo técnico, sino la política de seguridad activa.
                    </p>
                </div>

                <!-- Cuentas de demostración para probar cada rol -->
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-3">
                        Cuentas de demostración disponibles (Contraseña para todas: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-indigo-700 font-mono font-bold">Portal2026!</code>)
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="border border-slate-200 rounded-xl p-3 bg-white hover:border-indigo-300 transition">
                            <div class="flex items-center justify-between mb-1">
                                <strong class="text-indigo-950 font-bold">Secretaría / Bienestar</strong>
                                <span class="text-[10px] bg-indigo-100 text-indigo-800 px-1.5 py-0.5 rounded font-mono font-bold">secretaria</span>
                            </div>
                            <code class="text-slate-700 font-mono block">secretaria@portal.local</code>
                            <p class="text-[11px] text-slate-500 mt-1">Acceso a: <strong>Panel Operativo (/bienestar/panel)</strong>, alertas y citas.</p>
                        </div>

                        <div class="border border-slate-200 rounded-xl p-3 bg-white hover:border-amber-300 transition">
                            <div class="flex items-center justify-between mb-1">
                                <strong class="text-amber-950 font-bold">Docente</strong>
                                <span class="text-[10px] bg-amber-100 text-amber-800 px-1.5 py-0.5 rounded font-mono font-bold">profesor</span>
                            </div>
                            <code class="text-slate-700 font-mono block">profesor@portal.local</code>
                            <p class="text-[11px] text-slate-500 mt-1">Acceso a: <strong>Portal Docente (/bienestar/profesor)</strong> para reportar riesgo.</p>
                        </div>

                        <div class="border border-slate-200 rounded-xl p-3 bg-white hover:border-emerald-300 transition">
                            <div class="flex items-center justify-between mb-1">
                                <strong class="text-emerald-950 font-bold">Estudiante</strong>
                                <span class="text-[10px] bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-mono font-bold">alumno</span>
                            </div>
                            <code class="text-slate-700 font-mono block">alumno@portal.local</code>
                            <p class="text-[11px] text-slate-500 mt-1">Acceso a: <strong>Portal Estudiante (/bienestar/alumno/solicitudes)</strong> y citas.</p>
                        </div>

                        <div class="border border-slate-200 rounded-xl p-3 bg-white hover:border-purple-300 transition">
                            <div class="flex items-center justify-between mb-1">
                                <strong class="text-purple-950 font-bold">Director de Escuela</strong>
                                <span class="text-[10px] bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded font-mono font-bold">director_escuela</span>
                            </div>
                            <code class="text-slate-700 font-mono block">director@portal.local</code>
                            <p class="text-[11px] text-slate-500 mt-1">Acceso a: <strong>Estadísticas agregadas (/bienestar/director)</strong>.</p>
                        </div>
                    </div>
                </div>

                <!-- Botones de acción -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <a href="{{ route('bienestar.index') }}" class="w-full sm:w-auto text-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                            &larr; Volver a Bienestar
                        </a>
                        <a href="{{ route('dashboard') }}" class="w-full sm:w-auto text-center px-3 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition">
                            Ir al Portal
                        </a>
                    </div>
                    @auth
                        <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="w-full sm:w-auto text-center px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                                Cerrar Sesión y Cambiar de Cuenta
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="w-full sm:w-auto text-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                            Iniciar Sesión
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </main>

    <!-- Pie de página -->
    <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
        Dirección de Escuela de Informática · Universidad Nacional de Trujillo · 2026
    </footer>
</body>
</html>
