{{-- Detalle del formato F1 (SeguimientoService::detalleF1): requiere $f1. Se muestra al estudiante, al asesor y al Director. --}}
@php($soles = fn ($n) => 'S/ '.number_format((float) $n, 2, '.', ','))
<section class="tz-panel" aria-labelledby="f1-generales">
    <div class="tz-panel-head"><h2 id="f1-generales">Datos de la investigación (F1)</h2></div>
    <div class="tz-panel-body">
        <dl class="tz-facts" style="margin-top: 0">
            @foreach ($f1['generales'] as $dato => $valor)
                <div><dt>{{ $dato }}</dt><dd>{{ $valor ?: '—' }}</dd></div>
            @endforeach
        </dl>
        @if ($f1['ayudaFinanciamiento'])
            <p class="tz-small tz-muted" style="margin-top: 10px">{{ $f1['ayudaFinanciamiento'] }}</p>
        @endif
    </div>
</section>

<section class="tz-panel" aria-labelledby="f1-cronograma">
    <div class="tz-panel-head"><h2 id="f1-cronograma">Cronograma de actividades</h2></div>
    @if ($f1['actividades'] === [])
        @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin actividades registradas'])
    @else
        <div class="tz-table-wrap">
            <table class="tz-table">
                <thead><tr><th scope="col">Actividad</th><th scope="col">Inicio</th><th scope="col">Término</th><th scope="col">Dedicación</th></tr></thead>
                <tbody>
                    @foreach ($f1['actividades'] as $a)
                        <tr>
                            <td>{{ $a->descripcion }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($a->fecha_inicio)->format('d/m/Y') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($a->fecha_fin)->format('d/m/Y') }}</td>
                            <td>{{ $a->horas_semanales ? $a->horas_semanales.' h/sem.' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="tz-panel" aria-labelledby="f1-presupuesto">
    <div class="tz-panel-head">
        <h2 id="f1-presupuesto">Recursos y presupuesto</h2>
        <span class="tz-muted tz-small">Total general: <strong>{{ $soles($f1['totales']['General']) }}</strong></span>
    </div>
    <div class="tz-panel-body">
        <div class="tz-personal">
            <strong>Personal</strong> <span class="tz-muted tz-small">(sin costo)</span>
            <ul>@foreach ($f1['personal'] as $persona) <li>{{ $persona }}</li> @endforeach</ul>
        </div>
    </div>
    @if ($f1['recursos'] === [])
        @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin recursos registrados'])
    @else
        <div class="tz-table-wrap">
            <table class="tz-table">
                <thead><tr><th scope="col">Tipo</th><th scope="col">Descripción</th><th scope="col">Clasificador</th><th scope="col">Unidad</th><th scope="col">Cantidad</th><th scope="col">Valor unit.</th><th scope="col">Subtotal</th></tr></thead>
                <tbody>
                    @foreach ($f1['recursos'] as $r)
                        <tr>
                            <td>{{ $r['tipo'] }}</td>
                            <td>{{ $r['descripcion'] }}</td>
                            <td><span class="tz-code">{{ $r['codigo'] ?? '—' }}</span>@if ($r['concepto'])<br><span class="tz-small tz-muted">{{ $r['concepto'] }}</span>@endif</td>
                            <td>{{ $r['unidad'] ?? '—' }}</td>
                            <td class="tz-num">{{ $r['cantidad'] !== null ? rtrim(rtrim(number_format($r['cantidad'], 2, '.', ','), '0'), '.') : '—' }}</td>
                            <td class="tz-num">{{ $r['costo'] !== null ? $soles($r['costo']) : '—' }}</td>
                            <td class="tz-num">{{ $soles($r['subtotal']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th scope="row" colspan="6">Total de bienes</th><td class="tz-num">{{ $soles($f1['totales']['Bienes']) }}</td></tr>
                    <tr><th scope="row" colspan="6">Total de servicios</th><td class="tz-num">{{ $soles($f1['totales']['Servicios']) }}</td></tr>
                    <tr><th scope="row" colspan="6">Total general</th><td class="tz-num"><strong>{{ $soles($f1['totales']['General']) }}</strong></td></tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>

@if ($f1['colaboradores'] !== [])
    <section class="tz-panel" aria-labelledby="f1-colaboradores">
        <div class="tz-panel-head"><h2 id="f1-colaboradores">Colaboradores</h2></div>
        <ul class="tz-list">
            @foreach ($f1['colaboradores'] as $c)
                <li><strong>{{ $c->nombres_completos }}</strong><span class="tz-muted tz-small">{{ $c->institucion ?: '—' }} · {{ $c->rol_colaboracion }}</span></li>
            @endforeach
        </ul>
    </section>
@endif
