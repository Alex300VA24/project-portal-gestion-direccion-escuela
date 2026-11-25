<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 9mm; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 7.5pt; color: #17224d; }
        .pagina { page-break-after: always; }
        .pagina:last-child { page-break-after: auto; }
        .encabezado { height: 23mm; border-bottom: 1px solid #9fb5c7; margin-bottom: 3mm; }
        .logo { width: 14mm; height: 14mm; float: left; margin-right: 3mm; }
        h1 { font-size: 11pt; margin: 0 0 1.5mm; color: #193d5b; }
        h2 { font-size: 9pt; margin: 3mm 0 2mm; color: #193d5b; }
        .subtitulo { font-size: 7pt; margin: 1mm 0; }
        .resumen { clear: both; padding-top: 2mm; font-weight: bold; font-size: 8pt; }
        .cabecera-dias { height: 8mm; background: #193d5b; color: white; position: relative; }
        .rotulo { position: absolute; top: 2mm; text-align: center; font-weight: bold; font-size: 8pt; }
        .cuadricula { height: 129mm; position: relative; border: 1px solid #9fb5c7; border-top: none; }
        .dia { position: absolute; top: 0; bottom: 0; border-left: 1px solid #9fb5c7; }
        .linea-hora { position: absolute; left: 0; right: 0; border-top: 1px solid #d6dfe7; height: 0; }
        .hora { position: absolute; left: 1mm; width: 11%; color: #425566; font-size: 7pt; }
        .actividad { position: absolute; box-sizing: border-box; border: 1px solid #7c96aa; padding: 1mm;
            overflow: hidden; font-size: 6.3pt; line-height: 1.16; color: #14283a; }
        .actividad-item { padding-bottom: .4mm; margin-bottom: .4mm; border-bottom: 1px solid rgba(40,60,80,.22); }
        .actividad-item:last-child { border: none; margin-bottom: 0; }
        .actividad strong { font-size: 6.4pt; }
        .nota { font-size: 7pt; color: #425566; margin-top: 2.5mm; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #9fb5c7; text-align: left; padding: 2.2mm; overflow-wrap: break-word; }
        th { background: #e8eee9; }
        tr { page-break-inside: avoid; }
        .detalle { font-size: 7.3pt; }
        .detalle td { padding: 1.6mm; }
    </style>
</head>
<body>
@foreach ($gruposExportacion as $grupo)
    @php($paginasDias = count($grupo['dias']) <= 4 ? [$grupo['dias']] : array_chunk($grupo['dias'], 3))
    @foreach ($paginasDias as $paginaIndice => $diasPagina)
        @php($columnaAncho = 87 / count($diasPagina))
        <section class="pagina">
            <header class="encabezado">
                @if ($logoInstitucional)
                    <img class="logo" src="{{ $logoInstitucional }}" alt="">
                @endif
                <h1>UNIVERSIDAD NACIONAL DE TRUJILLO</h1>
                <p class="subtitulo">{{ $grupo['facultad'] }} · {{ $grupo['escuela'] }}</p>
                <p class="subtitulo">Periodo {{ $periodoSeleccionado->codigo ?? 'sin especificar' }} · Plan {{ $grupo['plan_nombre'] }} · Estado {{ $seleccion->estado ?? 'No registrado' }}</p>
                <p class="resumen">HORARIO ACADÉMICO · CICLO {{ $grupo['ciclo'] }} · SECCIÓN {{ $grupo['seccion'] }}@if (count($paginasDias) > 1) · Parte {{ $paginaIndice + 1 }}/{{ count($paginasDias) }}@endif</p>
            </header>

            <div class="cabecera-dias">
                <span class="rotulo" style="left:0;width:13%">HORA</span>
                @foreach ($diasPagina as $indice => $dia)
                    <span class="rotulo" style="left:{{ 13 + $indice * $columnaAncho }}%;width:{{ $columnaAncho }}%">{{ $dia }}</span>
                @endforeach
            </div>
            <div class="cuadricula">
                @for ($minutos = $grupo['desde']; $minutos <= $grupo['hasta']; $minutos += 60)
                    @php($pos = 100 * ($minutos - $grupo['desde']) / ($grupo['hasta'] - $grupo['desde']))
                    <div class="linea-hora" style="top:{{ $pos }}%"></div>
                    @if ($minutos < $grupo['hasta'])
                        <span class="hora" style="top:{{ $pos + .4 }}%">{{ \App\Modules\GestionHorarios\Services\DistribucionHoraria::hora($minutos) }}–{{ \App\Modules\GestionHorarios\Services\DistribucionHoraria::hora($minutos + 60) }}</span>
                    @endif
                @endfor
                @foreach ($diasPagina as $indice => $dia)
                    <div class="dia" style="left:{{ 13 + $indice * $columnaAncho }}%;width:{{ $columnaAncho }}%">
                        @foreach ($grupo['tarjetas'][$dia] as $tarjeta)
                            <div class="actividad" style="top:{{ $tarjeta['top'] }}%;height:{{ $tarjeta['alto'] }}%;left:{{ 100 * $tarjeta['lane'] / $tarjeta['slots'] }}%;width:{{ 100 / $tarjeta['slots'] }}%;background:#{{ $tarjeta['items'][0]['color'] }}">
                                @foreach ($tarjeta['items'] as $sesion)
                                    <div class="actividad-item">
                                        <strong>{{ $sesion['codigo'] }} · {{ $sesion['curso'] }}</strong>
                                        <div>{{ $sesion['tipo'] }}@if ($sesion['grupo']) · Grupo {{ $sesion['grupo'] }}@endif</div>
                                        <div>{{ $sesion['ambiente'] }}</div>
                                        <div>{{ $sesion['docente'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="nota">Los bloques conservan sus horarios exactos; las coincidencias se distribuyen en carriles. El detalle íntegro figura en la página siguiente. Receso académico: no confirmado en los datos disponibles.</p>
        </section>
    @endforeach

    <section class="pagina detalle">
        <header class="encabezado">
            @if ($logoInstitucional)<img class="logo" src="{{ $logoInstitucional }}" alt="">@endif
            <h1>UNIVERSIDAD NACIONAL DE TRUJILLO</h1>
            <p class="subtitulo">Periodo {{ $periodoSeleccionado->codigo ?? 'sin especificar' }} · Ciclo {{ $grupo['ciclo'] }} · Sección {{ $grupo['seccion'] }}</p>
            <p class="resumen">DETALLE DE SESIONES Y RELACIÓN DE ASIGNATURAS</p>
        </header>
        <h2>Detalle completo de sesiones</h2>
        <table>
            <thead><tr><th>Día</th><th>Horario</th><th>Asignatura</th><th>Tipo / grupo</th><th>Docente</th><th>Ambiente</th></tr></thead>
            <tbody>
                @foreach ($grupo['sesiones'] as $s)
                    <tr>
                        <td>{{ $s['dia'] }}</td><td>{{ $s['horario'] }}</td>
                        <td>{{ $s['codigo'] }} · {{ $s['curso'] }}</td>
                        <td>{{ $s['tipo'] }}@if ($s['grupo']) · {{ $s['grupo'] }}@endif</td>
                        <td>{{ $s['docente'] }}</td><td>{{ $s['ambiente'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <h2>Relación de asignaturas y docentes</h2>
        <table>
            <thead><tr><th style="width:19%">Código</th><th>Asignatura</th><th>Docente</th></tr></thead>
            <tbody>
                @foreach (collect($grupo['sesiones'])->unique(fn ($s) => json_encode([$s['codigo'], $s['curso'], $s['docente']], JSON_THROW_ON_ERROR))->sortBy('curso') as $s)
                    <tr><td>{{ $s['codigo'] }}</td><td>{{ $s['curso'] }}</td><td>{{ $s['docente'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endforeach
</body>
</html>
