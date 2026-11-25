<section class="card-unt p-5">
    <h2 class="subtitulo">Historial de estados</h2>
    <ol class="historial">
        @foreach ($solicitud->historial as $h)
            <li><b>{{ \App\Modules\Bienestar\Support\EstadoSolicitud::texto($h->estado_nuevo) }}</b><small>{{ $h->cambiado_en->setTimezone('America/Lima')->format('d/m/Y H:i') }}</small></li>
        @endforeach
    </ol>
</section>
