{{-- Tarjeta de proyecto: requiere $proyecto, $integrantes, $asesor, $linea; opcional $jurado --}}
<section class="tz-panel tz-panel-body" aria-labelledby="tz-ficha-titulo">
    <div class="tz-tags">
        <span class="tz-status is-info">{{ $proyecto->etapa === 'Tesis' ? 'Tesis (F2) · 10.º ciclo' : 'Proyecto (F1) · 9.º ciclo' }}</span>
        @include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $proyecto->estado])
        <span class="tz-status is-neutral tz-code">{{ $proyecto->codigo }}</span>
    </div>
    <h2 id="tz-ficha-titulo" style="margin-top: 14px; font-size: 1.35rem; line-height: 1.3">{{ $proyecto->titulo }}</h2>
    <dl class="tz-facts">
        <div>
            <dt>Integrantes</dt>
            @foreach ($integrantes as $i)
                <dd>{{ $i['nombre'] }} <span class="tz-muted tz-small">· {{ $i['codigo'] }}</span></dd>
            @endforeach
        </div>
        <div>
            <dt>Asesor</dt>
            @if ($asesor)
                <dd>{{ $asesor['nombre'] }}</dd>
                @if ($asesor['resolucion'])
                    <dd class="tz-muted tz-small">Res. N.° {{ $asesor['resolucion'] }}</dd>
                @endif
            @else
                <dd class="tz-muted">Pendiente de designación</dd>
            @endif
        </div>
        <div>
            <dt>Línea de investigación</dt>
            <dd>{{ $linea }}</dd>
        </div>
        @if (! empty($jurado))
            <div>
                <dt>Jurado</dt>
                @foreach ($jurado as $j)
                    <dd>{{ $j['cargo'] }}: {{ $j['nombre'] }}</dd>
                @endforeach
            </div>
        @endif
    </dl>
</section>
