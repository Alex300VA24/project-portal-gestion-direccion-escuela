{{-- Resumen de una verificación ya registrada: requiere $registrada (Revision con detalles) y $criterios --}}
@php($tzPorCriterio = $registrada->detalles->keyBy('id_criterio'))
<section class="tz-panel">
    <div class="tz-panel-head">
        <h2>Verificación registrada</h2>
        <span class="tz-status is-{{ $registrada->conforme ? 'success' : ($registrada->referencial ? 'info' : 'warning') }}">
            {{ $registrada->referencial ? 'Referencial' : ($registrada->conforme ? 'Conforme' : 'Con observaciones') }} · {{ $registrada->revisado_en->format('d/m/Y H:i') }}
        </span>
    </div>
    @foreach ($criterios as $c)
        @php($d = $tzPorCriterio->get($c->id_criterio))
        <div class="tz-rubric-row">
            <div class="tz-rubric-line">
                <b>{{ $c->orden }}</b>
                <span>{{ $c->descripcion }}</span>
                @if ($d && $d->no_aplica)
                    <span class="tz-status is-neutral">No aplica</span>
                @elseif ($d && $d->cumple)
                    <span class="tz-status is-success">✓ Cumple</span>
                @else
                    <span class="tz-status is-danger">✗ No cumple</span>
                @endif
            </div>
            @foreach ($registrada->observaciones->where('id_criterio', $c->id_criterio) as $o)
                <div class="tz-obsbox"><span class="tz-small"><strong>{{ $o->estado }}:</strong> {{ $o->descripcion }}</span></div>
            @endforeach
        </div>
    @endforeach
    @if ($registrada->comentario)
        <div class="tz-panel-body"><strong>Comentario:</strong> {{ $registrada->comentario }}</div>
    @endif
</section>
