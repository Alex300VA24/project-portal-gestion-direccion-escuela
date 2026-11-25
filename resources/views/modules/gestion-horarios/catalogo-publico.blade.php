@component('modules.gestion-horarios.layout-publico')
    <section class="quality-page gh-ui" style="max-width:60rem;padding:2rem 1rem">
    @include('modules.gestion-horarios.ui-estilos')
        <nav aria-label="Ruta actual"><a href="{{ route('gestion-horarios.index') }}">Gestión de Horarios</a> / Publicaciones</nav>
        <header style="margin:1.25rem 0">
            <h1 style="font-size:clamp(1.8rem,4vw,2.75rem);font-weight:700">Horarios publicados</h1>
            <p>Consulte las versiones publicadas por la Escuela. El detalle semanal está disponible con su cuenta del portal.</p>
        </header>
        @if ($horarios === null)
            <p class="quality-alert" role="alert">No se pudieron consultar las publicaciones. Vuelva a intentarlo.</p>
        @elseif ($horarios->isEmpty())
            <p class="quality-empty" role="status">Todavía no hay horarios publicados.</p>
        @else
            <ul class="quality-panel" style="padding:1rem">
                @foreach ($horarios as $h)
                    <li style="padding:.75rem;border-bottom:1px solid #d7dcd5">
                        <strong>{{ $h->ambito }} · versión {{ $h->version }}</strong>
                        <p>Periodo institucional {{ $h->id_periodo }} · Publicado el {{ \Illuminate\Support\Carbon::parse($h->publicado_en)->timezone('America/Lima')->format('d/m/Y') }}</p>
                    </li>
                @endforeach
            </ul>
            {{ $horarios->links() }}
        @endif
        <p style="margin-top:1rem"><a class="quality-open-link" href="{{ route('gestion-horarios.consulta-publicados') }}">Consultar detalle semanal</a></p>
    </section>
@endcomponent
