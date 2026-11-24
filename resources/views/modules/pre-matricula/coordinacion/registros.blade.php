@extends('modules.pre-matricula.layout')

@section('title', 'Registros')
@section('page-label', 'Registros')

@php
    $estadoActual = request('estado');
    $filtros = ['' => 'Todos', 'ENVIADA' => 'Registradas', 'BORRADOR' => 'Borradores', 'CORRECCION' => 'En corrección'];
@endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Registros de prematrícula</h1><p>Consulta de lo que registró cada estudiante y sus constancias. La prematrícula no se aprueba ni se rechaza.</p></div>
        @if ($periodos->isNotEmpty())@include('modules.pre-matricula.coordinacion._periodo', ['accion' => route('pre-matricula.coordinacion.registros')])@endif
    </header>

    @if ($periodo === null)
        <section class="quality-panel"><div class="quality-empty"><h2>No hay períodos con registros</h2></div></section>
    @else
        <nav class="pm-tabs" aria-label="Filtrar por estado">
            @foreach ($filtros as $clave => $etiqueta)
                <a href="{{ route('pre-matricula.coordinacion.registros', array_filter(['periodo' => $periodo->id_periodo, 'estado' => $clave, 'buscar' => request('buscar')])) }}" @if ((string) $estadoActual === (string) $clave) aria-current="page" @endif>{{ $etiqueta }}@if ($clave !== '')<span class="pm-chip">{{ $conteos[$clave] ?? 0 }}</span>@endif</a>
            @endforeach
        </nav>
        <form class="pm-filters" method="GET" action="{{ route('pre-matricula.coordinacion.registros') }}">
            <input type="hidden" name="periodo" value="{{ $periodo->id_periodo }}">
            @if ($estadoActual)<input type="hidden" name="estado" value="{{ $estadoActual }}">@endif
            <label class="pm-field"><span>Buscar</span><input type="search" name="buscar" value="{{ request('buscar') }}" placeholder="Código o apellido"></label>
            <button class="pm-button" type="submit">Buscar</button>
        </form>

        <section class="quality-panel">
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Estudiante</th><th>Estado</th><th>Validación</th><th>Registrada</th><th class="pm-num">Revisión</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($registros as $s)
                            @php $p = $s->estudiante?->persona; @endphp
                            <tr>
                                <td><span class="pm-code">{{ $s->estudiante?->codigo_universitario }}</span><br>{{ trim(($p?->apellido_paterno ?? '').' '.($p?->apellido_materno ?? '')) }}, {{ $p?->nombres }}</td>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $s->id_cronograma_correccion ? 'CORRECCION' : $s->estado])</td>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $s->validacion?->estado])</td>
                                <td>{{ $s->enviada_en?->timezone('America/Lima')->translatedFormat('d M Y, H:i') ?? '—' }}</td>
                                <td class="pm-num">{{ $s->revisionVigente?->numero_revision ?? '—' }}</td>
                                <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.registros.show', $s->id_solicitud) }}">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="pm-muted">No hay registros con estos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($registros->hasPages())
                <div class="pm-pagination">
                    <span>{{ $registros->firstItem() }}–{{ $registros->lastItem() }} de {{ $registros->total() }}</span>
                    <span class="pm-actions">
                        @if ($registros->previousPageUrl())<a class="pm-button is-secondary is-small" href="{{ $registros->previousPageUrl() }}">Anterior</a>@endif
                        @if ($registros->nextPageUrl())<a class="pm-button is-secondary is-small" href="{{ $registros->nextPageUrl() }}">Siguiente</a>@endif
                    </span>
                </div>
            @endif
        </section>
    @endif
@endsection
