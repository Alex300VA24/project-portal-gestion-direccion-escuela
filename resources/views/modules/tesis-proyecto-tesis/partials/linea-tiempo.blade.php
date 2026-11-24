{{-- Historial de estados: requiere $historial --}}
@if (empty($historial))
    <p class="tz-muted">Sin movimientos registrados.</p>
@else
    <ol class="tz-timeline">
        @foreach ($historial as $h)
            <li>
                <strong>{{ \App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($h['estado']) }}</strong>
                <div class="tz-muted tz-small">{{ $h['fecha']?->format('d/m/Y H:i') }}</div>
                @if ($h['comentario'])
                    <div class="tz-small">{{ $h['comentario'] }}</div>
                @endif
            </li>
        @endforeach
    </ol>
@endif
