<!DOCTYPE html>
<html lang="es" x-data="bienestarShell()" :class="{ 'overflow-hidden': navigationOpen }" @keydown.escape.window="navigationOpen = false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17224d">
    <title>@yield('title', 'Bienestar') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <script>
        (() => { const d = document.documentElement.dataset, s = localStorage; d.theme = s.getItem('portal-theme') || 'light'; d.contrast = s.getItem('portal-contrast') || 'normal'; d.fontSize = s.getItem('portal-font-size') || 'normal'; })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="quality-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="quality-shell">
        <!-- Barra lateral (Sidebar izquierda) -->
        <aside id="bienestar-navigation" class="quality-sidebar" :class="{ 'is-open': navigationOpen }" aria-label="Navegación de Bienestar">
            <a class="quality-brand" href="{{ route('bienestar.index') }}">
                <img class="quality-brand-mark" src="{{ asset('img/logo-informatica.png') }}" alt="Logo Informática">
                <span><strong>Bienestar</strong><small>Portal Gestión Dirección</small></span>
            </a>

            <nav class="quality-nav">
                <!-- Enlace al Inicio del Módulo -->
                <a href="{{ route('bienestar.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.index')])>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg>
                    <span>Inicio</span>
                </a>

                @auth
                    <!-- Secciones para Alumno -->
                    @if (auth()->user()->hasRole('alumno') || auth()->user()->can('bienestar.solicitar'))
                        <a href="{{ route('bienestar.alumno.solicitudes.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.alumno.solicitudes.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Mis Solicitudes</span>
                        </a>
                        <a href="{{ route('bienestar.alumno.citas.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.alumno.citas.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Mis Citas</span>
                        </a>
                    @endif

                    <!-- Secciones para Profesor -->
                    @if (auth()->user()->hasRole('profesor') || auth()->user()->can('bienestar.reportar'))
                        <a href="{{ route('bienestar.profesor.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.profesor.index')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <span>Mis Reportes</span>
                        </a>
                        <a href="{{ route('bienestar.profesor.create') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.profesor.create')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Reportar Riesgo</span>
                        </a>
                    @endif

                    <!-- Secciones para Secretaría / Personal de Bienestar -->
                    @if (auth()->user()->hasRole('secretaria') || auth()->user()->can('bienestar.gestionar'))
                        <a href="{{ route('bienestar.panel.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.index') || request()->routeIs('bienestar.panel.atender') || request()->routeIs('bienestar.panel.derivar')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Hub Operativo</span>
                        </a>
                        <a href="{{ route('bienestar.panel.alertas.create') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.alertas.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Alerta Manual</span>
                        </a>
                        <a href="{{ route('bienestar.panel.solicitudes.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.solicitudes.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span>Evaluar Apoyos</span>
                        </a>
                    @endif

                    <!-- Secciones para Director de Escuela -->
                    @if (auth()->user()->hasRole('director_escuela') || auth()->user()->can('bienestar.estadisticas'))
                        <a href="{{ route('bienestar.director.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.director.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Estadísticas</span>
                        </a>
                    @endif
                @endauth

                <!-- Información Normativa Pública -->
                <a href="{{ route('bienestar.informacion.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.informacion.*')])>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    <span>Normativa Académica</span>
                </a>
            </nav>

            <a class="quality-module-switch" href="{{ route('dashboard') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                <span>Cambiar de módulo</span>
            </a>
        </aside>

        <!-- Contenedor principal con Topbar -->
        <div class="quality-main-shell">
            <header class="quality-topbar">
                <button class="quality-icon-button quality-menu-button" type="button" @click="navigationOpen = !navigationOpen" :aria-expanded="navigationOpen.toString()" aria-controls="bienestar-navigation" aria-label="Abrir navegación">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>

                <nav class="quality-breadcrumb" aria-label="Ruta actual">
                    <a href="{{ route('dashboard') }}">Portal</a>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    <a href="{{ route('bienestar.index') }}">Bienestar</a>
                    @hasSection('page-label')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        <strong aria-current="page">@yield('page-label')</strong>
                    @endif
                </nav>

                <div class="quality-topbar-actions">
                    <button class="quality-icon-button" type="button" onclick="window.portalToggleTheme()" aria-label="Cambiar tema de color">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    </button>

                    @auth
                        <div class="quality-user-menu" @click.outside="userOpen = false">
                            <button class="quality-user-trigger" type="button" @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu">
                                <span class="quality-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
                                <span><strong>{{ auth()->user()->name }}</strong><small>{{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')->replace('_', ' ') }}</small></span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="quality-user-panel" x-cloak x-show="userOpen" x-transition role="menu">
                                <a href="{{ route('profile.edit') }}" role="menuitem">Mi perfil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" role="menuitem">Cerrar sesión</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm">
                            Iniciar sesión
                        </a>
                    @endauth
                </div>
            </header>

            <main id="contenido" class="quality-content" tabindex="-1">
                @if (session('status'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <button class="quality-overlay" x-cloak x-show="navigationOpen" @click="navigationOpen = false" aria-label="Cerrar navegación"></button>

    <script>
        function bienestarShell() {
            return {
                navigationOpen: false,
                userOpen: false,
            };
        }
        window.portalToggleTheme = () => {
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.dataset.theme = next;
            localStorage.setItem('portal-theme', next);
        };
    </script>
</body>
</html>
