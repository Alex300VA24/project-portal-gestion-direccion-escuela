@extends('layouts.calidad')

@section('title', 'Inicio')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div><h1>Estado de la calidad</h1><p>Lectura ejecutiva de procesos, indicadores y acciones de mejora del periodo vigente.</p></div>
            @if ($period)<span class="quality-period">Periodo {{ $period->codigo }}</span>@endif
        </header>

        <section class="quality-summary" aria-label="Resumen del periodo">
            <div class="quality-score" style="--score: {{ $compliance }}">
                <div><strong>{{ number_format($compliance, 1) }}%</strong><span>indicadores conformes</span></div>
            </div>
            <dl class="quality-summary-facts">
                <div><dt>Procesos</dt><dd>{{ $processTotal }}</dd></div>
                <div><dt>Indicadores</dt><dd>{{ $indicatorTotal }}</dd></div>
                <div><dt>Cumplen</dt><dd>{{ $compliant }}</dd></div>
                <div><dt>En riesgo</dt><dd>{{ $atRisk }}</dd></div>
                <div><dt>No cumplen</dt><dd>{{ $nonCompliant }}</dd></div>
                <div><dt>Sin medición</dt><dd>{{ $unmeasured }}</dd></div>
            </dl>
            <a class="quality-primary-action" href="{{ route('calidad.indicators', ['periodo' => $period?->id_periodo]) }}">Revisar indicadores<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </section>

        <div class="quality-dashboard-grid">
            <section class="quality-panel" aria-labelledby="priority-title">
                <header class="quality-panel-heading"><div><h2 id="priority-title">Indicadores prioritarios</h2><p>Ordenados por riesgo y falta de medición.</p></div><a href="{{ route('calidad.indicators') }}">Ver todos</a></header>
                @forelse ($priorityIndicators as $indicator)
                    <article class="quality-indicator-row">
                        <div><span class="quality-code">{{ $indicator['code'] }}</span><strong>{{ $indicator['name'] }}</strong><small>{{ $indicator['process_code'] }} · {{ $indicator['process_name'] }}</small></div>
                        <div class="quality-progress"><span><b>{{ $indicator['value'] === null ? 'Sin valor' : number_format($indicator['value'], 2).' '.$indicator['unit'] }}</b><small>Meta {{ $indicator['target'] === null ? 'no definida' : number_format($indicator['target'], 2).' '.$indicator['unit'] }}</small></span><div><i style="width: {{ $indicator['progress'] }}%"></i></div></div>
                        <span class="quality-status is-{{ $indicator['status_key'] }}">{{ $indicator['status'] }}</span>
                    </article>
                @empty
                    <div class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-8m5 8V7"/></svg><h3>Aún no hay indicadores</h3><p>Los registros de <code>calidad.indicador</code> aparecerán aquí.</p></div>
                @endforelse
            </section>

            <aside class="quality-side-stack">
                <section class="quality-panel" aria-labelledby="evidence-title">
                    <header class="quality-panel-heading"><div><h2 id="evidence-title">Evidencias recientes</h2><p>Últimos archivos vinculados.</p></div></header>
                    <div class="quality-compact-list">
                        @forelse ($evidence as $item)
                            <article><span class="quality-file-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/></svg></span><div><strong>{{ $item['title'] }}</strong><small>{{ $item['standard_code'] }} · {{ $item['period_code'] }}</small></div></article>
                        @empty
                            <p class="quality-list-empty">Sin evidencias registradas.</p>
                        @endforelse
                    </div>
                    <a class="quality-panel-action" href="{{ route('calidad.documents') }}">Abrir repositorio</a>
                </section>

                <section class="quality-panel" aria-labelledby="improvement-title">
                    <header class="quality-panel-heading"><div><h2 id="improvement-title">Planes de mejora</h2><p>Avance de acciones registradas.</p></div></header>
                    <div class="quality-plan-list">
                        @forelse ($improvements as $plan)
                            <article><div><strong>{{ $plan['title'] }}</strong><small>{{ $plan['origin_code'] }} · {{ $plan['status'] }}</small></div><span>{{ number_format($plan['progress'], 0) }}%</span></article>
                        @empty
                            <p class="quality-list-empty">Sin planes de mejora registrados.</p>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
