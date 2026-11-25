@extends('modules.pre-matricula.layout')

@section('title', 'Mis constancias')
@section('page-label', 'Mis constancias')

@section('content')
    <header class="quality-page-heading"><div><h1>Mis constancias</h1><p>Cada vez que registras o corriges tu prematrícula se emite una constancia nueva. Las anteriores se conservan como historial.</p></div></header>

    @forelse ($solicitudes as $solicitud)
        <section class="quality-panel pm-mt">
            <header class="quality-panel-heading"><div><h2>Período {{ $solicitud->periodo->codigo }}</h2><p>{{ $solicitud->revisiones->count() }} {{ Str::plural('revisión', $solicitud->revisiones->count()) }}</p></div>@include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->id_cronograma_correccion ? 'CORRECCION' : $solicitud->estado])</header>
            <ol class="pm-timeline">
                @foreach ($solicitud->revisiones as $revision)
                    <li>
                        <span class="pm-code">v{{ $revision->numero_revision }}</span>
                        <div><strong>{{ $revision->enviada_en?->timezone('America/Lima')->translatedFormat('d M Y, H:i') }} @if ($revision->id_revision === $solicitud->id_revision_vigente)<span class="quality-status is-success">Vigente</span>@endif</strong><small>{{ count($revision->snapshot['detalles'] ?? []) }} cursos · {{ $revision->snapshot['creditos_totales'] ?? 0 }} créditos · {{ $revision->codigo_constancia }}</small></div>
                        <a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.constancia', $revision->id_revision) }}" target="_blank" rel="noopener">PDF</a>
                    </li>
                @endforeach
            </ol>
        </section>
    @empty
        <section class="quality-panel"><div class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5"/></svg><h2>Aún no tienes constancias</h2><p>Aparecerán aquí cuando registres tu primera prematrícula.</p></div></section>
    @endforelse
@endsection
