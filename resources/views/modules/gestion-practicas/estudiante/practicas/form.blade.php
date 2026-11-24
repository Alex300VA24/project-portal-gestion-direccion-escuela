@php
    $editando = $practica->exists;
    $titulo = $editando ? 'Corregir datos de la práctica' : 'Registrar mi práctica';
@endphp
@extends('modules.gestion-practicas.layout')

@section('title', $titulo)
@section('page-label', $titulo)

@section('content')
    <header class="quality-page-heading">
        <div>
            <h1>{{ $titulo }}</h1>
            <p>@if ($convocatoria)Convocatoria: {{ $convocatoria->titulo }}. @endif{{ $editando ? 'Corrija los datos y vuelva a enviarlos a revisión.' : 'Adjunte la carta de aceptación de la empresa para que el administrador pueda validarla.' }}</p>
        </div>
    </header>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editando ? route('gestion-practicas.estudiante.practicas.update', $practica) : route('gestion-practicas.estudiante.practicas.store', $postulacion) }}">
        @csrf
        @if ($editando) @method('PUT') @endif

        <section class="quality-panel" aria-labelledby="form-titulo">
            <header class="quality-panel-heading"><div><h2 id="form-titulo">Datos de la práctica</h2></div></header>
            <div class="gp-form is-two">
                @include('modules.gestion-practicas.partials.campo', ['name' => 'area', 'label' => 'Área de la práctica', 'valor' => $practica->area, 'span' => true, 'ayuda' => 'Por ejemplo: software, redes o seguridad.'])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'funciones', 'label' => 'Funciones a desarrollar', 'tipo' => 'textarea', 'valor' => $practica->funciones, 'span' => true])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'fecha_inicio', 'label' => 'Fecha de inicio', 'tipo' => 'date', 'valor' => $practica->fecha_inicio?->format('Y-m-d')])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'fecha_fin', 'label' => 'Fecha de fin', 'tipo' => 'date', 'valor' => $practica->fecha_fin?->format('Y-m-d')])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'horas_totales', 'label' => 'Horas planificadas', 'tipo' => 'number', 'valor' => $practica->horas_totales, 'extra' => ['min' => 1]])
                @include('modules.gestion-practicas.partials.campo', ['name' => 'id_periodo', 'label' => 'Periodo académico', 'tipo' => 'select', 'valor' => $practica->id_periodo, 'opciones' => $periodos, 'vacio' => 'Sin especificar', 'requerido' => false])
                @include('modules.gestion-practicas.partials.campo', [
                    'name' => 'carta', 'tipo' => 'file', 'requerido' => ! $editando, 'span' => true,
                    'label' => $editando ? 'Reemplazar carta de aceptación (opcional)' : 'Carta de aceptación',
                    'ayuda' => 'PDF o imagen (JPG, PNG), máximo 5 MB.', 'extra' => ['accept' => '.pdf,.jpg,.jpeg,.png'],
                ])
            </div>
        </section>

        <div class="gp-actions" style="margin-top: 18px">
            <button class="institutional-primary">{{ $editando ? 'Guardar cambios' : 'Registrar práctica' }}</button>
            <a class="institutional-secondary" href="{{ route('gestion-practicas.estudiante.practicas.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
