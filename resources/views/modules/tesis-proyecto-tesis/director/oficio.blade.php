@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.director.panel'), 'a' => 'Panel general'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.director.panel') }}">Panel general</a> › Oficio {{ $oficio['numero'] }}</p>
            <h1>Oficio de solicitud de asesor</h1>
            <p>El sistema registra la solicitud; el envío al Decanato se hace por el canal oficial. Imprime el oficio, fírmalo y envíalo.</p>
        </div>
        <div class="tz-actions">
            <button type="button" class="tz-btn tz-btn-primary" onclick="window.print()">Imprimir oficio</button>
            @if ($oficio['archivo'])
                <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['oficio', $oficio['uuid']]) }}" target="_blank" rel="noopener">Constancia en PDF</a>
            @endif
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.director.proyecto', $proyecto) }}">Ver ficha del proyecto</a>
        </div>
    </div>

    <article class="tz-doc" aria-label="Oficio N.° {{ $oficio['numero'] }}">
        <p class="tz-doc-center"><strong>UNIVERSIDAD NACIONAL DE TRUJILLO</strong><br>Facultad de Ciencias Físicas y Matemáticas<br>Escuela de Ingeniería Informática</p>
        <p class="tz-doc-derecha">Trujillo, {{ $oficio['fecha']->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>
        <h2>OFICIO N.° {{ $oficio['numero'] }}</h2>
        <p>Señor(a):<br><strong>{{ $oficio['destinatario'] }}</strong><br>{{ $oficio['area'] }}<br>Presente.-</p>
        <p><strong>Asunto:</strong> {{ $oficio['asunto'] }}</p>
        <table class="tz-doc-tabla">
            <tr><th scope="row">Proyecto</th><td>{{ $oficio['proyecto']['codigo'] }} · {{ $oficio['proyecto']['titulo'] }}</td></tr>
            <tr><th scope="row">Estudiante(s)</th><td>@foreach ($oficio['estudiantes'] as $e)<div>{{ $e }}</div>@endforeach</td></tr>
            <tr><th scope="row">Línea de investigación</th><td>{{ $oficio['linea'] }}</td></tr>
            <tr><th scope="row">Docente sugerido</th><td>{{ $oficio['sugerido'] ?? 'Sin sugerencia: el Decanato decide' }}</td></tr>
        </table>
        <p>{{ $oficio['detalle'] }}</p>
        <p>Atentamente,</p>
        <div class="tz-firmas tz-firma-unica">
            <div>{{ $oficio['director'] }}<br>Director(a) de la Escuela de Ingeniería Informática</div>
        </div>
    </article>
@endsection
