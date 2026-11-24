@php
    $titulos = [
        'programa' => 'Programa de nivelación de competencias',
        'informe' => 'Informe final de nivelación',
        'personal' => 'Resumen personal de nivelación',
    ];
    $fecha = fn ($valor) => \Illuminate\Support\Carbon::parse($valor)->setTimezone('America/Lima')->format('d/m/Y');
    $fechaCalendario = fn ($valor) => \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $valor, 'America/Lima')->format('d/m/Y');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulos[$tipo] }}</title>
@include('modules.seguimiento-estudiante.pdf.estilos')
</head>
<body>
<header>
    <table class="letterhead">
        <tr>
            <td style="width: 10%"><img class="crest" src="{{ $crestPath }}" alt="Escudo UNT"></td>
            <td style="width: 70%">
                <div class="university">UNIVERSIDAD NACIONAL DE TRUJILLO</div>
                @if ($programa->escuela)<div class="school">{{ $programa->escuela }}</div>@endif
                <div class="unit">Seguimiento al Estudiante</div>
            </td>
            <td class="header-side">{{ $programa->periodo }}</td>
        </tr>
    </table>
</header>
<footer>
    <div class="footer-note">Emitido el {{ now('America/Lima')->format('d/m/Y H:i') }} · Hora de Lima<br>Documento de consulta generado desde los registros del portal.</div>
</footer>

<div class="document-title">
    <h1>{{ $titulos[$tipo] }}</h1>
    <p>{{ $programa->nombre }}</p>
</div>
<table class="metadata">
    <tbody>
    @if ($tipo === 'personal')
        <tr>
            <td><span class="label">ESTUDIANTE</span><span class="value">{{ $programa->nombres }} {{ $programa->apellido_paterno }} {{ $programa->apellido_materno }}</span></td>
            <td><span class="label">CÓDIGO UNIVERSITARIO</span><span class="value">{{ $programa->codigo }}</span></td>
        </tr>
        <tr>
            <td><span class="label">GRUPO</span><span class="value normal">{{ $programa->grupo }}</span></td>
            <td><span class="label">PARTICIPACIÓN</span><span class="value normal">{{ $programa->participacion }}</span></td>
        </tr>
    @endif
        <tr>
            <td><span class="label">FECHAS DEL PROGRAMA</span><span class="value normal">{{ $fechaCalendario($programa->fecha_inicio) }} al {{ $fechaCalendario($programa->fecha_fin) }}</span></td>
            <td><span class="label">ESTADO DEL PROGRAMA</span><span class="value normal">{{ $programa->estado }}</span></td>
        </tr>
    </tbody>
</table>

@if ($tipo === 'personal')
    <h2>Sesiones y asistencia</h2>
    @include('modules.seguimiento-estudiante.nivelacion.partials.pdf-schedule', ['personal' => true])
    <h2>Calificaciones publicadas</h2>
    @if ($notas->isNotEmpty())
        <table>
            <thead><tr><th style="width: 42%">Criterio</th><th>Peso</th><th>Nota</th><th>Publicación</th></tr></thead>
            <tbody>
            @foreach ($notas as $nota)
                <tr><td>{{ $nota->nombre }}</td><td>{{ $nota->peso }} %</td><td>{{ $nota->nota }}</td><td>{{ $fecha($nota->publicada_en) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>Aún no se han publicado calificaciones para esta inscripción.</p>
    @endif
@elseif ($tipo === 'programa')
    @if ($programa->descripcion)
        <h2>Descripción</h2>
        <p>{{ $programa->descripcion }}</p>
    @endif
    <h2>Grupos y docentes</h2>
    <table>
        <thead><tr><th style="width: 20%">Grupo</th><th style="width: 60%">Docente responsable</th><th>Capacidad</th></tr></thead>
        <tbody>
        @foreach ($grupos as $grupo)
            <tr><td>{{ $grupo->codigo }}</td><td>{{ $grupo->nombres }} {{ $grupo->apellido_paterno }} {{ $grupo->apellido_materno }}</td><td>{{ $grupo->capacidad ?? 'Sin dato' }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <h2>Programación de sesiones</h2>
    @include('modules.seguimiento-estudiante.nivelacion.partials.pdf-schedule', ['personal' => false])
    <h2>Criterios de evaluación</h2>
    @if ($criterios->isNotEmpty())
        <table>
            <thead><tr><th>Criterio</th><th style="width: 25%">Peso</th></tr></thead>
            <tbody>
            @foreach ($criterios as $criterio)
                <tr><td>{{ $criterio->nombre }}</td><td>{{ $criterio->peso }} %</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>Aún no hay criterios registrados.</p>
    @endif
@else
    <p class="reference">Cierre publicado el {{ $fecha($programa->cierre_publicado_en) }}.</p>
    @foreach ($cierre as $titulo => $contenido)
        <h2>{{ $titulo }}</h2>
        <p>{{ $contenido }}</p>
    @endforeach
@endif
</body>
</html>
