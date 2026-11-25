<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia personal de Tutoría</title>
    @include('modules.seguimiento-estudiante.pdf.estilos')
</head>
<body>
<header>
    <table class="letterhead">
        <tr>
            <td style="width: 10%"><img class="crest" src="{{ $crestPath }}" alt="Escudo UNT"></td>
            <td style="width: 70%">
                <div class="university">UNIVERSIDAD NACIONAL DE TRUJILLO</div>
                <div class="school">{{ $escuela }}</div>
                <div class="unit">Seguimiento al Estudiante</div>
            </td>
            <td class="header-side">{{ $atencion->periodo_codigo }}</td>
        </tr>
    </table>
</header>
<footer>
    <div class="footer-note">Emitido el {{ now('America/Lima')->format('d/m/Y H:i') }} · Hora de Lima<br>Extracto individual de consulta del expediente. Las firmas institucionales se emiten por separado.</div>
</footer>
<div class="document-title"><h1>Constancia personal de Tutoría</h1></div>
<table class="metadata">
    <tr>
        <td><span class="label">ESTUDIANTE</span><span class="value">{{ $perfil->persona->nombres }} {{ $perfil->persona->apellido_paterno }} {{ $perfil->persona->apellido_materno }}</span></td>
        <td><span class="label">CÓDIGO UNIVERSITARIO</span><span class="value">{{ $perfil->codigo_universitario }}</span></td>
    </tr>
    <tr>
        <td><span class="label">TUTOR</span><span class="value normal">{{ $tutor->nombres }} {{ $tutor->apellido_paterno }} {{ $tutor->apellido_materno }}</span></td>
        <td><span class="label">SESIÓN ATENDIDA</span><span class="value normal">{{ \Illuminate\Support\Carbon::parse($atencion->fecha_hora)->setTimezone('America/Lima')->format('d/m/Y H:i') }}</span></td>
    </tr>
</table>
<h2>Motivo de atención</h2>
<p>{{ $atencion->descripcion ?? 'Sin motivo registrado.' }}</p>
<h2>Acuerdos</h2>
<p>{{ $atencion->acuerdos ?? 'Sin acuerdos registrados.' }}</p>
@if ($atencion->id_compromiso)
    <p>Compromiso {{ $atencion->cumplido ? 'cumplido' : 'pendiente' }}.</p>
@endif
</body>
</html>
