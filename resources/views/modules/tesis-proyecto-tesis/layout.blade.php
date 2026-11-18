<x-app-layout>
    @include('modules.tesis-proyecto-tesis.partials.estilos')

    @php($tieneMenu = ! empty($tesisNav['menu'] ?? []))

    <script>
        // Menú lateral: desplegado (íconos + nombres) o plegado (solo íconos) en escritorio; cajón superpuesto en celular.
        window.tzMenu = function () {
            const clave = 'tesis-proyecto-tesis.menu';
            const mq = window.matchMedia('(max-width: 900px)');
            let plegado = false;
            try { plegado = window.localStorage.getItem(clave) === 'plegado'; } catch (e) { plegado = false; }

            return {
                plegado, abierto: false, movil: mq.matches,
                init() { mq.addEventListener('change', (e) => { this.movil = e.matches; this.abierto = false; }); },
                get expandido() { return this.movil ? this.abierto : ! this.plegado; },
                alternar() {
                    if (this.movil) { this.abierto ? this.cerrar() : this.abrir(); return; }
                    this.plegado = ! this.plegado;
                    try { window.localStorage.setItem(clave, this.plegado ? 'plegado' : 'desplegado'); } catch (e) { /* sin almacenamiento: solo esta página */ }
                },
                // Los focos diferidos comprueban el estado: si el usuario ya volvió a abrir o cerrar, no se aplican.
                abrir() { this.abierto = true; this.$nextTick(() => { if (this.abierto) this.$refs.menu.querySelector('a')?.focus(); }); },
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
            };
        };
    </script>

    <div class="tz" id="tz-raiz" x-data="tzMenu()" :class="{ 'is-plegado': plegado, 'is-abierto': movil && abierto }" @keydown.escape.window="cerrar()">
        {{-- Aplica el estado guardado antes de pintar, para que el menú no "salte" al cargar. --}}
        <script>try { if (window.localStorage.getItem('tesis-proyecto-tesis.menu') === 'plegado') document.getElementById('tz-raiz').classList.add('is-plegado'); } catch (e) {}</script>
        <a href="#tz-contenido" class="sr-only">Saltar al contenido</a>

        <div class="tz-band">
            <div class="tz-band-inner">
                @if ($tieneMenu)
                    <button type="button" class="tz-menu-btn" x-ref="botonMenu" @click="alternar()"
                            aria-controls="tz-menu" aria-expanded="true" aria-label="Contraer menú"
                            :aria-expanded="expandido.toString()" :aria-label="expandido ? 'Contraer menú' : 'Expandir menú'">
                        <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                @endif
                <a class="tz-brand" href="{{ route('tesis-proyecto-tesis.index') }}">
                    <span class="tz-brand-mark" aria-hidden="true">PT</span>
                    <span><strong>Gestión de Tesis</strong><span>Escuela de Ingeniería Informática</span></span>
                </a>

                @isset($tesisNav)
                    <div class="tz-band-actions">
                        <div class="relative" x-data="{ abierto: false }" @keydown.escape.window="abierto = false" @click.outside="abierto = false" style="position: relative">
                            <button type="button" class="tz-bell" @click="abierto = !abierto" :aria-expanded="abierto.toString()" aria-controls="tz-avisos"
                                    aria-label="Notificaciones{{ $tesisNav['noLeidas'] ? ', '.$tesisNav['noLeidas'].' sin leer' : '' }}">
                                <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
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

                        <div class="tz-user">
                            <span class="tz-avatar" aria-hidden="true">{{ $tesisNav['iniciales'] }}</span>
                            <div>
                                <strong>{{ $tesisNav['usuario'] }}</strong>
                                <small>
                                    {{ $tesisNav['rolEtiqueta'] }}
                                    @if ($tesisNav['cambiarRol'])
                                        · <a href="{{ route('tesis-proyecto-tesis.index') }}">Cambiar rol</a>
                                    @endif
                                </small>
                            </div>
                        </div>
                    </div>
                @endisset
            </div>
        </div>

        <div class="tz-shell">
            @if ($tieneMenu)
                <div class="tz-velo" x-show="movil && abierto" x-cloak x-transition.opacity @click="cerrar()" aria-hidden="true"></div>
            @endif
            <aside class="tz-side" id="tz-menu" x-ref="menu" aria-label="Menú del módulo" @focusout="salir($event)">
                <nav>
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
            </aside>

            <main id="tz-contenido" class="tz-main">
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
</x-app-layout>
