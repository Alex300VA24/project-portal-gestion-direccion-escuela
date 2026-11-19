@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
@extends('modules.gestion-practicas.layout')

@section('title', $convocatoria->titulo)
@section('page-label', $convocatoria->titulo)

@section('content')
    <header class="quality-page-heading">
        <div><h1>{{ $convocatoria->titulo }}</h1><p>{{ $empresa }}</p></div>
    </header>

    <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
        <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Detalle de la convocatoria</h2></div></header>
        <div class="quality-compact-list">
            <article><div><small>Tipo</small><strong>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</strong></div></article>
            <article><div><small>Modalidad</small><strong>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</strong></div></article>
            <article><div><small>Vacantes</small><strong>{{ $convocatoria->vacantes }}</strong></div></article>
            <article><div><small>Cierre de postulación</small><strong>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</strong></div></article>
        </div>
        <div class="gp-body">
            <h3>Descripción</h3><p class="gp-text">{{ $convocatoria->descripcion }}</p>
            <h3>Requisitos</h3><p class="gp-text">{{ $convocatoria->requisitos }}</p>
        </div>
        <div class="gp-body gp-actions">
            <a class="institutional-secondary" href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener">Acceder al enlace de postulación<span class="sr-only"> (se abre en una pestaña nueva)</span></a>
            @if (! $vigente)
                <span class="quality-status is-neutral">La convocatoria ya no está vigente</span>
            @elseif ($yaPostulo)
                <span class="quality-status is-success">Ya registró su postulación</span>
            @else
                <form method="POST" action="{{ route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria) }}">
                    @csrf
                    <button class="institutional-primary">Registrar mi postulación</button>
                </form>
            @endif
        </div>
        <div class="gp-body"><p class="gp-lead">Postule primero en el enlace de la empresa y luego registre aquí su postulación para llevar el control del proceso.</p></div>
    </section>
@endsection
