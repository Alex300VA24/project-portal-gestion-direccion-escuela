@php
    $s = $snapshot;
    $zona = $s['zona_horaria'] ?? 'America/Lima';
    $fecha = fn ($iso) => \Illuminate\Support\Carbon::parse($iso)->timezone($zona)->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de prematrícula {{ $s['codigo_constancia'] }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #141a33; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 18mm; border-bottom: 2px solid #17224d; }
        header img { height: 15mm; float: left; margin-right: 10px; }
        header .inst { padding-top: 2mm; line-height: 1.35; }
        header .inst strong { font-size: 12px; color: #17224d; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 10mm; font-size: 8.5px; color: #4a5170; border-top: 1px solid #dfe3ea; padding-top: 2mm; }
        h1 { margin: 4mm 0 1mm; font-size: 17px; color: #17224d; }
        .sub { margin: 0 0 5mm; color: #4a5170; }
        table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 3px 0; vertical-align: top; }
        .datos td.k { width: 34%; color: #4a5170; }
        .cursos { margin-top: 5mm; }
        .cursos th { background: #17224d; color: #fff; padding: 5px 6px; text-align: left; font-size: 9.5px; }
        .cursos td { padding: 5px 6px; border-bottom: 1px solid #dfe3ea; }
        .cursos .n { text-align: right; }
        .cursos tfoot td { font-weight: bold; border-top: 1.5px solid #17224d; border-bottom: 0; }
        .nota { margin-top: 6mm; padding: 3mm 4mm; background: #eef0f3; border-left: 3px solid #0d7a50; font-size: 9.5px; line-height: 1.45; }
        .codigo { font-family: 'DejaVu Sans Mono', monospace; }
    </style>
</head>
<body>
    <header>
        @if ($escudo)<img src="{{ $escudo }}" alt="">@endif
        <div class="inst"><strong>{{ $s['institucion']['universidad'] }}</strong><br>{{ $s['institucion']['facultad'] }}<br>{{ $s['institucion']['escuela'] }}</div>
    </header>
    <footer>
        Código de constancia: <span class="codigo">{{ $s['codigo_constancia'] }}</span> · Revisión {{ $s['numero_revision'] }} · Emitida el {{ $fecha($s['enviada_en']) }} (hora de Lima) · Portal Gestión Dirección — Pre Matrícula
    </footer>

    <h1>Constancia de prematrícula</h1>
    <p class="sub">Período académico {{ $s['periodo']['codigo'] }} · Ventana {{ Str::lower($s['ventana']['tipo']) }}</p>

    <table class="datos">
        <tr><td class="k">Estudiante</td><td>{{ $s['estudiante']['apellidos'] }}, {{ $s['estudiante']['nombres'] }}</td></tr>
        <tr><td class="k">Código universitario</td><td class="codigo">{{ $s['estudiante']['codigo_universitario'] }}</td></tr>
        <tr><td class="k">Fecha de registro</td><td>{{ $fecha($s['enviada_en']) }}</td></tr>
        <tr><td class="k">Créditos</td><td>{{ $s['creditos_totales'] }} de un máximo de {{ $s['creditos_maximos'] }}</td></tr>
    </table>

    <table class="cursos">
        <thead><tr><th style="width: 14%">Código</th><th>Curso</th><th style="width: 9%">Ciclo</th><th style="width: 11%" class="n">Créditos</th><th style="width: 20%">Prerrequisitos</th></tr></thead>
        <tbody>
            @foreach ($s['detalles'] as $d)
                <tr><td class="codigo">{{ $d['codigo'] }}</td><td>{{ $d['nombre'] }}</td><td>{{ $d['ciclo'] }}</td><td class="n">{{ $d['creditos'] }}</td><td>{{ implode(', ', $d['prerrequisitos']) ?: '—' }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">{{ count($s['detalles']) }} cursos</td><td class="n">{{ $s['creditos_totales'] }}</td><td></td></tr></tfoot>
    </table>

    <div class="nota">
        Esta constancia registra la intención de matrícula del estudiante y sirve para planificar la oferta académica. No es una matrícula. Si el estudiante corrige su prematrícula en una ventana extraordinaria, se emite una nueva revisión y esta deja de estar vigente.
    </div>
</body>
</html>
