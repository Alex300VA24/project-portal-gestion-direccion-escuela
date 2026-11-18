@extends('modules.seguimiento-egresado.layout')
@section('title', 'Indicadores I1 a I6')
@section('section_name', 'Indicadores I1 a I6')
@php
  $formato = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.');
  $conUnidad = fn (\App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador $r) => $r->valor === null ? $r->etiquetaEstado() : ($r->unidad === '%' ? $formato($r->valor).' %' : number_format($r->valor, 0).' '.$r->unidad);
  $fecha = fn (?string $f) => $f ? \Carbon\CarbonImmutable::parse($f)->format('d/m/Y') : '—';
  $baseNota = $indicadores['I1']->cohorte.' · '.$filtro->periodo().' · Datos hasta '.$fecha($filtro->fechaCorte());
  $coberturaTexto = fn (?float $c) => $c === null ? 'Cobertura sin datos' : 'Cobertura '.$formato($c).' %';
  $hayFiltros = $filtro->cohorteId || $filtro->horizonte || ! $filtro->esCorteDeHoy();
  $consulta = array_filter(['cohorte' => $filtro->cohorteId, 'horizonte' => $filtro->horizonte, 'corte' => $filtro->esCorteDeHoy() ? null : $filtro->fechaCorte()]);
@endphp
@push('styles')
  @include('modules.seguimiento-egresado.partials.estilos-graficos')
  <style>
    .sg-ind-filtros { grid-template-columns: repeat(3, minmax(0, 1fr)) auto; align-items: end; }
    .sg-ind-filtros .sg-actions { margin: 0; }
    .sg-modal.sg-modal-ancho { width: min(1120px, calc(100% - 32px)); }
    @media (max-width: 900px) { .sg-ind-filtros { grid-template-columns: minmax(0, 1fr); } }
  </style>
@endpush
@section('content')
<section class="sg-heading">
  <h1>Indicadores I1 a I6</h1>
</section>

<form class="sg-panel sg-bloque" method="get" action="{{ route('seguimiento-egresado.indicadores') }}" aria-label="Filtros del tablero">
  <div class="sg-filters sg-ind-filtros">
    <div class="sg-field">
      <label class="sg-label" for="cohorte">Promoción</label>
      <select class="sg-select" id="cohorte" name="cohorte">
        <option value="">Todas las promociones</option>
        @foreach ($cohortes as $c)
          <option value="{{ $c->id }}" @selected($filtro->cohorteId === (int) $c->id)>Promoción {{ $c->anio_ingreso }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="horizonte">Años desde el egreso</label>
      <select class="sg-select" id="horizonte" name="horizonte">
        <option value="">Todo el histórico</option>
        @foreach (\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::HORIZONTES as $h)
          <option value="{{ $h }}" @selected($filtro->horizonte === $h)>{{ $h }} años</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="corte">Datos hasta</label>
      <input class="sg-input" id="corte" name="corte" type="date" value="{{ $filtro->fechaCorte() }}" max="{{ now(\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA)->toDateString() }}">
    </div>
    <div class="sg-actions">
      <button class="sg-button" type="submit">Aplicar filtros</button>
      @if ($hayFiltros)
        <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.indicadores') }}">Limpiar</a>
      @endif
    </div>
  </div>
</form>

@if ($error || collect($indicadores)->contains(fn ($r) => $r->estado === \App\Modules\SeguimientoEgresado\Services\Indicadores\ResultadoIndicador::ERROR))
  <div class="sg-alert sg-alert-danger sg-bloque" role="alert">
    No se pudieron consultar los datos. Revisa la conexión con la base de datos y
    <a class="sg-link" href="{{ route('seguimiento-egresado.indicadores', $consulta) }}">vuelve a intentarlo</a>.
  </div>
@endif

<section class="sg-bloque" aria-labelledby="sg-ind-kpis">
  <div class="sg-section-title">
    <h2 id="sg-ind-kpis">Resultados</h2>
    <div class="sg-acciones-seccion">
      <button class="sg-button sg-button-secondary" type="button" data-sg-abrir-modal="sg-modal-calculo" hidden>Cómo se calcula</button>
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.indicadores.exportar', $consulta) }}">Exportar a Excel</a>
    </div>
  </div>
  <p class="sg-criterio" role="status">{{ $baseNota }} · {{ count($i1PorCohorte) }} {{ count($i1PorCohorte) === 1 ? 'promoción' : 'promociones' }} · {{ count($campanias) }} {{ count($campanias) === 1 ? 'campaña' : 'campañas' }}</p>
  <div class="sg-kpis">
    @foreach ($indicadores as $r)
      <article @class(['sg-kpi', 'sg-kpi-programa' => in_array($r->codigo, ['I3', 'I5', 'I6'], true)])>
        <h3 class="sg-kpi-head"><span class="sg-kpi-code">{{ $r->codigo }}</span> {{ $r->nombre }}</h3>
        @if ($r->valor === null)
          <p class="sg-kpi-sin">{{ $r->etiquetaEstado() }}</p>
          <p class="sg-kpi-pie">{{ $r->explicacionEstado() }}</p>
          @if ($r->nota)<p class="sg-kpi-pie">{{ $r->nota }}</p>@endif
        @elseif ($r->unidad === '%')
          <p class="sg-kpi-valor">{{ $formato($r->valor) }} <small>%</small></p>
          <div class="sg-kpi-barra" aria-hidden="true"><span style="width: {{ min(100, $r->valor) }}%"></span></div>
          <p class="sg-kpi-pie">{{ $r->numerador }} de {{ $r->denominador }}@if ($r->cobertura !== null) · Cobertura {{ $formato($r->cobertura) }} %@endif</p>
          @if ($r->nota)<p class="sg-kpi-pie">{{ $r->nota }}</p>@endif
        @else
          <p class="sg-kpi-valor">{{ number_format($r->valor, 0) }} <small>{{ $r->unidad }}</small></p>
          <p class="sg-kpi-pie">Cantidad absoluta</p>
          @if ($r->nota)<p class="sg-kpi-pie">{{ $r->nota }}</p>@endif
        @endif
        <p class="sg-kpi-meta">{{ $r->periodo }} · Datos hasta {{ $fecha($r->fechaCorte) }}<br>Fuente: {{ $r->fuente }}</p>
      </article>
    @endforeach
  </div>
</section>

@if ($resumen && $gestion && $verGestion)
  <section class="sg-bloque" aria-labelledby="sg-ind-gestion">
    <div class="sg-section-title"><h2 id="sg-ind-gestion">Gestión diaria</h2></div>
    <div class="sg-kpis">
      <div class="sg-kpi sg-kpi-compacta">
        <p class="sg-kpi-head">Importaciones pendientes</p>
        <p class="sg-kpi-valor">{{ $gestion['importaciones_pendientes'] }}</p>
        <p class="sg-kpi-pie">En proceso o con errores</p>
        <p class="sg-kpi-accion"><a class="sg-link" href="{{ route('seguimiento-egresado.padron') }}">Ir al padrón</a></p>
      </div>
      <div class="sg-kpi sg-kpi-compacta">
        <p class="sg-kpi-head">Egresados sin contacto vigente</p>
        <p class="sg-kpi-valor">{{ $gestion['sin_contacto'] }}</p>
        <p class="sg-kpi-pie">En {{ $gestion['cohortes_sin_contacto'] }} {{ $gestion['cohortes_sin_contacto'] === 1 ? 'promoción' : 'promociones' }} · {{ $resumen['registrados'] }} registrados</p>
        <p class="sg-kpi-accion"><a class="sg-link" href="{{ route('seguimiento-egresado.padron') }}">Revisar expedientes</a></p>
      </div>
      <div class="sg-kpi sg-kpi-compacta">
        <p class="sg-kpi-head">Campañas activas</p>
        <p class="sg-kpi-valor">{{ $gestion['campanias_activas'] }}</p>
        @if ($gestion['cobertura_activas'] === null)
          <p class="sg-kpi-pie">Sin invitaciones de seguimiento activas</p>
        @else
          <div class="sg-kpi-barra" aria-hidden="true"><span style="width: {{ $gestion['cobertura_activas'] }}%"></span></div>
          <p class="sg-kpi-pie">{{ $gestion['respondieron_activas'] }} de {{ $gestion['invitados_activas'] }} · Cobertura {{ $formato($gestion['cobertura_activas']) }} %</p>
        @endif
        <p class="sg-kpi-accion"><a class="sg-link" href="{{ route('seguimiento-egresado.campanias') }}">Ir a campañas</a></p>
      </div>
    </div>
  </section>
@endif

@php
  $sinMedicion = ['Sin medición', 'No hay respuestas para este filtro.'];

  $horizontesConDatos = collect($horizontes)->filter(fn ($h) => $h['i2']->valor !== null || $h['i4']->valor !== null);
  $etiquetaH = fn ($h) => [$h['horizonte'].' años', 'N='.($h['i2']->n ?? 0).' · Cob. '.$formato($h['i2']->cobertura).' %'];

  $graficoI1 = [
    'tipo' => 'bar', 'unidad' => '%', 'maximo' => 100,
    'etiquetas' => array_map(fn ($c) => $c['etiqueta'].($c['en_curso'] ? ' · en curso' : ''), $i1PorCohorte),
    'series' => [[
      'nombre' => 'I1', 'color' => 'navy', 'valores' => array_column($i1PorCohorte, 'valor'),
      'textos' => array_map(fn ($c) => $c['valor'] === null ? 'Sin datos' : $formato($c['valor']).' %', $i1PorCohorte),
      'detalles' => array_map(fn ($c) => $c['numerador'].' de '.$c['denominador'].' ingresantes'.($c['en_curso'] ? ' · aún en curso, no entra al total' : ''), $i1PorCohorte),
    ]],
  ];

  $graficoHorizontes = [
    'tipo' => 'bar', 'unidad' => '%', 'maximo' => 100,
    'etiquetas' => $horizontesConDatos->map($etiquetaH)->values()->all(),
    'series' => [
      ['nombre' => 'I2 Empleabilidad', 'color' => 'navy', 'valores' => $horizontesConDatos->map(fn ($h) => $h['i2']->valor)->values()->all(),
       'textos' => $horizontesConDatos->map(fn ($h) => $formato($h['i2']->valor).' %')->values()->all()],
      ['nombre' => 'I4 Satisfacción', 'color' => 'verde', 'patron' => true, 'valores' => $horizontesConDatos->map(fn ($h) => $h['i4']->valor)->values()->all(),
       'textos' => $horizontesConDatos->map(fn ($h) => $formato($h['i4']->valor).' %')->values()->all()],
    ],
  ];

  $graficoTendencia = [
    'tipo' => 'line', 'unidad' => '%', 'maximo' => 100,
    'etiquetas' => array_map(fn ($p) => $p['periodo'].' (N='.$p['n'].')', $tendencia),
    'series' => [
      ['nombre' => 'I2 Empleabilidad', 'color' => 'navy', 'punto' => 'circle', 'valores' => array_column($tendencia, 'i2')],
      ['nombre' => 'I4 Satisfacción', 'color' => 'verde', 'punto' => 'rectRot', 'discontinua' => true, 'valores' => array_column($tendencia, 'i4')],
    ],
  ];

  $porcentajeDe = fn ($parte, $total) => $total > 0 ? round(100 * $parte / $total, 1) : 0;
  $serieCobertura = fn (string $nombre, string $clave, string $color, bool $patron = false) => [
    'nombre' => $nombre, 'color' => $color, 'patron' => $patron,
    'valores' => array_map(fn ($c) => $porcentajeDe($c[$clave], $c['invitados']), $campanias),
    'textos' => array_map(fn ($c) => $c[$clave] > 0 ? (string) $c[$clave] : '', $campanias),
    'detalles' => array_map(fn ($c) => $c[$clave].' de '.$c['invitados'], $campanias),
  ];
  $graficoCobertura = [
    'tipo' => 'bar', 'horizontal' => true, 'apilado' => true, 'unidad' => '%', 'maximo' => 100,
    'etiquetas' => array_map(fn ($c) => $c['periodo'].' · '.($c['horizonte'] ? $c['horizonte'].($c['horizonte'] === 1 ? ' año' : ' años') : 'sin plazo'), $campanias),
    'series' => [
      $serieCobertura('Respondieron', 'respondieron', 'verde'),
      $serieCobertura('Pendientes', 'pendientes', 'ambar', true),
      $serieCobertura('Vencidas', 'vencidas', 'gris'),
    ],
  ];

  $barrasConteo = function (array $datos, string $color, int $base) use ($formato) {
    return [
      'tipo' => 'bar', 'horizontal' => true, 'unidad' => 'n',
      'etiquetas' => array_keys($datos),
      'series' => [[
        'nombre' => 'Personas', 'color' => $color, 'valores' => array_values($datos),
        'textos' => array_map(fn ($v) => $v.' ('.$formato($base > 0 ? 100 * $v / $base : 0).' %)', array_values($datos)),
      ]],
    ];
  };
  $filasConteo = fn (array $datos, int $base) => array_map(
    fn ($nombre, $v) => [$nombre, $v, $formato($base > 0 ? 100 * $v / $base : 0).' %'], array_keys($datos), array_values($datos));

  $graficoI6 = [
    'tipo' => 'bar', 'unidad' => 'n',
    'etiquetas' => array_map('strval', array_keys($i6PorAnio)),
    'series' => [['nombre' => 'Mejoras', 'color' => 'dorado', 'valores' => array_values($i6PorAnio), 'textos' => array_map('strval', array_values($i6PorAnio))]],
  ];
  $objetivos = $direccion['objetivos'] ?? [];
  $graficoObjetivos = [
    'tipo' => 'bar', 'horizontal' => true, 'unidad' => 'n',
    'etiquetas' => array_map(fn ($o) => $o['codigo'].' '.$o['titulo'], $objetivos),
    'series' => [[
      'nombre' => 'Promociones con logro bueno o muy bueno', 'color' => 'dorado',
      'valores' => array_column($objetivos, 'logradas'),
      'textos' => array_map(fn ($o) => $o['logradas'].' de '.$o['evaluaciones'], $objetivos),
    ]],
  ];
@endphp

@unless ($error)
  <section class="sg-bloque" aria-labelledby="sg-ind-comparar">
    <div class="sg-section-title"><h2 id="sg-ind-comparar">Comparaciones y tendencias</h2></div>
    <div class="sg-graficos">
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-i1', 'titulo' => 'I1 por promoción', 'nota' => 'N='.($indicadores['I1']->denominador ?? 0).' ingresantes', 'detalle' => $baseNota,
        'config' => $graficoI1, 'columnas' => ['Promoción', 'Egresados', 'Ingresantes', 'I1'],
        'filas' => array_map(fn ($c) => [$c['etiqueta'].($c['en_curso'] ? ' (en curso)' : ''), $c['numerador'], $c['denominador'], $c['valor'] === null ? 'Sin datos' : $formato($c['valor']).' %'], $i1PorCohorte),
        'vacio' => $i1PorCohorte === [] ? ['Sin datos', 'No hay promociones para este filtro.'] : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-horizontes', 'titulo' => 'Comparación a 3, 5 y 10 años', 'nota' => 'N y cobertura según los años desde el egreso', 'detalle' => $indicadores['I1']->cohorte.' · Datos hasta '.$fecha($filtro->fechaCorte()),
        'config' => $graficoHorizontes, 'columnas' => ['Años desde el egreso', 'Encuestados', 'Cobertura', 'I2', 'I4'],
        'filas' => collect($horizontes)->map(fn ($h) => [$h['horizonte'].' años', $h['i2']->n ?? 0, $h['i2']->cobertura === null ? 'Sin datos' : $formato($h['i2']->cobertura).' %', $h['i2']->valor === null ? $h['i2']->etiquetaEstado() : $formato($h['i2']->valor).' %', $h['i4']->valor === null ? $h['i4']->etiquetaEstado() : $formato($h['i4']->valor).' %'])->all(),
        'vacio' => $horizontesConDatos->isEmpty() ? $sinMedicion : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-tendencia', 'titulo' => 'Evolución de I2 e I4', 'nota' => $coberturaTexto($indicadores['I2']->cobertura), 'detalle' => 'Por periodo de campaña · '.$baseNota,
        'config' => $graficoTendencia, 'columnas' => ['Periodo', 'Encuestados', 'I2', 'I4'],
        'filas' => array_map(fn ($p) => [$p['periodo'], $p['n'], $formato($p['i2']).' %', $p['i4'] === null ? 'Sin medición' : $formato($p['i4']).' %'], $tendencia),
        'vacio' => $tendencia === [] ? $sinMedicion : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-cobertura', 'titulo' => 'Cobertura por campaña', 'nota' => 'N='.array_sum(array_column($campanias, 'invitados')).' invitaciones', 'detalle' => 'No responder no significa estar sin empleo · '.$baseNota,
        'config' => $graficoCobertura, 'alto' => max(180, 60 * count($campanias) + 60),
        'columnas' => ['Campaña', 'Invitados', 'Respondieron', 'Pendientes', 'Vencidas', 'Cobertura'],
        'filas' => array_map(fn ($c) => [$c['campania'], $c['invitados'], $c['respondieron'], $c['pendientes'], $c['vencidas'], $formato($c['cobertura']).' %'], $campanias),
        'vacio' => $campanias === [] ? ['Sin datos', 'No hay campañas de seguimiento para este filtro.'] : null,
      ])
    </div>
  </section>

  <section class="sg-bloque" aria-labelledby="sg-ind-laboral">
    <div class="sg-section-title"><h2 id="sg-ind-laboral">Situación laboral</h2></div>
    <div class="sg-graficos sg-graficos-3">
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-modalidad', 'titulo' => 'Modalidad laboral', 'nota' => 'N='.$laboral['trabajan'].' · '.$coberturaTexto($laboral['cobertura']), 'detalle' => 'Última respuesta de quienes trabajan · '.$baseNota,
        'config' => $barrasConteo($laboral['modalidades'], 'navy', $laboral['trabajan']), 'alto' => 300,
        'columnas' => ['Modalidad', 'Personas', 'Porcentaje'], 'filas' => $filasConteo($laboral['modalidades'], $laboral['trabajan']),
        'vacio' => $laboral['modalidades'] === [] ? $sinMedicion : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-area', 'titulo' => 'Área de desempeño', 'nota' => 'N='.$laboral['trabajan'].' · '.$coberturaTexto($laboral['cobertura']), 'detalle' => 'Última respuesta de quienes trabajan · '.$baseNota,
        'config' => $barrasConteo($laboral['areas'], 'navy', $laboral['trabajan']), 'alto' => 300,
        'columnas' => ['Área', 'Personas', 'Porcentaje'], 'filas' => $filasConteo($laboral['areas'], $laboral['trabajan']),
        'vacio' => $laboral['areas'] === [] ? $sinMedicion : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-ubicacion', 'titulo' => 'Perú y extranjero', 'nota' => 'N='.$laboral['n'].' · '.$coberturaTexto($laboral['cobertura']), 'detalle' => 'País de residencia, última respuesta · '.$baseNota,
        'config' => $barrasConteo($laboral['ubicacion'], 'navy', $laboral['n']), 'alto' => 300,
        'columnas' => ['Residencia', 'Personas', 'Porcentaje'], 'filas' => $filasConteo($laboral['ubicacion'], $laboral['n']),
        'vacio' => $laboral['n'] === 0 ? $sinMedicion : null,
      ])
    </div>
  </section>

  <section class="sg-bloque" aria-labelledby="sg-ind-mejoras">
    <div class="sg-section-title">
      <h2 id="sg-ind-mejoras">Objetivos y mejoras</h2>
      @if ($verDireccion)
        <div class="sg-acciones-seccion"><button class="sg-button sg-button-secondary" type="button" data-sg-abrir-modal="sg-modal-evidencias" hidden>Ver evidencias</button></div>
      @endif
    </div>
    <div class="sg-graficos">
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-i6', 'titulo' => 'I6 por año', 'nota' => 'N='.array_sum($i6PorAnio).' mejoras', 'detalle' => 'Implementadas con evidencia · Todo el programa · '.$filtro->periodo().' · Datos hasta '.$fecha($filtro->fechaCorte()),
        'config' => $graficoI6, 'columnas' => ['Año', 'Mejoras'],
        'filas' => array_map(fn ($a, $v) => [$a, $v], array_keys($i6PorAnio), array_values($i6PorAnio)),
        'vacio' => $i6PorAnio === [] ? ['Sin mejoras', 'No hay mejoras implementadas en este periodo.'] : null,
      ])
      @if ($verDireccion)
        @include('modules.seguimiento-egresado.partials.grafico', [
          'id' => 'sg-graf-objetivos', 'titulo' => 'Logro por objetivo educacional', 'nota' => $coberturaTexto($indicadores['I3']->cobertura), 'detalle' => 'Evaluaciones validadas · '.$baseNota,
          'config' => $graficoObjetivos, 'alto' => max(200, 56 * count($objetivos) + 40),
          'columnas' => ['Objetivo', 'Con logro', 'Evaluaciones'],
          'filas' => array_map(fn ($o) => [$o['codigo'].' '.$o['titulo'], $o['logradas'], $o['evaluaciones']], $objetivos),
          'vacio' => $objetivos === [] ? $sinMedicion : null,
        ])
      @endif
    </div>
    @if ($verDireccion)
      <dialog class="sg-modal" id="sg-modal-evidencias" aria-labelledby="sg-modal-evidencias-titulo">
        @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => 'sg-modal-evidencias', 'titulo' => 'Evidencia de las mejoras', 'detalle' => $filtro->periodo().' · Datos hasta '.$fecha($filtro->fechaCorte())])
        <div class="sg-modal-cuerpo">
          @if (($direccion['mejoras'] ?? []) === [])
            <div class="sg-empty"><h3>Sin mejoras</h3><p>No hay mejoras implementadas en este periodo.</p></div>
          @else
            <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-modal-evidencias-titulo">
              <table class="sg-table">
                <caption class="sg-graf-oculto">Mejoras que cuenta I6 y su evidencia</caption>
                <thead>
                  <tr><th scope="col">Código</th><th scope="col">Mejora</th><th scope="col">Implementada</th><th scope="col">Evidencia</th></tr>
                </thead>
                <tbody>
                  @foreach ($direccion['mejoras'] as $m)
                    <tr>
                      <th scope="row">{{ $m['codigo'] }}</th>
                      <td>{{ $m['titulo'] }}</td>
                      <td>{{ $fecha($m['fecha']) }}</td>
                      <td>{{ $m['evidencia'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
          <div class="sg-actions"><a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.institucional') }}">Ir a evaluación institucional</a></div>
        </div>
      </dialog>
    @endif
  </section>
@endunless

<dialog class="sg-modal sg-modal-ancho" id="sg-modal-calculo" aria-labelledby="sg-modal-calculo-titulo">
  @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => 'sg-modal-calculo', 'titulo' => 'Cómo se calcula', 'detalle' => $baseNota])
  <div class="sg-modal-cuerpo">
  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-modal-calculo-titulo">
    <table class="sg-table">
      <caption class="sg-graf-oculto">Fórmula, datos y fuente de cada indicador. {{ $baseNota }}</caption>
      <thead>
        <tr>
          <th scope="col">Indicador</th>
          <th scope="col">Fórmula</th>
          <th scope="col" class="sg-numeric">Numerador</th>
          <th scope="col" class="sg-numeric">Denominador</th>
          <th scope="col" class="sg-numeric">Población</th>
          <th scope="col" class="sg-numeric">Resultado</th>
          <th scope="col">Fuente</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($indicadores as $r)
          <tr>
            <th scope="row">{{ $r->codigo }}</th>
            <td>{{ $r->formula }}</td>
            <td class="sg-numeric">{{ $r->numerador ?? '—' }}</td>
            <td class="sg-numeric">{{ $r->unidad === '%' ? ($r->denominador ?? '—') : 'No corresponde' }}</td>
            <td class="sg-numeric">{{ $r->unidad === '%' ? ($r->poblacionElegible ?? '—') : 'No corresponde' }}</td>
            <td class="sg-numeric">{{ $conUnidad($r) }}</td>
            <td>{{ $r->fuente }}@if ($r->nota) <br><small>{{ $r->nota }}</small>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  </div>
</dialog>
@endsection
@push('scripts')
  <script src="{{ asset('js/seguimiento-egresado/vendor/chart-4.4.9.umd.js') }}"></script>
  <script src="{{ asset('js/seguimiento-egresado/seguimiento-graficos.js') }}"></script>
@endpush
