@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Inicio</a> › Mi proyecto</p>
            <h1>Datos generales del proyecto (F1)</h1>
        </div>
        @if ($proyecto->id_archivo_documento)
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $proyecto->uuid]) }}" target="_blank" rel="noopener">Ver documento F1 (PDF)</a>
        @endif
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    @include('modules.tesis-proyecto-tesis.partials.detalle-f1')
@endsection
