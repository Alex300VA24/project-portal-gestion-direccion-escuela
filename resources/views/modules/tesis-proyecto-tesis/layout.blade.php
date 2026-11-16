<x-app-layout>
    @include('modules.tesis-proyecto-tesis.partials.estilos')

    <div class="tz">
        <a href="#tz-contenido" class="sr-only">Saltar al contenido</a>

        <div class="tz-band">
            <div class="tz-band-inner">
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
                                            <strong>{{ $aviso['titulo'] }}</strong>
                                            <div class="tz-muted">{{ $aviso['mensaje'] }}</div>
                                            <div class="tz-muted tz-small">{{ $aviso['fecha']->diffForHumans() }}</div>
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
            <aside class="tz-side" aria-label="Menú del módulo">
                <nav>
                    @foreach ($tesisNav['menu'] ?? [] as $item)
                        <a href="{{ $item['url'] }}" @if (($tesisNav['activo'] ?? '') === $item['clave']) aria-current="page" @endif>
                            <span>{{ $item['etiqueta'] }}</span>
                            @if ($item['insignia'] > 0)
                                <span class="tz-count">{{ $item['insignia'] }}</span>
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
