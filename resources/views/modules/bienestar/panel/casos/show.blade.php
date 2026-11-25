@extends('modules.bienestar.layout')
@section('title', 'Caso #'.$caso->id_caso.' · Bienestar')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.panel.casos.index'), 'texto' => 'Casos'])@endsection
@section('page-label', 'Caso #'.$caso->id_caso)
@section('content')
<div class="space-y-6">
    <div class="encabezado"><div>
        <h1>{{ $caso->estudiante?->persona?->nombres }} {{ $caso->estudiante?->persona?->apellido_paterno }} {{ $caso->estudiante?->persona?->apellido_materno }}</h1>
        <p>Código {{ $caso->estudiante?->codigo_universitario }} · {{ $caso->tipoCaso?->nombre }}</p>
    </div></div>
    <div class="card-unt-editorial p-4 text-sm">{{ $caso->descripcion }}</div>
    @foreach ($caso->alertas as $a)
        <a class="fila card-unt" href="{{ route('bienestar.panel.atender', $a) }}"><div><b>Alerta #{{ $a->id_alerta }} · {{ ucfirst(str_replace('_', ' ', $a->regla)) }}</b><small>{{ $a->curso }} · {{ $a->periodo }}</small></div><span aria-hidden="true">&rarr;</span></a>
    @endforeach
    @include('modules.bienestar.panel._caso')
</div>
@endsection
