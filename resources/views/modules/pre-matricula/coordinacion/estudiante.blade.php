@extends('modules.pre-matricula.layout')

@section('title', $estudiante->codigo_universitario)
@section('page-label', $estudiante->codigo_universitario)

@php $p = $estudiante->persona; @endphp

@section('content')
    <header class="quality-page-heading">
        <div><span class="quality-eyebrow">{{ $estudiante->codigo_universitario }}</span><h1>{{ trim(($p?->nombres ?? '').' '.($p?->apellido_paterno ?? '').' '.($p?->apellido_materno ?? '')) }}</h1><p>Situación académica para orientar la prematrícula.</p></div>
        @if ($periodos->isNotEmpty())@include('modules.pre-matricula.coordinacion._periodo', ['accion' => route('pre-matricula.coordinacion.estudiantes.show', $estudiante->codigo_universitario)])@endif
    </header>

    <div class="pm-grid">
        <div class="pm-stack">
            <section class="quality-panel" aria-labelledby="pm-titulo">
                <header class="quality-panel-heading"><div><h2 id="pm-titulo">Prematrícula {{ $periodo?->codigo }}</h2></div>@if ($solicitud)<a href="{{ route('pre-matricula.coordinacion.registros.show', $solicitud->id_solicitud) }}">Ver detalle</a>@endif</header>
                <div class="pm-body">
                    @if ($solicitud)
                        <div class="pm-actions">@include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->id_cronograma_correccion ? 'CORRECCION' : $solicitud->estado]) @include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->validacion?->estado])</div>
                        @if ($solicitud->revisionVigente)<p>Constancia vigente: revisión {{ $solicitud->revisionVigente->numero_revision }}, {{ count($solicitud->revisionVigente->snapshot['detalles'] ?? []) }} cursos.</p>@endif
                    @else
                        <p class="pm-muted">No registró prematrícula en este período.</p>
                    @endif
                </div>
            </section>

            <section class="quality-panel" aria-labelledby="disp-titulo">
                <header class="quality-panel-heading"><div><h2 id="disp-titulo">Cursos que puede llevar</h2><p>Según los resultados anteriores al período {{ $periodo?->codigo }}.</p></div></header>
                <div class="pm-table-wrap">
                    <table class="pm-table">
                        <thead><tr><th>Curso</th><th>Ciclo</th><th>Situación</th></tr></thead>
                        <tbody>
                            @forelse ($disponibles as $c)
                                <tr><td><span class="pm-code">{{ $c->codigo }}</span><br>{{ $c->nombre }}</td><td>{{ $c->ciclo }}</td><td>@include('modules.pre-matricula.partials.estado', ['valor' => $c->evaluacion['estado'] ?? null])</td></tr>
                            @empty
                                <tr><td colspan="3" class="pm-muted">Sin cursos disponibles.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="quality-panel" aria-labelledby="hist-titulo">
            <header class="quality-panel-heading"><div><h2 id="hist-titulo">Historial académico</h2><p>Resultados oficiales importados.</p></div></header>
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Período</th><th>Curso</th><th class="pm-num">Nota</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse ($historial as $h)
                            <tr><td>{{ $h->periodo }}</td><td><span class="pm-code">{{ $h->codigo }}</span><br>{{ $h->nombre }}</td><td class="pm-num">{{ $h->nota_final ?? '—' }}</td><td>@include('modules.pre-matricula.partials.estado', ['valor' => $h->estado])</td></tr>
                        @empty
                            <tr><td colspan="4" class="pm-muted">Sin resultados registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
