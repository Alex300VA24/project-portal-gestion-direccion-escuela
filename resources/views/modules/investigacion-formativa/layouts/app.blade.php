<!DOCTYPE html>
<html lang="es" x-data="ifShell" :class="{ 'overflow-hidden': navigationOpen || a11yOpen }" @keydown.escape.window="a11yOpen = false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17224d">
    <title>@yield('title', 'Investigación Formativa') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <script>
        (() => { const d = document.documentElement.dataset, s = localStorage; d.theme = s.getItem('portal-theme') || 'light'; d.contrast = s.getItem('portal-contrast') || 'normal'; d.fontSize = s.getItem('portal-font-size') || 'normal'; })();
        document.addEventListener('alpine:init', () => {
            window.Alpine.data('ifShell', () => {
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
            });
        });
        window.portalToggleTheme = () => { const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = next; localStorage.setItem('portal-theme', next); };
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/css/investigacion-formativa.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="portal-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="if-shell">
        <aside id="if-navigation" class="if-sidebar" :class="{ 'is-open': navigationOpen }" aria-label="Navegación de Investigación Formativa">
            <a class="if-brand" href="{{ route('investigacion-formativa.index') }}">
                <span style="display:grid;width:42px;height:42px;place-items:center;color:#17224d;background:#fff;border-radius:11px;font:600 13px 'Fira Code', monospace;letter-spacing:-.03em;">IF</span>
                <span><strong>Investigación Formativa</strong><small>Portal Gestión Dirección</small></span>
            </a>
            <nav class="if-nav">
                <a href="{{ route('investigacion-formativa.index') }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.index')]) @if(request()->routeIs('investigacion-formativa.index')) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/></svg><span>Inicio</span>
                </a>
                @if (auth()->user()->hasRole('director_escuela'))
                    <a href="{{ route('investigacion-formativa.director.index') }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.director.index', 'investigacion-formativa.director.resultados')]) @if(request()->routeIs('investigacion-formativa.director.index', 'investigacion-formativa.director.resultados')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-8m5 8V7"/><path d="m4 8 5-3 5 5 5-4"/></svg><span>Dirección</span>
                    </a>
                    <a href="{{ route('investigacion-formativa.director.cursos.index') }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.director.cursos.*', 'investigacion-formativa.director.encuestas.*', 'investigacion-formativa.director.secciones.habilitacion')]) @if(request()->routeIs('investigacion-formativa.director.cursos.*', 'investigacion-formativa.director.encuestas.*', 'investigacion-formativa.director.secciones.habilitacion')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h7"/></svg><span>Cursos y habilitación</span>
                    </a>
                @endif
                @if (auth()->user()->hasRole('profesor'))
                    <a href="{{ route('investigacion-formativa.docente.cursos.index') }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.docente.cursos.index')]) @if(request()->routeIs('investigacion-formativa.docente.cursos.index')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Docencia</span>
                    </a>
                    <a href="{{ route('investigacion-formativa.docente.grupos.index', request()->route('seccion') ?? request()->integer('seccion') ?? 0) }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.docente.grupos.*', 'investigacion-formativa.docente.titulos.*', 'investigacion-formativa.docente.evaluaciones.*')]) @if(request()->routeIs('investigacion-formativa.docente.grupos.*', 'investigacion-formativa.docente.titulos.*', 'investigacion-formativa.docente.evaluaciones.*')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><circle cx="19" cy="8" r="2.4"/></svg><span>Grupos y avances</span>
                    </a>
                @endif
                @if (auth()->user()->hasRole('alumno'))
                    <a href="{{ route('investigacion-formativa.alumno.cursos.index') }}" @class(['if-nav-link', 'is-active' => request()->routeIs('investigacion-formativa.alumno.cursos.index')]) @if(request()->routeIs('investigacion-formativa.alumno.cursos.index')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m22 10-10-5-10 5 10 5 10-5z"/><path d="M6 12v5c0 1 2 2.5 6 2.5s6-1.5 6-2.5v-5"/><path d="M22 10v6"/></svg><span>Estudiante</span>
                    </a>
                @endif
            </nav>
            <a class="if-module-switch" href="{{ route('dashboard') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg><span>Cambiar de módulo</span>
            </a>
        </aside>
        <div class="if-main-shell">
            <header class="if-topbar">
                <button class="if-icon-button if-menu-button" type="button" @click="navigationOpen = !navigationOpen" :aria-expanded="navigationOpen.toString()" aria-controls="if-navigation" aria-label="Abrir navegación">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <nav class="if-breadcrumb" aria-label="Ruta actual">
                    <a href="{{ route('dashboard') }}">Portal</a><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><a href="{{ route('investigacion-formativa.index') }}">Investigación Formativa</a>
                    @hasSection('page-label')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><strong aria-current="page">@yield('page-label')</strong>@endif
                </nav>
                <div class="if-topbar-actions">
                    <button class="if-icon-button" type="button" onclick="window.portalToggleTheme()" aria-label="Cambiar tema de color">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    </button>
                    <div class="if-user-menu" @click.outside="userOpen = false">
                        <button class="if-user-trigger" type="button" @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu">
                            <span class="if-avatar" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
                            <span><strong>{{ auth()->user()->name }}</strong><small>{{ Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')->replace('_', ' ') }}</small></span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="if-user-panel" x-cloak x-show="userOpen" x-transition role="menu">
                            <a href="{{ route('profile.edit') }}" role="menuitem">Mi perfil</a>
                            <button type="button" role="menuitem" class="if-menu-a11y" @click="userOpen = false; openA11y()">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="7" r="1.6" fill="currentColor" stroke="none"/><path d="M7 10h10M12 10v4m0 0-3 6m3-6 3 6"/></svg>&nbsp;Accesibilidad
                            </button>
                            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" role="menuitem">Cerrar sesión</button></form>
                        </div>
                    </div>
                </div>
            </header>
            <main id="contenido" class="if-content" tabindex="-1">
                <div class="if-page">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    <button class="if-overlay" x-cloak x-show="navigationOpen" @click="navigationOpen = false" aria-label="Cerrar navegación"></button>
    <div class="quality-modal" x-cloak x-show="a11yOpen" x-transition.opacity>
        <div class="quality-modal-backdrop" @click="a11yOpen = false"></div>
        <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="if-a11y-title" x-ref="a11yDialog" tabindex="-1" @keydown.tab="trapFocus($event)">
            <header>
                <div><span class="quality-eyebrow">Preferencias</span><h2 id="if-a11y-title">Accesibilidad</h2></div>
                <button class="if-icon-button" type="button" @click="a11yOpen = false" aria-label="Cerrar"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
            </header>
            <div class="quality-a11y-body">
                <div class="quality-a11y-row">
                    <div><strong>Tamaño de letra</strong><small x-text="fontLabel()"></small></div>
                    <div class="quality-a11y-actions">
                        <button class="if-icon-button" type="button" @click="stepFont(-1)" :disabled="fontIndex === 0" aria-label="Disminuir tamaño de letra">A&minus;</button>
                        <button class="if-icon-button" type="button" @click="stepFont(1)" :disabled="fontIndex === fontSizes.length - 1" aria-label="Aumentar tamaño de letra">A+</button>
                    </div>
                </div>
                <div class="quality-a11y-row">
                    <div><strong>Alto contraste</strong><small>Texto más oscuro, bordes más marcados</small></div>
                    <button class="if-icon-button" type="button" @click="toggleContrast()" :aria-pressed="contrast.toString()" aria-label="Alternar alto contraste"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none"/></svg></button>
                </div>
                <div class="quality-a11y-row">
                    <div><strong>Tema de color</strong><small x-text="dark ? 'Modo oscuro' : 'Modo claro'"></small></div>
                    <button class="if-icon-button" type="button" @click="toggleTheme()" :aria-pressed="dark.toString()" aria-label="Cambiar tema de color"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg></button>
                </div>
                <button class="quality-reset" type="button" @click="resetA11y()">Restablecer preferencias</button>
            </div>
        </section>
    </div>
    @livewireScriptConfig
</body>
</html>