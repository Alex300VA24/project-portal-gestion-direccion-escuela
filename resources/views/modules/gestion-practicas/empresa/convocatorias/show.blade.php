@php
    use App\Modules\GestionPracticas\Models\Convocatoria;
    use App\Modules\GestionPracticas\Support\Catalogos;

    $editable = in_array($convocatoria->estado, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], true);
@endphp
@extends('modules.gestion-practicas.layout')

@section('title', $convocatoria->titulo)
@section('page-label', $convocatoria->titulo)

@section('content')
    <header class="quality-page-heading">
        <div><h1>{{ $convocatoria->titulo }}</h1><p>{{ $postulantes }} {{ $postulantes === 1 ? 'postulante registrado' : 'postulantes registrados' }}</p></div>
        @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
    </header>

    <div class="gp-stack">
        <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
            <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Detalle</h2></div></header>
            <div class="quality-compact-list">
                <article><div><small>Tipo</small><strong>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</strong></div></article>
                <article><div><small>Modalidad</small><strong>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</strong></div></article>
                <article><div><small>Vacantes</small><strong>{{ $convocatoria->vacantes }}</strong></div></article>
                <article><div><small>Cierre</small><strong>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</strong></div></article>
            </div>
            <div class="gp-body">
                <h3>Descripción</h3><p class="gp-text">{{ $convocatoria->descripcion }}</p>
                <h3>Requisitos</h3><p class="gp-text">{{ $convocatoria->requisitos }}</p>
                <h3>Enlace de postulación</h3><p class="gp-text"><a class="gp-link" href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener">{{ $convocatoria->enlace_postulacion }}<span class="sr-only"> (se abre en una pestaña nueva)</span></a></p>
            </div>
            @if ($editable || $convocatoria->estado === Convocatoria::PUBLICADA)
                <div class="gp-body gp-actions">
                    @if ($editable)
                        <a class="institutional-secondary" href="{{ route('gestion-practicas.empresa.convocatorias.edit', $convocatoria) }}">Corregir</a>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.enviar', $convocatoria) }}">@csrf<button class="institutional-primary">Enviar a revisión</button></form>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.anular', $convocatoria) }}">@csrf<button class="institutional-secondary is-danger">Anular</button></form>
                    @endif
                    @if ($convocatoria->estado === Convocatoria::PUBLICADA)
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.cerrar', $convocatoria) }}">@csrf<button class="institutional-secondary">Cerrar convocatoria</button></form>
                    @endif
                </div>
            @endif
        </section>

        @if ($observaciones->isNotEmpty())
            <section class="quality-panel" aria-labelledby="obs-titulo">
                <header class="quality-panel-heading"><div><h2 id="obs-titulo">Observaciones del administrador</h2><p>Corrija lo pendiente y vuelva a enviar la convocatoria.</p></div></header>
                <ul class="gp-timeline">
                    @foreach ($observaciones as $observacion)
                        <li @class(['is-pending' => $observacion->estado === 'pendiente'])>
                            <p>{{ $observacion->descripcion }}</p>
                            <small>{{ $observacion->registrado_en->format('d/m/Y H:i') }} @include('modules.gestion-practicas.partials.estado', ['estado' => $observacion->estado])</small>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
