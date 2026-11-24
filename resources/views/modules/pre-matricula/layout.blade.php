@php
    $esAlumno = auth()->user()->hasRole('alumno');
    $esCoordinacion = auth()->user()->hasAnyRole(['director_escuela', 'secretaria']);
    $menu = array_values(array_filter([
        $esAlumno ? ['pre-matricula.alumno.index', 'Mi prematrícula', '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/>'] : null,
        $esAlumno ? ['pre-matricula.alumno.historial', 'Mis constancias', '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.index', 'Inicio', '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.periodos', 'Períodos', '<path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 14h3"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.plan', 'Plan de estudios', '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M8 7h8M8 11h6"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.ventanas', 'Ventanas', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.resultados', 'Resultados oficiales', '<path d="M4 4h16v16H4z"/><path d="M4 10h16M10 4v16"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.registros', 'Registros', '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.demanda', 'Demanda', '<path d="M4 19V9m5 10V5m5 14v-8m5 8V7"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.cuentas', 'Cuentas', '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M17 11h6M20 8v6"/>'] : null,
        $esCoordinacion ? ['pre-matricula.coordinacion.estudiantes', 'Estudiantes', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'] : null,
    ]));
    $inicio = $esAlumno ? route('pre-matricula.alumno.index') : ($esCoordinacion ? route('pre-matricula.coordinacion.index') : route('pre-matricula.index'));
@endphp
<!DOCTYPE html>
<html lang="es" x-data="qualityShell()" :class="{ 'overflow-hidden': navigationOpen || a11yOpen }" @keydown.escape.window="a11yOpen = false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17224d">
    <title>@yield('title', 'Pre Matrícula') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <script>
        (() => { const d = document.documentElement.dataset, s = localStorage; d.theme = s.getItem('portal-theme') || 'light'; d.contrast = s.getItem('portal-contrast') || 'normal'; d.fontSize = s.getItem('portal-font-size') || 'normal'; })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('modules.pre-matricula.partials.estilos')
</head>
<body class="quality-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="quality-shell">
        <aside id="quality-navigation" class="quality-sidebar" :class="{ 'is-open': navigationOpen }" aria-label="Navegación de Pre Matrícula">
            <a class="quality-brand" href="{{ $inicio }}">
                <img class="quality-brand-mark" src="{{ asset('img/logo-informatica.png') }}" alt="">
                <span><strong>Pre Matrícula</strong><small>Portal Gestión Dirección</small></span>
            </a>
            <nav class="quality-nav">
                @foreach ($menu as [$ruta, $etiqueta, $icono])
                    @php $activo = request()->routeIs($ruta) || request()->routeIs($ruta.'.*'); @endphp
                    <a href="{{ route($ruta) }}" @class(['quality-nav-link', 'is-active' => $activo]) @if($activo) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icono !!}</svg><span>{{ $etiqueta }}</span>
                    </a>
                @endforeach
            </nav>
            <a class="quality-module-switch" href="{{ route('dashboard') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg><span>Cambiar de módulo</span>
            </a>
        </aside>
        <div class="quality-main-shell">
            <header class="quality-topbar">
                <button class="quality-icon-button quality-menu-button" type="button" @click="navigationOpen = !navigationOpen" :aria-expanded="navigationOpen.toString()" aria-controls="quality-navigation" aria-label="Abrir navegación">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <nav class="quality-breadcrumb" aria-label="Ruta actual">
                    <a href="{{ route('dashboard') }}">Portal</a><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><a href="{{ $inicio }}">Pre Matrícula</a>
                    @hasSection('page-label')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><strong aria-current="page">@yield('page-label')</strong>@endif
                </nav>
                <div class="quality-topbar-actions">
                    <button class="quality-icon-button" type="button" onclick="window.portalToggleTheme()" aria-label="Cambiar tema de color">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    </button>
                    <div class="quality-user-menu" @click.outside="userOpen = false">
                        <button class="quality-user-trigger" type="button" @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu">
                            <span class="quality-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
                            <span><strong>{{ auth()->user()->name }}</strong><small>{{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')->replace('_', ' ') }}</small></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="quality-user-panel" x-cloak x-show="userOpen" x-transition role="menu">
                            <a href="{{ route('profile.edit') }}" role="menuitem">Mi perfil</a>
                            <button type="button" role="menuitem" class="quality-menu-a11y" @click="userOpen = false; openA11y()">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="7" r="1.6" fill="currentColor" stroke="none"/><path d="M7 10h10M12 10v4m0 0-3 6m3-6 3 6"/></svg>&nbsp;Accesibilidad
                            </button>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" role="menuitem">Cerrar sesión</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main id="contenido" class="quality-content" tabindex="-1">
                <div class="quality-page">
                    @if (session('status'))
                        <div class="pm-flash is-success" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg><span>{{ session('status') }}</span></div>
                    @endif
                    @if (session('error'))
                        <div class="pm-flash is-danger" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><span>{{ session('error') }}</span></div>
                    @endif
                    @if ($errors->any())
                        <div class="pm-flash is-danger" role="alert"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><ul>@foreach ($errors->all() as $mensaje)<li>{{ $mensaje }}</li>@endforeach</ul></div>
                    @endif
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    <button class="quality-overlay" x-cloak x-show="navigationOpen" @click="navigationOpen = false" aria-label="Cerrar navegación"></button>
    <div class="quality-modal" x-cloak x-show="a11yOpen" x-transition.opacity>
        <div class="quality-modal-backdrop" @click="a11yOpen = false"></div>
        <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="a11y-title" x-ref="a11yDialog" tabindex="-1" @keydown.tab="trapFocus($event)">
            <header>
                <div><span class="quality-eyebrow">Preferencias</span><h2 id="a11y-title">Accesibilidad</h2></div>
                <button class="quality-icon-button" type="button" @click="a11yOpen = false" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
            </header>
            <div class="quality-a11y-body">
                <div class="quality-a11y-row">
                    <div><strong>Tamaño de letra</strong><small x-text="fontLabel()"></small></div>
                    <div class="quality-a11y-actions">
                        <button class="quality-icon-button" type="button" @click="stepFont(-1)" :disabled="fontIndex === 0" aria-label="Disminuir tamaño de letra">A&minus;</button>
                        <button class="quality-icon-button" type="button" @click="stepFont(1)" :disabled="fontIndex === fontSizes.length - 1" aria-label="Aumentar tamaño de letra">A+</button>
                    </div>
                </div>
                <div class="quality-a11y-row">
                    <div><strong>Alto contraste</strong><small>Texto más oscuro, bordes más marcados</small></div>
                    <button class="quality-icon-button" type="button" @click="toggleContrast()" :aria-pressed="contrast.toString()" aria-label="Alternar alto contraste"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none"/></svg></button>
                </div>
                <div class="quality-a11y-row">
                    <div><strong>Tema de color</strong><small x-text="dark ? 'Modo oscuro' : 'Modo claro'"></small></div>
                    <button class="quality-icon-button" type="button" @click="toggleTheme()" :aria-pressed="dark.toString()" aria-label="Cambiar tema de color"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg></button>
                </div>
                <button class="quality-reset" type="button" @click="resetA11y()">Restablecer preferencias</button>
            </div>
        </section>
    </div>
    <script>
        function qualityShell() {
            const root = document.documentElement;
            const sizes = ['normal', 'grande', 'xl'];
            return {
                navigationOpen: false, userOpen: false, a11yOpen: false, trigger: null,
                fontSizes: sizes, fontNames: ['Normal', 'Grande (115%)', 'Muy grande (130%)'],
                fontIndex: Math.max(0, sizes.indexOf(root.dataset.fontSize)),
                contrast: root.dataset.contrast === 'alto', dark: root.dataset.theme === 'dark',
                fontLabel() { return this.fontNames[this.fontIndex]; },
                stepFont(delta) { this.fontIndex = Math.min(sizes.length - 1, Math.max(0, this.fontIndex + delta)); root.dataset.fontSize = sizes[this.fontIndex]; localStorage.setItem('portal-font-size', sizes[this.fontIndex]); },
                toggleContrast() { this.contrast = !this.contrast; root.dataset.contrast = this.contrast ? 'alto' : 'normal'; localStorage.setItem('portal-contrast', root.dataset.contrast); },
                toggleTheme() { this.dark = !this.dark; root.dataset.theme = this.dark ? 'dark' : 'light'; localStorage.setItem('portal-theme', root.dataset.theme); },
                resetA11y() { this.fontIndex = 0; this.contrast = false; this.dark = false; root.dataset.fontSize = 'normal'; root.dataset.contrast = 'normal'; root.dataset.theme = 'light'; ['portal-font-size', 'portal-contrast', 'portal-theme'].forEach((k) => localStorage.removeItem(k)); },
                openA11y() { this.trigger = document.activeElement; this.a11yOpen = true; this.$nextTick(() => this.$refs.a11yDialog.focus()); },
                trapFocus(e) { const f = [...this.$refs.a11yDialog.querySelectorAll('button:not([disabled])')]; if (!f.length) return; const first = f[0], last = f[f.length - 1]; if (e.shiftKey && (document.activeElement === first || document.activeElement === this.$refs.a11yDialog)) { e.preventDefault(); last.focus(); } else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } },
                init() { this.$watch('a11yOpen', (open) => { if (!open && this.trigger) { this.trigger.focus(); } }); },
            };
        }
        window.portalToggleTheme = () => { const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = next; localStorage.setItem('portal-theme', next); };
    </script>
    @stack('scripts')
@livewireScriptConfig
</body>
</html>
