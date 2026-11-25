<details class="campana" @click.outside="$el.removeAttribute('open')" x-data>
    <summary aria-label="Notificaciones, {{ $avisosSinLeer }} sin leer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 0 1-6 0v-1"/></svg>
        @if ($avisosSinLeer)<span>{{ $avisosSinLeer > 9 ? '9+' : $avisosSinLeer }}</span>@endif
    </summary>
    <div class="campana-panel">
        <strong>Notificaciones</strong>
        @forelse ($avisosRecientes as $n)
            <a href="{{ route('bienestar.notificaciones.abrir', $n) }}" @class(['sin-leer' => ! $n->leida_en])>
                <b>{{ $n->titulo }}</b><span>{{ $n->mensaje }}</span><small>{{ $n->creada_en->locale('es')->diffForHumans() }}</small>
            </a>
        @empty
            <p>No tienes notificaciones.</p>
        @endforelse
        <a class="campana-todas" href="{{ route('bienestar.notificaciones.index') }}">Ver todas</a>
    </div>
</details>
