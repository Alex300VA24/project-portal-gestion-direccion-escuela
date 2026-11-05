@extends('layouts.calidad')

@section('title', 'Indicadores')
@section('page-label', 'Indicadores')

@section('content')
    <div class="quality-page" x-data="{ query: '', status: 'all' }">
        <header class="quality-page-heading"><div><h1>Indicadores</h1><p>Compara medición, meta y umbral usando el periodo académico seleccionado.</p></div>@if($periods->isNotEmpty())<form method="GET" class="quality-period-form"><label for="quality-period">Periodo</label><select id="quality-period" name="periodo" onchange="this.form.submit()">@foreach($periods as $period)<option value="{{ $period->id_periodo }}" @selected($selectedPeriod?->id_periodo === $period->id_periodo)>{{ $period->codigo }}</option>@endforeach</select></form>@endif</header>

        <section class="quality-kpis" aria-label="Resumen de indicadores">
            @foreach ([['total','Total'],['success','Cumplen'],['warning','En riesgo'],['danger','No cumplen'],['neutral','Sin medición']] as [$key,$label])
                <button type="button" class="is-{{ $key }}" @click="status = status === '{{ $key }}' ? 'all' : '{{ $key }}'" :aria-pressed="(status === '{{ $key }}').toString()"><span>{{ $summary[$key] }}</span><strong>{{ $label }}</strong></button>
            @endforeach
        </section>

        <section class="quality-indicator-panel">
            <header><label class="quality-search"><span class="sr-only">Buscar indicador</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input x-model.debounce.200ms="query" type="search" placeholder="Buscar por código, nombre o proceso…" autocomplete="off"></label><button class="quality-reset" type="button" @click="query = ''; status = 'all'">Limpiar filtros</button></header>
            <div class="quality-indicator-table" role="table" aria-label="Indicadores del periodo">
                <div class="quality-indicator-head" role="row"><span>Indicador</span><span>Medición y meta</span><span>Responsable</span><span>Estado</span></div>
                @forelse($indicators as $indicator)
                    @php $searchable = mb_strtolower($indicator['code'].' '.$indicator['name'].' '.$indicator['process_code'].' '.$indicator['process_name']); @endphp
                    <article id="proceso-{{ $indicator['id'] }}" class="quality-indicator-item" role="row" x-show="{{ Illuminate\Support\Js::from($searchable) }}.includes(query.toLowerCase()) && (status === 'all' || status === '{{ $indicator['status_key'] }}')">
                        <div role="cell"><span class="quality-code">{{ $indicator['code'] }}</span><strong>{{ $indicator['name'] }}</strong><small>{{ $indicator['process_code'] }} · {{ $indicator['process_name'] }}</small></div>
                        <div class="quality-measure" role="cell"><span><strong>{{ $indicator['value'] === null ? '—' : number_format($indicator['value'], 2) }}</strong><small>{{ $indicator['unit'] }}</small></span><div><i style="width: {{ $indicator['progress'] }}%"></i><b style="left: min({{ $indicator['progress'] }}%, 98%)"></b></div><small>Meta: {{ $indicator['target'] === null ? 'no definida' : number_format($indicator['target'], 2).' '.$indicator['unit'] }}</small></div>
                        <div role="cell"><strong>{{ $indicator['responsible'] }}</strong><small>{{ $indicator['frequency'] }} · {{ $indicator['direction'] }}</small></div>
                        <div role="cell"><span class="quality-status is-{{ $indicator['status_key'] }}">{{ $indicator['status'] }}</span></div>
                    </article>
                @empty
                    <div class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-8m5 8V7"/></svg><h2>No hay indicadores para mostrar</h2><p>La pantalla usa <code>calidad.indicador</code>, <code>meta_indicador</code> y <code>medicion_indicador</code>.</p></div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
