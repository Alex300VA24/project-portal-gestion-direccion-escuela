@php use App\Modules\GestionPracticas\Models\Practica; @endphp
@extends('modules.gestion-practicas.layout')

@section('title', 'Práctica de '.$estudiante)
@section('page-label', 'Práctica de '.$estudiante)

@section('content')
    <header class="quality-page-heading">
        <div><h1>Práctica de {{ $estudiante }}</h1><p>{{ $empresa }}</p></div>
        <div class="gp-actions">
            @include('modules.gestion-practicas.partials.volver', ['ruta' => route('gestion-practicas.revision.practicas.index'), 'texto' => 'validar prácticas'])
            @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
        </div>
    </header>

    <div class="gp-stack">
        <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
            <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Datos de la práctica</h2></div></header>
            <div class="quality-compact-list">
                <article><div><small>Área</small><strong>{{ $practica->area }}</strong></div></article>
                <article><div><small>Horas planificadas</small><strong>{{ $practica->horas_totales }}</strong></div></article>
                <article><div><small>Inicio</small><strong>{{ $practica->fecha_inicio->format('d/m/Y') }}</strong></div></article>
                <article><div><small>Fin</small><strong>{{ $practica->fecha_fin->format('d/m/Y') }}</strong></div></article>
                <article><div><small>Docente supervisor</small><strong>{{ $supervisor ?? 'Sin asignar' }}</strong></div></article>
                @if ($practica->estado === Practica::FINALIZADA)
                    <article><div><small>Nota final</small><strong>{{ $practica->nota_final }} ({{ $practica->resultado }})</strong></div></article>
                @endif
            </div>
            <div class="gp-body"><h3>Funciones</h3><p class="gp-text">{{ $practica->funciones }}</p></div>
        </section>

        <section class="quality-panel" aria-labelledby="docs-titulo">
            <header class="quality-panel-heading"><div><h2 id="docs-titulo">Documentos</h2><p>Revise la carta de aceptación y los demás archivos.</p></div></header>
            @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => true])
        </section>

        @if ($practica->estado === Practica::EN_REVISION)
            <section class="quality-panel" aria-labelledby="decision-titulo">
                <header class="quality-panel-heading"><div><h2 id="decision-titulo">¿La información es válida?</h2><p>Al validar, la práctica pasa a «en curso» con el docente asignado.</p></div></header>
                <div class="gp-body gp-stack">
                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.validar', $practica) }}" class="gp-stack">
                        @csrf
                        @include('modules.gestion-practicas.partials.campo', ['name' => 'id_docente', 'label' => 'Docente supervisor', 'tipo' => 'select', 'opciones' => $docentes, 'vacio' => 'Seleccione un docente…'])
                        <div><button class="institutional-primary">Validar y poner en curso</button></div>
                    </form>
                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.observar', $practica) }}" class="gp-stack">
                        @csrf
                        @include('modules.gestion-practicas.partials.campo', ['name' => 'descripcion', 'label' => 'Observaciones para el estudiante', 'tipo' => 'textarea', 'filas' => 3, 'extra' => ['placeholder' => 'Indique qué debe corregir el estudiante']])
                        <div><button class="institutional-secondary is-danger">Devolver con observaciones</button></div>
                    </form>
                </div>
            </section>
        @endif

        @if ($observaciones->isNotEmpty())
            <section class="quality-panel" aria-labelledby="obs-titulo">
                <header class="quality-panel-heading"><div><h2 id="obs-titulo">Historial de observaciones</h2></div></header>
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
