@php
    $cuenta = auth()->user();
    $nombreUsuario = $tesisNav['usuario'] ?? ($cuenta?->name ?? '');
    $rolUsuario = $tesisNav['rolEtiqueta'] ?? 'Usuario del portal';
    $iniciales = $tesisNav['iniciales'] ?? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($nombreUsuario, 0, 2));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17224d">
    <title>@yield('titulo', 'Gestión de Tesis') · Portal Gestión Dirección</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-informatica.png') }}">
    <script>
        // Preferencias del portal (las mismas claves que el resto de módulos), aplicadas antes de pintar.
        (() => {
            const d = document.documentElement.dataset;
            let tema = null, contraste = null, letra = null;
            try { tema = window.localStorage.getItem('portal-theme'); contraste = window.localStorage.getItem('portal-contrast'); letra = window.localStorage.getItem('portal-font-size'); } catch (e) { /* sin almacenamiento: valores por defecto */ }
            d.theme = tema === 'dark' ? 'dark' : 'light';
            d.contrast = contraste === 'alto' ? 'alto' : 'normal';
            d.fontSize = ['grande', 'xl'].includes(letra) ? letra : 'normal';
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fira-code:500,600|fira-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @include('modules.tesis-proyecto-tesis.partials.estilos')
    <script>
        // Contenedor del módulo: menú lateral (plegado en escritorio, cajón en celular), menú de usuario y tema.
        window.tzShell = function () {
            const clave = 'tesis-proyecto-tesis.menu';
            const mq = window.matchMedia('(max-width: 900px)');
            let plegado = false;
            try { plegado = window.localStorage.getItem(clave) === 'plegado'; } catch (e) { plegado = false; }

            return {
                plegado, abierto: false, movil: mq.matches, usuarioAbierto: false,
                oscuro: document.documentElement.dataset.theme === 'dark',
                init() { mq.addEventListener('change', (e) => { this.movil = e.matches; this.abierto = false; }); },
                get expandido() { return this.movil ? this.abierto : ! this.plegado; },
                alternar() {
                    if (this.movil) { this.abierto ? this.cerrar() : this.abrir(); return; }
                    this.plegado = ! this.plegado;
                    try { window.localStorage.setItem(clave, this.plegado ? 'plegado' : 'desplegado'); } catch (e) { /* sin almacenamiento: solo esta página */ }
                },
                // Los focos diferidos comprueban el estado: si el usuario ya volvió a abrir o cerrar, no se aplican.
                abrir() { this.abierto = true; this.$nextTick(() => { if (this.abierto) (this.$refs.menu.querySelector('.tz-nav a') ?? this.$refs.menu.querySelector('a'))?.focus(); }); },
                cerrar(devolverFoco = true) {
                    if (! this.abierto) return;
                    this.abierto = false;
                    if (devolverFoco) this.$nextTick(() => { if (! this.abierto) this.$refs.botonMenu?.focus(); });
                },
                // Celular: si el foco sale del cajón hacia otro control de la página (Tab), se cierra.
                salir(e) {
                    const destino = e.relatedTarget;
                    if (this.movil && this.abierto && destino && destino !== this.$refs.botonMenu && ! this.$refs.menu.contains(destino)) this.cerrar(false);
                },
                escape() { if (this.usuarioAbierto) { this.usuarioAbierto = false; this.$refs.botonUsuario?.focus(); return; } this.cerrar(); },
                alternarTema() {
                    this.oscuro = ! this.oscuro;
                    const tema = this.oscuro ? 'dark' : 'light';
                    document.documentElement.dataset.theme = tema;
                    try { window.localStorage.setItem('portal-theme', tema); } catch (e) { /* sin almacenamiento: solo esta página */ }
                },
            };
        };
    </script>
</head>
<body class="tz-body">
    <div class="tz" id="tz-raiz" x-data="tzShell()" :class="{ 'is-plegado': plegado, 'is-abierto': movil && abierto }" @keydown.escape.window="escape()">
        {{-- Aplica el estado guardado del menú antes de pintar, para que no "salte" al cargar. --}}
        <script>try { if (window.localStorage.getItem('tesis-proyecto-tesis.menu') === 'plegado') document.getElementById('tz-raiz').classList.add('is-plegado'); } catch (e) {}</script>
        <a class="skip-link" href="#tz-contenido">Saltar al contenido</a>

        <aside class="tz-side" id="tz-menu" x-ref="menu" aria-label="Menú del módulo" @focusout="salir($event)">
            <a class="tz-brand" href="{{ route('tesis-proyecto-tesis.index') }}" aria-label="Gestión de Tesis · Escuela de Ingeniería Informática">
                <span class="tz-brand-mark" aria-hidden="true">PT</span>
                <span class="tz-brand-text" aria-hidden="true"><strong>Gestión de Tesis</strong><small>Escuela de Ingeniería Informática</small></span>
            </a>

            <nav class="tz-nav" aria-label="Opciones del módulo">
                @foreach ($tesisNav['menu'] ?? [] as $item)
                    @php($nombre = $item['etiqueta'].($item['insignia'] > 0 ? ', '.$item['insignia'].' '.($item['insignia'] === 1 ? 'pendiente' : 'pendientes') : ''))
                    <a href="{{ $item['url'] }}" aria-label="{{ $nombre }}" @if (($tesisNav['activo'] ?? '') === $item['clave']) aria-current="page" @endif>
                        @include('modules.tesis-proyecto-tesis.partials.icono-menu', ['clave' => $item['clave']])
                        <span class="tz-side-label" aria-hidden="true">{{ $item['etiqueta'] }}</span>
                        @if ($item['insignia'] > 0)
                            <span class="tz-count" aria-hidden="true">{{ $item['insignia'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <a class="tz-module-switch" href="{{ route('dashboard') }}" aria-label="Cambiar de módulo">
                <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                <span class="tz-side-label" aria-hidden="true">Cambiar de módulo</span>
            </a>
        </aside>
        <div class="tz-velo" x-show="movil && abierto" x-cloak x-transition.opacity @click="cerrar()" aria-hidden="true"></div>

        <div class="tz-main-shell">
            <header class="tz-topbar">
                <button type="button" class="tz-icon-btn" x-ref="botonMenu" @click="alternar()"
                        aria-controls="tz-menu" aria-expanded="true" aria-label="Contraer menú"
                        :aria-expanded="expandido.toString()" :aria-label="expandido ? 'Contraer menú' : 'Expandir menú'">
                    <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <nav class="tz-breadcrumb" aria-label="Ruta actual">
                    <a href="{{ route('dashboard') }}">Portal</a>
                    <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg>
                    <a href="{{ route('tesis-proyecto-tesis.index') }}" aria-current="location">Tesis y Proyecto de Tesis</a>
                </nav>

                <div class="tz-topbar-actions">
                    @isset($tesisNav)
                        <div class="tz-bell-wrap" x-data="{ abierto: false }" @keydown.escape.window="abierto = false" @click.outside="abierto = false">
                            <button type="button" class="tz-icon-btn tz-bell" @click="abierto = !abierto" :aria-expanded="abierto.toString()" aria-expanded="false" aria-controls="tz-avisos"
                                    aria-label="Notificaciones{{ $tesisNav['noLeidas'] ? ', '.$tesisNav['noLeidas'].' sin leer' : '' }}">
                                <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                                @if ($tesisNav['noLeidas'] > 0)
                                    <span class="tz-bell-count" aria-hidden="true">{{ $tesisNav['noLeidas'] }}</span>
                                @endif
                            </button>
                            <div id="tz-avisos" class="tz-pop" x-show="abierto" x-cloak x-transition.opacity>
                                <header>
                                    <strong>Notificaciones</strong>
                                    @if ($tesisNav['noLeidas'] > 0)
                                        <form method="POST" action="{{ route('tesis-proyecto-tesis.notificaciones.leer') }}">
                                            @csrf
                                            <button class="tz-link-btn" type="submit">Marcar todas como leídas</button>
                                        </form>
                                    @endif
                                </header>
                                <ul>
                                    @forelse ($tesisNav['avisos'] as $aviso)
                                        <li class="{{ $aviso['leida'] ? '' : 'is-new' }}">
                                            <form method="POST" action="{{ route('tesis-proyecto-tesis.notificaciones.abrir') }}">
                                                @csrf
                                                <input type="hidden" name="n" value="{{ $aviso['token'] }}">
                                                <button type="submit" class="tz-aviso">
                                                    <strong>{{ $aviso['titulo'] }}</strong>
                                                    <span class="tz-muted">{{ $aviso['mensaje'] }}</span>
                                                    <span class="tz-muted tz-small">{{ $aviso['fecha']->diffForHumans() }}{{ $aviso['leida'] ? '' : ' · sin leer' }} · Ir a la acción →</span>
                                                </button>
                                            </form>
                                        </li>
                                    @empty
                                        <li class="tz-muted">No tienes notificaciones.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @endisset

                    <button type="button" class="tz-icon-btn tz-theme-btn" id="tz-tema" @click="alternarTema()"
                            aria-label="Cambiar a tema oscuro" :aria-label="oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'">
                        {{-- Luna en tema claro (pasar a oscuro); sol en tema oscuro (pasar a claro). --}}
                        <svg class="tz-i tz-icon-moon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                        <svg class="tz-i tz-icon-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/></svg>
                    </button>

                    @if ($cuenta)
                        <div class="tz-user-menu" @click.outside="usuarioAbierto = false">
                            <button type="button" class="tz-user-trigger" x-ref="botonUsuario" @click="usuarioAbierto = !usuarioAbierto"
                                    aria-expanded="false" :aria-expanded="usuarioAbierto.toString()" aria-controls="tz-usuario">
                                <span class="tz-avatar" aria-hidden="true">{{ $iniciales }}</span>
                                <span class="tz-user-text"><strong>{{ $nombreUsuario }}</strong><small>{{ $rolUsuario }}</small></span>
                                <span class="sr-only">Menú de usuario</span>
                                <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div id="tz-usuario" class="tz-user-panel" x-show="usuarioAbierto" x-cloak x-transition.opacity>
                                <p class="tz-user-panel-head"><strong>{{ $nombreUsuario }}</strong><small>{{ $rolUsuario }}</small></p>
                                <a href="{{ route('profile.edit') }}">Mi perfil</a>
                                @if ($tesisNav['cambiarRol'] ?? false)
                                    <a href="{{ route('tesis-proyecto-tesis.index', ['elegir' => 1]) }}">Cambiar rol</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit">Cerrar sesión</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            <main id="tz-contenido" class="tz-main" tabindex="-1">
                <div class="tz-page">
                    @if (session('status'))
                        <div class="tz-alert is-success" role="status">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="tz-alert is-danger" role="alert">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="tz-alert is-danger" role="alert">
                            <div>
                                <strong>Revisa los datos del formulario.</strong>
                                <ul>
                                    @foreach ($errors->all() as $mensaje)
                                        <li>{{ $mensaje }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @yield('contenido')
                </div>
            </main>
        </div>
    </div>
    @livewireScriptConfig
</body>
</html>
