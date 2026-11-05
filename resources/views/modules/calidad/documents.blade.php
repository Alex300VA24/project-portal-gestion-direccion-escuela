@extends('layouts.calidad')

@section('title', 'Documentos')
@section('page-label', 'Documentos')

@section('content')
    @php
        $tabs = [
            'silabo' => ['Sílabos visados', 'Por semestre y ciclo'],
            'institucional' => ['Documentos institucionales', 'Archivos del módulo'],
            'calidad' => ['Documentos de calidad', 'Evidencias de acreditación'],
        ];
        $periodId = $activePeriod?->id_periodo;
    @endphp
    <div class="quality-page" x-data="{ query: '' }">
        <header class="quality-page-heading"><div><h1>Documentos</h1><p>Consulta sílabos visados, documentos institucionales y evidencias del sistema de calidad.</p></div></header>

        <nav class="quality-doctabs" aria-label="Tipo de documento">
            @foreach ($tabs as $key => [$label, $hint])
                <a href="{{ route('calidad.documents', ['tipo' => $key]) }}" @if ($activeType === $key) aria-current="page" @endif><strong>{{ $label }}</strong><small>{{ $hint }}</small></a>
            @endforeach
        </nav>

        @if ($activeType === 'silabo')
            <section class="quality-doc-filters" aria-label="Filtros de sílabos">
                <div>
                    <strong id="period-title">Semestre</strong>
                    <nav class="quality-segment" aria-labelledby="period-title">
                        @foreach ($periods as $period)
                            <a href="{{ route('calidad.documents', ['tipo' => 'silabo', 'periodo' => $period->id_periodo]) }}" @if ($periodId === $period->id_periodo) aria-current="page" @endif>{{ $period->codigo }}</a>
                        @endforeach
                    </nav>
                </div>
                <div>
                    <strong id="cycle-title">Ciclo</strong>
                    <nav class="quality-segment" aria-labelledby="cycle-title">
                        @forelse ($cycleCounts as $cycle => $total)
                            <a href="{{ route('calidad.documents', ['tipo' => 'silabo', 'periodo' => $periodId, 'ciclo' => $cycle]) }}" @if ($activeCycle === $cycle) aria-current="page" @endif>{{ $cycle }}.º ciclo <span>{{ $total }}</span></a>
                        @empty
                            <span class="quality-segment-empty">Sin sílabos en este semestre</span>
                        @endforelse
                    </nav>
                </div>
            </section>
        @endif

        <section class="quality-repository">
            <header>
                <label class="quality-search"><span class="sr-only">Buscar documento</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input x-model.debounce.200ms="query" type="search" placeholder="Buscar por título o código…" autocomplete="off"></label>
                <span aria-live="polite">{{ $documents->count() }} {{ $documents->count() === 1 ? 'documento' : 'documentos' }}</span>
            </header>
            <ul class="quality-document-list">
                @forelse ($documents as $item)
                    @php $searchable = mb_strtolower($item['title'].' '.$item['code'].' '.implode(' ', $item['meta'])); @endphp
                    <li x-show="{{ Illuminate\Support\Js::from($searchable) }}.includes(query.toLowerCase())">
                        <span class="quality-document-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h4"/></svg></span>
                        <div>
                            <h2>{{ $item['title'] }}</h2>
                            <p>@if ($item['code'])<span class="quality-code">{{ $item['code'] }}</span>@endif @foreach ($item['meta'] as $meta)<span>{{ $meta }}</span>@endforeach</p>
                            @if ($item['description'])<small>{{ $item['description'] }}</small>@endif
                        </div>
                        <div class="quality-file-meta">
                            @if ($item['status'])<span class="quality-status is-neutral">{{ $item['status'] }}</span>@endif
                            @if ($item['url'])<a class="quality-open-link" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">Abrir documento</a>@endif
                        </div>
                    </li>
                @empty
                    <li class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/></svg><h2>No hay documentos para mostrar</h2><p>Los documentos se leen desde la base de datos del portal y aparecerán aquí cuando estén registrados.</p></li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
