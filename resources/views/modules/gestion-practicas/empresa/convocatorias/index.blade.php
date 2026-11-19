@extends('modules.gestion-practicas.layout')

@section('title', 'Mis convocatorias')
@section('page-label', 'Mis convocatorias')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Mis convocatorias</h1><p>Cree una convocatoria y envíela a revisión del administrador para que se publique.</p></div>
        <a class="institutional-primary" href="{{ route('gestion-practicas.empresa.convocatorias.create') }}">Nueva convocatoria</a>
    </header>

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">Convocatorias de la empresa</h2><p>{{ count($convocatorias) }} {{ count($convocatorias) === 1 ? 'convocatoria' : 'convocatorias' }}</p></div>
        </header>
        @forelse ($convocatorias as $convocatoria)
            <article class="quality-indicator-row">
                <div>
                    <strong>{{ $convocatoria->titulo }}</strong>
                    <small>Cierra el {{ $convocatoria->fecha_cierre->format('d/m/Y') }} · {{ $convocatoria->vacantes }} {{ $convocatoria->vacantes === 1 ? 'vacante' : 'vacantes' }}</small>
                </div>
                <div>
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
                    @if ($convocatoria->observaciones_pendientes > 0)
                        <small>{{ $convocatoria->observaciones_pendientes }} observación(es) por corregir</small>
                    @endif
                </div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.empresa.convocatorias.show', $convocatoria) }}">Ver detalle</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Aún no ha creado convocatorias',
                'texto' => 'Use «Nueva convocatoria» para crear un borrador y luego envíelo a revisión.',
            ])
        @endforelse
    </section>
@endsection
