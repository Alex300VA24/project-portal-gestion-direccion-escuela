@extends('layouts.calidad')

@section('title', 'Mapa de procesos')
@section('page-label', 'Mapa de procesos')

@section('content')
    <div class="quality-page" x-data="{ query: '', stage: 'all', responsible: 'all' }">
        <header class="quality-page-heading"><div><h1>Mapa de procesos</h1><p>Explora la jerarquía real de <code>calidad.proceso</code> y sus indicadores vinculados.</p></div><dl class="quality-inline-stats"><div><dt>Etapas</dt><dd>{{ $stages->count() }}</dd></div><div><dt>Procesos</dt><dd>{{ $processTotal }}</dd></div><div><dt>Indicadores</dt><dd>{{ $indicatorTotal }}</dd></div></dl></header>

        @if ($stages->isNotEmpty())
            <section class="quality-stage-strip" aria-label="Etapas del sistema">
                @foreach ($stages as $stage)
                    <button type="button" @click="stage = stage === '{{ $stage['id'] }}' ? 'all' : '{{ $stage['id'] }}'" :aria-pressed="(stage === '{{ $stage['id'] }}').toString()"><span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><strong>{{ $stage['name'] }}</strong><small>{{ $stage['processes']->count() }} procesos · {{ $stage['indicator_count'] }} indicadores</small></button>
                @endforeach
            </section>
        @endif

        <section class="quality-map-tools" aria-label="Filtros del mapa">
            <label class="quality-search"><span class="sr-only">Buscar proceso</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input x-model.debounce.200ms="query" type="search" placeholder="Buscar por código, nombre o responsable…" autocomplete="off"></label>
            <label class="quality-select"><span>Responsable</span><select x-model="responsible"><option value="all">Todos</option>@foreach($responsibles as $responsible)<option value="{{ $responsible }}">{{ $responsible }}</option>@endforeach</select></label>
            <button class="quality-reset" type="button" @click="query = ''; stage = 'all'; responsible = 'all'">Limpiar filtros</button>
        </section>

        <div class="quality-map-sections">
            @forelse ($stages as $stage)
                <section class="quality-process-group" x-show="stage === 'all' || stage === '{{ $stage['id'] }}'" x-transition.opacity>
                    <header><div><span class="quality-stage-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $stage['name'] }}</h2></div><p>{{ $stage['indicator_count'] }} indicadores vinculados</p></header>
                    <div class="quality-process-grid">
                        @foreach ($stage['processes'] as $process)
                            @php $searchable = mb_strtolower($process['code'].' '.$process['name'].' '.$process['responsible']); @endphp
                            <article class="quality-process-card" x-show="({{ Illuminate\Support\Js::from($searchable) }}.includes(query.toLowerCase())) && (responsible === 'all' || responsible === {{ Illuminate\Support\Js::from($process['responsible']) }})">
                                <div><span class="quality-code">{{ $process['code'] }}</span><span class="quality-level">Nivel {{ $process['level'] }}</span></div><h3>{{ $process['name'] }}</h3>
                                <dl><div><dt>Responsable</dt><dd>{{ $process['responsible'] }}</dd></div><div><dt>Indicadores</dt><dd>{{ $process['indicator_count'] }}</dd></div></dl>
                                <a href="{{ route('calidad.indicators') }}#proceso-{{ $process['id'] }}">Ver indicadores<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="quality-empty quality-panel"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><path d="M10 6.5h4M17.5 10v4"/></svg><h2>No hay procesos registrados</h2><p>Agrega registros en <code>calidad.proceso</code>; no se importaron tablas desde projectCalidad.</p></div>
            @endforelse
        </div>
    </div>
@endsection
