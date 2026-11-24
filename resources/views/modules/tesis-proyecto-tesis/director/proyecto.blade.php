@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.director.proyectos'), 'a' => 'Todos los proyectos'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.director.proyectos') }}">Todos los proyectos</a> › {{ $proyecto->codigo }}</p>
            <h1>Ficha del proyecto</h1>
            <p>Datos del formato F1, cronograma, recursos y presupuesto, e historial del proceso.</p>
        </div>
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    @include('modules.tesis-proyecto-tesis.partials.detalle-f1')

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Historial</h2></div>
        <div class="tz-panel-body">@include('modules.tesis-proyecto-tesis.partials.linea-tiempo')</div>
    </section>
@endsection
