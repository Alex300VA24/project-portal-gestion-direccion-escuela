<!DOCTYPE html>
<html lang="es" x-data="bienestarShell()" :class="{ 'overflow-hidden': navigationOpen }" @keydown.escape.window="navigationOpen = false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#12377B">
    <title>@yield('title', 'Bienestar') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700|roboto-slab:600,700,800|inter:400,500,600,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @include('modules.bienestar.partials.tema')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --unt-azul: #12377B;
            --unt-azul-oscuro: #092C6C;
            --u-noche: #0B1F44;
            --unt-dorado: #E6AD09;
            --unt-dorado-oscuro: #BD8E07;
            --u-dorado-texto: #8A6700;
            --unt-negro: #1B1811;
            --unt-fondo-suave: #F5F7FA;
            --u-papel: #F7F3EA;
            --unt-texto: #222222;
            --unt-texto-suave: #5F6670;
            --unt-borde: #E3E6EA;
        }

        body.quality-body {
            background-color: var(--unt-fondo-suave) !important;
            color: var(--unt-texto) !important;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif !important;
        }

        h1, h2, h3, .font-unt-title {
            font-family: 'Roboto Slab', serif !important;
            color: var(--unt-azul-tinta) !important;
        }

        :focus-visible,
        .quality-body :focus-visible,
        button:focus-visible,
        a:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            outline: 2px solid var(--unt-dorado) !important;
            outline-offset: 2px !important;
        }

        /* Topbar Superior: Negro Institucional (#1B1811) */
        .quality-topbar {
            background-color: var(--unt-negro) !important;
            border-bottom: 2px solid var(--unt-dorado) !important;
            color: #ffffff !important;
            height: 66px !important;
        }

        .quality-topbar .quality-breadcrumb {
            color: #cbd5e1 !important;
        }

        .quality-topbar .quality-breadcrumb a {
            color: #cbd5e1 !important;
        }

        .quality-topbar .quality-breadcrumb a:hover {
            color: var(--unt-dorado) !important;
        }

        .quality-topbar .quality-breadcrumb svg {
            color: #64748b !important;
        }

        .quality-topbar .quality-breadcrumb strong {
            color: var(--unt-dorado) !important;
        }

        .quality-topbar .quality-icon-button {
            background: rgba(255, 255, 255, 0.08) !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            color: #ffffff !important;
        }

        .quality-topbar .quality-icon-button:hover {
            background: rgba(230, 173, 9, 0.22) !important;
            border-color: var(--unt-dorado) !important;
            color: var(--unt-dorado) !important;
        }

        .quality-topbar .quality-user-trigger {
            color: #ffffff !important;
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            background: rgba(255, 255, 255, 0.06) !important;
        }

        .quality-topbar .quality-user-trigger:hover,
        .quality-topbar .quality-user-trigger[aria-expanded="true"] {
            background: rgba(255, 255, 255, 0.14) !important;
            border-color: var(--unt-dorado) !important;
        }

        .quality-topbar .quality-avatar {
            background: var(--unt-dorado) !important;
            color: var(--unt-negro) !important;
            font-weight: 800 !important;
        }

        .quality-topbar .quality-user-trigger strong {
            color: #ffffff !important;
        }

        .quality-topbar .quality-user-trigger small {
            color: #cbd5e1 !important;
        }

        /* Sidebar: Azul Noche (#0B1F44) */
        .quality-sidebar {
            background: var(--u-noche) !important;
            border-right: 1px solid rgba(230, 173, 9, 0.2) !important;
        }

        .quality-brand {
            border-bottom: 1px solid rgba(230, 173, 9, 0.25) !important;
        }

        .quality-brand strong {
            color: #ffffff !important;
            font-family: 'Roboto Slab', serif !important;
            letter-spacing: 0.02em;
        }

        .quality-brand small {
            color: var(--unt-dorado) !important;
            font-weight: 600 !important;
        }

        .quality-nav-link {
            color: #e2e8f0 !important;
            border-radius: 8px !important;
            transition: all 160ms ease !important;
            border-left: 3px solid transparent !important;
        }

        .quality-nav-link:hover {
            background: var(--unt-azul) !important;
            color: #ffffff !important;
            border-left: 3px solid var(--unt-dorado) !important;
        }

        .quality-nav-link.is-active {
            background: var(--unt-azul) !important;
            color: #ffffff !important;
            border-left: 4px solid var(--unt-dorado) !important;
            font-weight: 700 !important;
        }

        .quality-nav-link.is-active svg {
            color: var(--unt-dorado) !important;
        }

        .quality-module-switch {
            border: 1px solid rgba(255, 255, 255, 0.16) !important;
            color: #cbd5e1 !important;
            border-radius: 8px !important;
        }

        .quality-module-switch:hover {
            background: var(--unt-azul) !important;
            color: #ffffff !important;
            border-color: var(--unt-dorado) !important;
        }

        /* Utilidades de Componentes UNT */
        .btn-unt-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--unt-azul) !important;
            color: #ffffff !important;
            border-radius: 7px !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            padding: 0.55rem 1rem !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            transition: all 160ms ease !important;
            border: 1px solid transparent !important;
            cursor: pointer;
            text-decoration: none !important;
        }

        .btn-unt-primary:hover {
            background-color: var(--unt-azul-oscuro) !important;
            color: #ffffff !important;
            box-shadow: 0 8px 24px rgba(18, 55, 123, 0.18) !important;
            transform: translateY(-1px);
        }

        .btn-unt-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--unt-dorado) !important;
            color: var(--unt-negro) !important;
            border-radius: 7px !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            padding: 0.55rem 1rem !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            transition: all 160ms ease !important;
            border: 1px solid transparent !important;
            cursor: pointer;
            text-decoration: none !important;
        }

        .btn-unt-secondary:hover {
            background-color: var(--unt-dorado-oscuro) !important;
            color: var(--unt-negro) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(189, 142, 7, 0.25) !important;
        }

        .btn-unt-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: var(--unt-superficie) !important;
            color: var(--unt-azul-tinta) !important;
            border: 1.5px solid var(--unt-azul-tinta) !important;
            border-radius: 7px !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            padding: 0.5rem 0.875rem !important;
            transition: all 160ms ease !important;
            cursor: pointer;
            text-decoration: none !important;
        }

        .btn-unt-outline:hover {
            background-color: var(--unt-azul) !important;
            color: #ffffff !important;
        }

        .card-unt {
            background-color: var(--unt-superficie) !important;
            border: 1px solid var(--unt-borde) !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            transition: all 200ms ease !important;
        }

        .card-unt:hover {
            box-shadow: 0 8px 24px rgba(18, 55, 123, 0.18) !important;
            border-color: #cbd5e1 !important;
        }

        .card-unt-editorial {
            background-color: var(--u-papel) !important;
            border: 1px solid var(--unt-dorado) !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
        }

        .text-unt-dorado-accessible {
            color: var(--u-dorado-texto) !important;
            font-weight: 700;
        }
    </style>
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

                    <!-- Secciones para Secretaría -->
                    @if (auth()->user()->hasRole('secretaria') || auth()->user()->can('bienestar.secretaria'))
                        <a href="{{ route('bienestar.secretaria.alertas') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.secretaria.alertas*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span>Alertas en Revisión</span>
                        </a>
                        <a href="{{ route('bienestar.secretaria.solicitudes') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.secretaria.solicitudes*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span>Recepción Solicitudes</span>
                        </a>
                        <a href="{{ route('bienestar.secretaria.indicadores') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.secretaria.indicadores')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3v18h18M7 16l4-4 4 4 5-6"/></svg>
                            <span>Indicadores Operativos</span>
                        </a>
                    @endif

                    <!-- Secciones para Personal de Bienestar -->
                    @if (auth()->user()->hasRole('bienestar') || auth()->user()->can('bienestar.gestionar'))
                        <a href="{{ route('bienestar.panel.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.index') || request()->routeIs('bienestar.panel.atender') || request()->routeIs('bienestar.panel.alertas.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span>Panel de Alertas</span>
                        </a>
                        <a href="{{ route('bienestar.panel.casos.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.casos.*') || request()->routeIs('bienestar.panel.derivar')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Casos y Citas</span>
                        </a>
                        <a href="{{ route('bienestar.catalogo-solicitudes.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.catalogo-solicitudes.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Catálogo Trámites UNT</span>
                        </a>
                        <a href="{{ route('bienestar.panel.solicitudes.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.panel.solicitudes.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <span>Dictaminar Solicitudes</span>
                        </a>
                    @endif

                    <!-- Secciones para Director de Escuela o Bienestar (Estadísticas completas) -->
                    @if (auth()->user()->hasRole('director_escuela') || auth()->user()->hasRole('bienestar') || auth()->user()->can('bienestar.estadisticas'))
                        <a href="{{ route('bienestar.director.index') }}" @class(['quality-nav-link', 'is-active' => request()->routeIs('bienestar.director.*')])>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Estadísticas y Reportes</span>
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
                    @hasSection('migas')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        @yield('migas')
                    @endif
                    @hasSection('page-label')
                        @sectionMissing('migas')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>@endif
                        <strong aria-current="page">@yield('page-label')</strong>
                    @endif
                </nav>

                <div class="quality-topbar-actions">
                    @include('modules.bienestar.partials.boton-tema')
                    @auth
                        @include('modules.bienestar.partials.avisos')
                    @endauth

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
                        <a href="{{ route('login') }}" class="btn-unt-primary">
                            Iniciar sesión
                        </a>
                    @endauth
                </div>
            </header>

            <main id="contenido" class="quality-content" tabindex="-1">
                @if (session('status'))
                    <div class="aviso aviso-ok" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any() && ! old('_modal'))
                    <ul class="aviso aviso-error" role="alert">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <button class="quality-overlay" x-cloak x-show="navigationOpen" @click="navigationOpen = false" aria-label="Cerrar navegación"></button>

    @include('modules.bienestar.partials.modales')
    @auth
        @include('modules.bienestar.partials.avisos-vivo')
    @endauth
    <script>
        function bienestarShell() {
            return {
                navigationOpen: false,
                userOpen: false,
            };
        }
    </script>
@livewireScriptConfig
</body>
</html>
