@extends('modules.bienestar.layout')
@section('title', 'Solicitud #'.$solicitud->id_solicitud.' · Bienestar')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.panel.solicitudes.index'), 'texto' => 'Solicitudes'])@endsection
@section('page-label', 'Solicitud #'.$solicitud->id_solicitud)
@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="encabezado">
        <div><h1>{{ $solicitud->tipoSolicitud?->nombre ?? 'Solicitud' }}</h1>
        <p>{{ $solicitud->estudiante?->persona?->nombres }} {{ $solicitud->estudiante?->persona?->apellido_paterno }} · Código {{ $solicitud->estudiante?->codigo_universitario }} · {{ $solicitud->solicitada_en?->format('d/m/Y') }}</p></div>
        @if ($solicitud->puedeDictaminarse())
            <div class="acciones">
                <button type="button" class="btn-unt-peligro" data-abrir-modal="m-rechazar" data-accion="{{ route('bienestar.panel.solicitudes.evaluar', $solicitud) }}">Rechazar</button>
                <button type="button" class="btn-unt-primary" data-abrir-modal="m-aprobar" data-accion="{{ route('bienestar.panel.solicitudes.evaluar', $solicitud) }}">Aprobar</button>
            </div>
        @endif
    </div>
    @include('modules.bienestar.partials.etapas')
    <div class="card-unt p-5 text-sm"><h2 class="subtitulo">Justificación del estudiante</h2><p>{{ $solicitud->motivo }}</p></div>
    @if ($solicitud->observacion_general_secretaria)<div class="card-unt-editorial p-4 text-sm"><b>Nota de Secretaría:</b> {{ $solicitud->observacion_general_secretaria }}</div>@endif
    @if ($solicitud->observacion_dictamen)<div class="card-unt-editorial p-4 text-sm"><b>Dictamen:</b> {{ $solicitud->observacion_dictamen }}</div>@endif
    @include('modules.bienestar.partials.documentos', ['descarga' => 'bienestar.panel.documentos.descargar'])
    @include('modules.bienestar.partials.historial')
</div>
@include('modules.bienestar.panel.solicitudes._modales')
@endsection
