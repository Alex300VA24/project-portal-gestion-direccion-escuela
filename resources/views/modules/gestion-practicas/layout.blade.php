{{--
    Marco del módulo Gestión de Prácticas. Sigue docs/DESIGN.md: misma estructura que el módulo Calidad
    (barra lateral navy, barra superior, menú de usuario y panel de accesibilidad) y las mismas preferencias
    de tema, contraste y tamaño de texto guardadas en localStorage.
--}}
@php
    use App\Modules\GestionPracticas\Support\Menu;

    $opciones = Menu::para(auth()->user());
    $rol = (string) \Illuminate\Support\Str::of(auth()->user()->getRoleNames()->first() ?? 'Usuario institucional')
        ->replace('practicas_', '')->replace('_', ' ')->ucfirst();
@endphp
<!DOCTYPE html>
<html lang="es" x-data="practicasShell()" :class="{ 'overflow-hidden': navigationOpen || a11yOpen }" @keydown.escape.window="a11yOpen = false">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17224d">
    <title>@yield('title', 'Gestión de Prácticas') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <script>
        (() => { const d = document.documentElement.dataset, s = localStorage; d.theme = s.getItem('portal-theme') || 'light'; d.contrast = s.getItem('portal-contrast') || 'normal'; d.fontSize = s.getItem('portal-font-size') || 'normal'; })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {{-- Complementos del módulo (prefijo gp-): formularios y avisos con las medidas de DESIGN.md §4, pero con los tokens --quality-* para respetar tema oscuro y alto contraste. --}}
    <style>
        .quality-body .gp-aviso { margin: 0 0 20px; }
        .quality-body .gp-aviso ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; }
        .quality-page-heading > div:first-child { flex: 1 1 auto; min-width: min(100%, 480px); }
        .quality-page-heading > .gp-actions { align-items: center; }
        .gp-body { padding: 20px 22px; }
        .gp-body > :first-child { margin-top: 0; }
        .gp-body > :last-child { margin-bottom: 0; }
        .gp-text { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
        .gp-lead { margin: 0 0 4px; color: var(--quality-muted); font-size: 14px; }
        .gp-stack { display: grid; gap: 18px; }
        .gp-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .gp-row-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 8px; }
        .gp-row-actions form { display: contents; }
        .gp-form { display: grid; gap: 18px; padding: 22px; }
        .gp-form.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gp-span { grid-column: 1 / -1; }
        .gp-field { display: grid; gap: 8px; align-content: start; }
        .gp-field > label, .gp-field > legend, .gp-label { color: var(--quality-ink); font-size: 14px; font-weight: 700; }
        .gp-field small { color: var(--quality-muted); font-size: 13px; }
        .gp-control { width: 100%; min-height: 56px; padding: 12px 14px; color: var(--quality-ink); background: var(--quality-surface); border: 1px solid #bbc2ce; border-radius: 10px; outline: 0; font: inherit; transition: border-color 160ms var(--portal-ease), box-shadow 160ms var(--portal-ease); }
        textarea.gp-control { min-height: 112px; resize: vertical; }
        .gp-control::placeholder { color: var(--quality-muted); opacity: 1; }
        .gp-control:hover { border-color: #7c8598; }
        .gp-control:focus { border-color: var(--portal-green); box-shadow: 0 0 0 3px rgba(13, 122, 80, .15); }
        .gp-control[aria-invalid="true"] { border-color: #a11d12; }
        .gp-control:disabled { color: var(--quality-muted); background: var(--quality-surface-alt); cursor: not-allowed; }
        input[type="file"].gp-control { padding: 14px; }
        .gp-error { margin: 0; color: #8e1a11; font-size: 13px; font-weight: 600; }
        .gp-grade { display: grid; grid-template-columns: minmax(0, 1fr) 140px; align-items: center; gap: 12px 16px; }
        .gp-grade .gp-control { min-height: 48px; text-align: right; font-family: 'Fira Code', monospace; }
        .gp-compact .quality-compact-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); column-gap: 22px; padding-bottom: 4px; }
        .gp-compact .quality-compact-list article { border-bottom: 0; }
        .gp-compact .quality-compact-list article > div { min-width: 0; }
        .gp-compact h3 { margin: 18px 0 6px; font-size: 14px; }
        .gp-compact .gp-body > h3:first-child { margin-top: 0; }
        .gp-compact .quality-compact-list small { margin: 0 0 2px; }
        .gp-compact .quality-compact-list strong { font-size: 14px; font-weight: 500; overflow-wrap: anywhere; }
        .gp-timeline { margin: 0; padding: 0; list-style: none; }
        .gp-timeline > li { padding: 14px 22px; border-bottom: 1px solid var(--quality-border); }
        .gp-timeline > li:last-child { border: 0; }
        .gp-timeline > li.is-pending { border-left: 4px solid var(--portal-danger); padding-left: 18px; }
        .gp-timeline small { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; margin-top: 6px; color: var(--quality-muted); font-size: 12px; }
        .gp-timeline p { margin: 0; white-space: pre-line; }
        button.institutional-primary, button.institutional-secondary { cursor: pointer; font: inherit; font-weight: 700; }
        button.institutional-primary { border: 0; }
        .institutional-primary, .institutional-secondary { min-height: 46px; }
        .institutional-secondary { color: var(--quality-navy); background: var(--quality-surface); border-color: #bbc2ce; }
        .institutional-secondary.is-danger { color: var(--portal-danger); border-color: var(--portal-danger); }
        button.quality-open-link { color: var(--quality-green); background: transparent; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; }
        button.quality-open-link.is-danger { color: var(--portal-danger); }
        button.quality-open-link:hover { border-color: currentColor; }
        a.gp-link { color: var(--quality-green); text-decoration: underline; text-underline-offset: 4px; text-decoration-thickness: 1px; overflow-wrap: anywhere; }
        .quality-indicator-row small { display: block; }
        .quality-indicator-row .quality-code + strong { margin-top: 7px; }
        html[data-theme="dark"] .gp-control { border-color: #8390aa; }
        html[data-theme="dark"] .gp-error, html[data-theme="dark"] .institutional-secondary.is-danger, html[data-theme="dark"] button.quality-open-link.is-danger { color: #ff9d92; }
        html[data-theme="dark"] .institutional-secondary.is-danger { border-color: #ff9d92; }
        html[data-contrast="alto"] .gp-control { border: 2px solid var(--quality-border); }
        @media (max-width: 760px) {
            .gp-form.is-two { grid-template-columns: 1fr; }
            .gp-grade { grid-template-columns: 1fr; }
            .gp-row-actions { justify-content: flex-start; }
        }
        @media (prefers-reduced-motion: reduce) { .gp-control { transition: none; } }
    </style>
</head>
<body class="quality-body">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>
    <div class="quality-shell">
        <aside id="quality-navigation" class="quality-sidebar" :class="{ 'is-open': navigationOpen }" aria-label="Navegación de Gestión de Prácticas">
            <a class="quality-brand" href="{{ route('gestion-practicas.panel') }}">
                <img class="quality-brand-mark" src="{{ asset('img/logo-informatica.png') }}" alt="">
                <span><strong>Prácticas</strong><small>Portal Gestión Dirección</small></span>
            </a>
            <nav class="quality-nav">
                @foreach ($opciones as $opcion)
                    @php($activa = request()->routeIs($opcion['patron']))
                    <a href="{{ route($opcion['ruta']) }}" @class(['quality-nav-link', 'is-active' => $activa]) @if ($activa) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true">{!! $opcion['icono'] !!}</svg><span>{{ $opcion['texto'] }}</span>
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
                    <a href="{{ route('dashboard') }}">Portal</a><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><a href="{{ route('gestion-practicas.panel') }}">Gestión de Prácticas</a>
                    @hasSection('page-label')<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg><strong aria-current="page">@yield('page-label')</strong>@endif
                </nav>
                <div class="quality-topbar-actions">
                    <button class="quality-icon-button" type="button" onclick="window.portalToggleTheme()" aria-label="Cambiar tema de color">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    </button>
                    <div class="quality-user-menu" @click.outside="userOpen = false">
                        <button class="quality-user-trigger" type="button" @click="userOpen = !userOpen" :aria-expanded="userOpen.toString()" aria-haspopup="menu">
                            <span class="quality-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 2)) }}</span>
                            <span><strong>{{ auth()->user()->name }}</strong><small>{{ $rol }}</small></span>
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
                    @include('modules.gestion-practicas.partials.mensajes')
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
        function practicasShell() {
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
@livewireScriptConfig
</body>
</html>
