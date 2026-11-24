@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
@extends('modules.gestion-practicas.layout')

@section('title', 'Convocatorias')
@section('page-label', 'Convocatorias')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Convocatorias de prácticas</h1><p>Postule primero en el enlace de la empresa y luego registre aquí su postulación.</p></div>
    </header>

    @if ($practicaActiva)
        <div class="quality-alert gp-aviso" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
            <div>
                <strong>Tiene una práctica activa en {{ $empresaActiva ?? 'otra empresa' }}</strong>
                <span>
                    Mientras no la finalice no puede postular a otras convocatorias; puede consultarlas.
                    <a class="gp-link" href="{{ route('gestion-practicas.estudiante.practicas.show', $practicaActiva) }}">Ver mi práctica</a>
                </span>
            </div>
        </div>
    @endif

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">Convocatorias vigentes</h2><p>{{ count($convocatorias) }} {{ count($convocatorias) === 1 ? 'convocatoria abierta' : 'convocatorias abiertas' }}</p></div>
        </header>
        @forelse ($convocatorias as $convocatoria)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$convocatoria->id_empresa] ?? '—' }}</span>
                    <strong>{{ $convocatoria->titulo }}</strong>
                    <small>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }} · {{ $convocatoria->vacantes }} {{ $convocatoria->vacantes === 1 ? 'vacante' : 'vacantes' }} · cierra el {{ $convocatoria->fecha_cierre->format('d/m/Y') }}</small>
                </div>
                <div>
                    @if (in_array((int) $convocatoria->id_convocatoria, $yaPostulo, true))
                        <span class="quality-status is-success">Ya postuló</span>
                    @elseif ($practicaActiva)
                        <span class="quality-status is-neutral">Postulación no disponible</span>
                    @endif
                </div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.estudiante.convocatorias.show', $convocatoria) }}">Ver detalles</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'No hay convocatorias vigentes por ahora',
                'texto' => 'Cuando una empresa publique una convocatoria aparecerá aquí.',
            ])
        @endforelse
    </section>
@endsection
