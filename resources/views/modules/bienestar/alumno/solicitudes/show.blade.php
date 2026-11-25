@extends('modules.bienestar.layout')
@section('title', 'Solicitud #'.$solicitud->id_solicitud.' · Bienestar')
@section('migas')@include('modules.bienestar.partials.miga', ['url' => route('bienestar.alumno.solicitudes.index'), 'texto' => 'Mis solicitudes'])@endsection
@section('page-label', 'Solicitud #'.$solicitud->id_solicitud)
@section('content')
<div class="space-y-6 max-w-5xl">
    <div class="encabezado">
        <div><h1>{{ $solicitud->tipoSolicitud?->nombre ?? 'Solicitud' }}</h1><p>Enviada el {{ $solicitud->solicitada_en?->setTimezone('America/Lima')->format('d/m/Y H:i') }}</p></div>
        @if ($solicitud->estado === 'observada')<button type="button" class="btn-unt-secondary" data-abrir-modal="m-subsanar-{{ $solicitud->id_solicitud }}">Corregir lo observado</button>@endif
    </div>
    @include('modules.bienestar.partials.etapas')
    @if ($solicitud->estado === 'observada' && $solicitud->observacion_general_secretaria)<div class="aviso aviso-error"><b>Observación de Secretaría:</b> {{ $solicitud->observacion_general_secretaria }}</div>@endif
    @if ($solicitud->observacion_dictamen)<div class="card-unt-editorial p-4 text-sm"><b>Dictamen de Bienestar:</b> {{ $solicitud->observacion_dictamen }}</div>@endif
    <div class="card-unt p-5 text-sm"><h2 class="subtitulo">Tu justificación</h2><p>{{ $solicitud->motivo }}</p></div>
    @include('modules.bienestar.partials.documentos', ['descarga' => null])
    @include('modules.bienestar.partials.historial')
</div>
@includeWhen($solicitud->estado === 'observada', 'modules.bienestar.alumno.solicitudes._subsanar')
@endsection
