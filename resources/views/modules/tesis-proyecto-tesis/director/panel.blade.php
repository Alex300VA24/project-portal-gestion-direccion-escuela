@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Director de Escuela</p>
            <h1>Panel general</h1>
            <p>Periodo {{ $periodo ?? '—' }} · semana {{ $semana }}</p>
        </div>
    </div>

    <div class="tz-grid">
        <article class="tz-card tz-stat"><strong>{{ $cifras['activas'] }}</strong><span class="tz-muted">Investigaciones activas</span></article>
        <article class="tz-card tz-stat"><strong>{{ $cifras['proyecto'] }}</strong><span class="tz-muted">En proyecto (9.º ciclo)</span></article>
        <article class="tz-card tz-stat"><strong>{{ $cifras['tesis'] }}</strong><span class="tz-muted">En tesis (10.º ciclo)</span></article>
        <article class="tz-card tz-stat"><strong style="color: {{ $cifras['accion'] ? 'var(--tz-warning)' : 'var(--tz-navy)' }}">{{ $cifras['accion'] }}</strong><span class="tz-muted">Esperan tu acción</span></article>
    </div>

    <div class="tz-grid-2">
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Requieren tu acción</h2></div>
            @if ($accion === [])
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay trámites pendientes', 'texto' => 'Aquí aparecerán los proyectos que esperan la solicitud de asesor o la asignación de jurado.'])
            @else
                <ul class="tz-list">
                    @foreach ($accion as $a)
                        <li>
                            <div class="tz-tags"><span class="tz-status is-{{ $a['variante'] }}">{{ $a['tipo'] }}</span> <strong class="tz-code">{{ $a['proyecto']->codigo }}</strong></div>
                            <span>{{ $a['proyecto']->titulo }} <span class="tz-muted">· {{ $a['detalle'] }}</span></span>
                            <div><a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ $a['url'] }}">{{ $a['boton'] }}<span class="sr-only"> · {{ $a['proyecto']->codigo }}</span></a></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Investigaciones por estado</h2></div>
            <div class="tz-panel-body" style="display: grid; gap: 12px">
                @forelse ($porEstado as $e)
                    <div style="display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(80px, 1fr) 32px; gap: 10px; align-items: center">
                        <span>{{ $e['etiqueta'] }}</span>
                        <div class="tz-bar" role="img" aria-label="{{ $e['n'] }} proyectos"><span style="width: {{ round($e['n'] / $maximo * 100) }}%"></span></div>
                        <strong style="text-align: right">{{ $e['n'] }}</strong>
                    </div>
                @empty
                    <p class="tz-muted">Aún no hay proyectos registrados.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="tz-grid">
        <article class="tz-card">
            <span class="tz-eyebrow">Tesis aprobadas</span>
            <strong style="font-size: 1.8rem; color: var(--tz-navy)">{{ $aprobadas }}</strong>
            <span class="tz-muted">Sustentaciones aprobadas en el sistema</span>
        </article>
        <article class="tz-card">
            <span class="tz-eyebrow">Tiempo promedio</span>
            <strong style="font-size: 1.8rem; color: var(--tz-navy)">{{ $diasAsesor !== null ? $diasAsesor.' días' : '—' }}</strong>
            <span class="tz-muted">Desde el registro hasta la resolución del asesor</span>
        </article>
        <article class="tz-card">
            <span class="tz-eyebrow">Tiempo promedio</span>
            <strong style="font-size: 1.8rem; color: var(--tz-navy)">{{ $diasDictamen !== null ? $diasDictamen.' días' : '—' }}</strong>
            <span class="tz-muted">Desde la conformidad del asesor hasta el dictamen</span>
        </article>
    </div>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Actas y dictámenes recientes</h2></div>
        @if ($actas->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin actas todavía', 'texto' => 'Los dictámenes de aprobación y las actas de sustentación aparecerán aquí.'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Documento</th><th scope="col">Proyecto</th><th scope="col">Resultado</th><th scope="col">Fecha</th><th scope="col">Firmada</th></tr></thead>
                <tbody>
                    @foreach ($actas as $a)
                        <tr>
                            <td>{{ $a->tipo === 'Dictamen' ? 'Dictamen' : 'Acta de sustentación' }} <span class="tz-code">{{ $a->numero }}</span></td>
                            <td class="tz-code">{{ $a->proyecto?->codigo }}</td>
                            <td>{{ $a->resultado }}</td>
                            <td>{{ $a->emitida_en?->format('d/m/Y') }}</td>
                            <td>
                                @if ($a->id_archivo_firmado)
                                    <a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['acta-firmada', $a->uuid]) }}" target="_blank" rel="noopener">Ver PDF</a>
                                @else
                                    <span class="tz-muted">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
