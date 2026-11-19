@php
    use App\Modules\GestionPracticas\Support\Catalogos;

    $editando = $convocatoria->exists;
    $titulo = $editando ? 'Corregir convocatoria' : 'Nueva convocatoria';
@endphp
@extends('modules.gestion-practicas.layout')

@section('title', $titulo)
@section('page-label', $titulo)

@section('content')
    <header class="quality-page-heading">
        <div>
            <h1>{{ $titulo }}</h1>
            <p>{{ $editando ? 'Corrija lo que indicó el administrador y vuelva a enviarla a revisión.' : 'Se guarda como borrador; podrá enviarla a revisión cuando esté lista.' }}</p>
        </div>
    </header>

    <form method="POST" action="{{ $editando ? route('gestion-practicas.empresa.convocatorias.update', $convocatoria) : route('gestion-practicas.empresa.convocatorias.store') }}">
        @csrf
        @if ($editando) @method('PUT') @endif

        <section class="quality-panel" aria-labelledby="form-titulo">
            <header class="quality-panel-heading"><div><h2 id="form-titulo">Datos de la convocatoria</h2></div></header>
            <div class="gp-form is-two">
                @include('modules.gestion-practicas.partials.campo', ['name' => 'titulo', 'label' => 'Título', 'valor' => $convocatoria->titulo, 'span' => true])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'valor' => $convocatoria->descripcion, 'span' => true])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'requisitos', 'label' => 'Requisitos', 'tipo' => 'textarea', 'valor' => $convocatoria->requisitos, 'span' => true])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'enlace_postulacion', 'label' => 'Enlace de postulación', 'tipo' => 'url', 'valor' => $convocatoria->enlace_postulacion, 'span' => true, 'extra' => ['placeholder' => 'https://']])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'tipo_practica', 'label' => 'Tipo de práctica', 'tipo' => 'select', 'valor' => $convocatoria->tipo_practica, 'opciones' => Catalogos::TIPOS_PRACTICA])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'modalidad', 'label' => 'Modalidad', 'tipo' => 'select', 'valor' => $convocatoria->modalidad, 'opciones' => Catalogos::MODALIDADES])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'vacantes', 'label' => 'Vacantes', 'tipo' => 'number', 'valor' => $convocatoria->vacantes ?? 1, 'extra' => ['min' => 1]])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'fecha_cierre', 'label' => 'Fecha de cierre', 'tipo' => 'date', 'valor' => $convocatoria->fecha_cierre?->format('Y-m-d')])
            </div>
        </section>

        <div class="gp-actions" style="margin-top: 18px">
            <button class="institutional-primary">{{ $editando ? 'Guardar cambios' : 'Crear borrador' }}</button>
            <a class="institutional-secondary" href="{{ route('gestion-practicas.empresa.convocatorias.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
