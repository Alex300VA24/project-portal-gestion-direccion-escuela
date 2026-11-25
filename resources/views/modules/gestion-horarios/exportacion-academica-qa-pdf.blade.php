<!doctype html>
<html lang="es"><head><meta charset="utf-8"><style>
@page { size:A4 landscape; margin:8mm; }
body { font-family:DejaVu Sans,sans-serif; font-size:8pt; color:#14283a; margin:0; }
section { page-break-before:always; } section:first-child { page-break-before:auto; }
header { border-bottom:1px solid #9fb5c7; padding-bottom:2mm; margin-bottom:3mm; }
h1 { font-size:12pt; margin:0; color:#193d5b; } h2 { font-size:10pt; margin:2mm 0; color:#193d5b; }
p { margin:1mm 0; } .logo { float:left; max-width:14mm; max-height:14mm; margin-right:4mm; }
table { border-collapse:collapse; width:100%; table-layout:fixed; } thead { display:table-header-group; }
th,td { border:0.5pt solid #9fb5c7; padding:1.4mm; vertical-align:top; overflow-wrap:break-word; }
th { background:#193d5b; color:white; } tr { page-break-inside:avoid; } .hora { width:12%; background:#f2f6fa; font-weight:bold; }
.tarjeta { padding:1mm; margin-bottom:1mm; font-size:7.5pt; line-height:1.2; } .receso { background:#dfe6eb;text-align:center;font-weight:bold; }
</style></head><body>
@foreach ($gruposExportacion as $grupo)
<section>
<header>
@if ($logoInstitucional)<img class="logo" src="{{ $logoInstitucional }}" alt="">@endif
<h1>UNIVERSIDAD NACIONAL DE TRUJILLO</h1>
<p>{{ $grupo['facultad'] }} · {{ $grupo['escuela'] }}</p>
<p>Periodo {{ $periodoSeleccionado->codigo }} · {{ $seleccion->estado }}</p>
<h2>{{ $grupo['alcance'] ?: 'CICLO '.$grupo['ciclo'].' · SECCIÓN '.$grupo['seccion'] }}</h2>
</header>
<table><thead><tr><th colspan="{{ count($grupo['dias'])+1 }}">{{ $periodoSeleccionado->codigo }} · {{ $grupo['alcance'] ?: 'CICLO '.$grupo['ciclo'].' · SECCIÓN '.$grupo['seccion'] }}</th></tr><tr><th style="width:12%">HORA</th>@foreach ($grupo['dias'] as $dia)<th>{{ $dia }}</th>@endforeach</tr></thead><tbody>
@foreach (\App\Modules\GestionHorarios\Services\DistribucionHoraria::filasAcademicas($grupo,$bloquesPeriodo->all()) as $fila)
@php($subfilas = max(1, ...array_map('count', array_values($fila['activas']))))
@for ($i=0;$i<$subfilas;$i++)
<tr><td class="hora">{{ \App\Modules\GestionHorarios\Services\DistribucionHoraria::hora($fila['inicio']) }}–{{ \App\Modules\GestionHorarios\Services\DistribucionHoraria::hora($fila['fin']) }}</td>
@if ($fila['receso'])<td colspan="{{ count($grupo['dias']) }}" class="receso">RECESO</td>
@else
@foreach ($grupo['dias'] as $dia)<td>
@php($s = $fila['activas'][$dia][$i] ?? null)
@if ($s)
<div class="tarjeta" style="background:#{{ $s['color'] }}"><strong>{{ $s['codigo'] }} · {{ $s['curso'] }}</strong><br>{{ $s['tipo'] }} @if ($s['grupo']) · {{ $s['grupo'] }} @endif<br>{{ $s['horario'] }}<br>{{ $s['ambiente'] }}<br>{{ $s['docente'] }}@if ($grupo['alcance'])<br>{{ $s['alcance_sesion'] }}@endif</div>
@endif
</td>@endforeach
@endif
</tr>
@endfor
@endforeach
</tbody></table>
<p>Las sesiones de varias horas se muestran en cada franja que ocupan. Las coincidencias se presentan por separado.</p>
</section>
@endforeach
</body></html>
