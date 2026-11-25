@extends('modules.pre-matricula.layout')

@section('title', 'Registro')
@section('page-label', $solicitud->estudiante?->codigo_universitario)

@php
    $p = $solicitud->estudiante?->persona;
    $vigente = $solicitud->revisionVigente;
    $validados = collect($solicitud->validacion?->cursos ?? [])->keyBy('id_curso_plan');
@endphp

@section('content')
    <header class="quality-page-heading">
        <div><span class="quality-eyebrow">{{ $solicitud->estudiante?->codigo_universitario }} · {{ $solicitud->periodo?->codigo }}</span><h1>{{ trim(($p?->nombres ?? '').' '.($p?->apellido_paterno ?? '').' '.($p?->apellido_materno ?? '')) }}</h1><p>Detalle de la prematrícula y de sus constancias.</p></div>
        <div class="pm-actions">
            @include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->id_cronograma_correccion ? 'CORRECCION' : $solicitud->estado])
            @include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->validacion?->estado])
        </div>
    </header>

    <div class="pm-grid">
        <section class="quality-panel" aria-labelledby="vigente-titulo">
            <header class="quality-panel-heading"><div><h2 id="vigente-titulo">Cursos de la constancia vigente</h2><p>@if ($vigente) Revisión {{ $vigente->numero_revision }} · {{ $vigente->snapshot['creditos_totales'] ?? 0 }} de {{ $vigente->snapshot['creditos_maximos'] ?? '—' }} créditos @else Aún no registra una constancia. @endif</p></div>@if ($vigente)<a href="{{ route('pre-matricula.constancia', $vigente->id_revision) }}" target="_blank" rel="noopener">PDF</a>@endif</header>
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Curso</th><th>Ciclo</th><th class="pm-num">Créditos</th><th>Validación actual</th></tr></thead>
                    <tbody>
                        @forelse ($vigente?->snapshot['detalles'] ?? [] as $d)
                            <tr>
                                <td><span class="pm-code">{{ $d['codigo'] }}</span><br>{{ $d['nombre'] }}</td><td>{{ $d['ciclo'] }}</td><td class="pm-num">{{ $d['creditos'] }}</td>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $validados[$d['id_curso_plan']]['estado'] ?? null])@if ($validados[$d['id_curso_plan']]['motivo'] ?? null)<small>{{ $validados[$d['id_curso_plan']]['motivo'] }}</small>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pm-muted">Sin cursos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="quality-panel" aria-labelledby="revisiones-titulo">
            <header class="quality-panel-heading"><div><h2 id="revisiones-titulo">Historial de constancias</h2><p>Las revisiones no se modifican.</p></div></header>
            <ol class="pm-timeline">
                @forelse ($revisiones as $r)
                    <li>
                        <span class="pm-code">v{{ $r->numero_revision }}</span>
                        <div><strong>{{ $r->enviada_en?->timezone('America/Lima')->translatedFormat('d M Y, H:i') }} @if ($r->id_revision === $solicitud->id_revision_vigente)<span class="quality-status is-success">Vigente</span>@endif</strong><small>{{ count($r->snapshot['detalles'] ?? []) }} cursos · {{ $r->codigo_constancia }}</small></div>
                        <a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.constancia', $r->id_revision) }}" target="_blank" rel="noopener">PDF</a>
                    </li>
                @empty
                    <li><span></span><span class="pm-muted">Sin constancias.</span><span></span></li>
                @endforelse
            </ol>
            <a class="quality-panel-action" href="{{ route('pre-matricula.coordinacion.estudiantes.show', ['codigo' => $solicitud->estudiante?->codigo_universitario, 'periodo' => $solicitud->id_periodo]) }}">Ver situación académica</a>
        </section>
    </div>
@endsection
