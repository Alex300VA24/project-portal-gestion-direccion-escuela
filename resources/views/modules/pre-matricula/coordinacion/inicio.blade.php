@extends('modules.pre-matricula.layout')

@section('title', 'Coordinación')

@php $formato = fn ($fecha) => $fecha?->timezone('America/Lima')->translatedFormat('d M Y, H:i'); @endphp

@section('content')
    <header class="quality-page-heading">
        <div><span class="quality-eyebrow">Coordinación académica</span><h1>Pre Matrícula</h1><p>Ventanas, registros de estudiantes y estimación de demanda del período.</p></div>
        @if ($periodos->isNotEmpty())@include('modules.pre-matricula.coordinacion._periodo', ['accion' => route('pre-matricula.coordinacion.index')])@endif
    </header>

    @if ($aviso)
        <div class="pm-flash is-warning" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><div><strong>Revisa los períodos</strong>{{ $aviso }}</div></div>
    @endif

    @if ($periodo === null)
        <section class="quality-panel"><div class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/></svg><h2>No hay períodos académicos</h2><p>Los períodos se registran en el catálogo académico del portal (<code>core.periodo_academico</code>).</p></div></section>
    @else
        <section class="quality-summary" aria-label="Resumen del período">
            @php $total = array_sum($conteos); @endphp
            <div class="quality-score" style="--score: {{ $total > 0 ? round(($conteos['ENVIADA'] ?? 0) / $total * 100) : 0 }}" title="Registradas sobre el total de prematrículas iniciadas"><div><strong>{{ $conteos['ENVIADA'] ?? 0 }}</strong><span>prematrículas registradas</span></div></div>
            <dl class="quality-summary-facts">
                <div><dt>En borrador</dt><dd>{{ $conteos['BORRADOR'] ?? 0 }}</dd></div>
                <div><dt>En corrección</dt><dd>{{ $conteos['CORRECCION'] ?? 0 }}</dd></div>
                <div><dt>Estudiantes con demanda</dt><dd>{{ $resumen['total_estudiantes'] ?? 0 }}</dd></div>
            </dl>
            <a class="quality-primary-action" href="{{ route('pre-matricula.coordinacion.registros', ['periodo' => $periodo->id_periodo]) }}">Ver registros<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </section>

        <div class="quality-dashboard-grid">
            <section class="quality-panel" aria-labelledby="top-titulo">
                <header class="quality-panel-heading"><div><h2 id="top-titulo">Cursos más solicitados</h2><p>{{ \App\Modules\PreMatricula\Services\DemandaAcademica::AVISO }}</p></div><a href="{{ route('pre-matricula.coordinacion.demanda', ['periodo' => $periodo->id_periodo]) }}">Ver demanda</a></header>
                <div class="pm-table-wrap">
                    <table class="pm-table">
                        <thead><tr><th>Curso</th><th>Ciclo</th><th class="pm-num">Estudiantes</th></tr></thead>
                        <tbody>
                            @forelse ($top as $curso)
                                <tr><td><span class="pm-code">{{ $curso->codigo }}</span><br>{{ $curso->nombre }}</td><td>{{ $curso->ciclo }}</td><td class="pm-num">{{ $curso->estudiantes }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="pm-muted">Todavía no hay prematrículas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="quality-side-stack">
                <section class="quality-panel" aria-labelledby="ventana-titulo">
                    <header class="quality-panel-heading"><div><h2 id="ventana-titulo">Ventana actual</h2><p>Hora de Lima.</p></div></header>
                    <div class="pm-body">
                        @if ($ventana)
                            @include('modules.pre-matricula.partials.estado', ['valor' => $ventana->tipo])
                            <p>Abierta hasta el <strong>{{ $formato($ventana->fecha_fin) }}</strong>.</p>
                        @else
                            <p class="pm-muted">No hay una ventana abierta en este momento.</p>
                            @php $proxima = $ventanas->first(fn ($v) => $v->fecha_inicio->isFuture()); @endphp
                            @if ($proxima)<p>Próxima: {{ Str::lower($proxima->tipo) }} desde el {{ $formato($proxima->fecha_inicio) }}.</p>@endif
                        @endif
                    </div>
                    <a class="quality-panel-action" href="{{ route('pre-matricula.coordinacion.ventanas', ['periodo' => $periodo->id_periodo]) }}">Gestionar ventanas</a>
                </section>
                <section class="quality-panel" aria-labelledby="accesos-titulo">
                    <header class="quality-panel-heading"><div><h2 id="accesos-titulo">Accesos rápidos</h2></div></header>
                    <div class="pm-body pm-actions">
                        <a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.plan') }}">Importar malla curricular</a>
                        <a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.estudiantes') }}">Buscar estudiante</a>
                        <a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.demanda.exportar', $periodo->id_periodo) }}">Exportar demanda (CSV)</a>
                    </div>
                </section>
            </aside>
        </div>
    @endif
@endsection
