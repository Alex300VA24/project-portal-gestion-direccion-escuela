@extends('modules.seguimiento-egresado.layout')
@section('title', 'Encuesta empresarial')
@section('section_name', 'Encuesta empresarial')
@php
  $formato = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.');
  $estados = [
    'PENDIENTE' => ['Pendiente', 'sg-badge-warning'],
    'COMPLETADA' => ['Respondida', 'sg-badge-success'],
    'EXPIRADA' => ['Vencida', 'sg-badge-danger'],
    'ANULADA' => ['Anulada', 'sg-badge-danger'],
  ];
  $tipos = ['DESEMPENO' => 'Desempeño', 'MERCADO' => 'Mercado'];
  $enlaceGenerado = session('enlace_generado');
  $campaniaActual = $campanias->firstWhere('id', $campaniaId);
  $periodoTexto = $campaniaActual ? 'Periodo '.$campaniaActual->periodo : 'Histórico hasta '.now(\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA)->year;
  $corteTexto = 'Corte '.now(\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA)->format('d/m/Y');
  $alcance = ($campaniaActual->nombre ?? 'Todas las campañas').' · '.($tipos[$contexto] ?? 'Todos los tipos').' · '.$periodoTexto.' · '.$corteTexto;
  $coberturaTexto = ($resumen['cobertura'] ?? null) === null ? 'Cobertura sin datos' : 'Cobertura '.$formato($resumen['cobertura']).' %';

  $barras = fn (array $ranking, string $color) => [
    'tipo' => 'bar', 'horizontal' => true, 'unidad' => 'n',
    'etiquetas' => array_column($ranking, 'nombre'),
    'series' => [[
      'nombre' => 'Organizaciones', 'color' => $color, 'valores' => array_column($ranking, 'cantidad'),
      'textos' => array_map(fn ($f) => $f['cantidad'].' de '.$f['base'], $ranking),
    ]],
  ];
  $porPagina = 10;
  $totalPaginas = max(1, (int) ceil(($resumen['total'] ?? 0) / $porPagina));
  $paginaActual = min($pagina, $totalPaginas);
  $urlPagina = fn (int $n) => route('seguimiento-egresado.empresas', array_filter(['campania' => $campaniaId, 'contexto' => $contexto, 'pagina' => $n > 1 ? $n : null])).'#sg-emp-invitaciones';
  $egresadosPorCohorte = $evaluables->groupBy('codigo_promocion')->sortKeysDesc();
  $filasRanking = fn (array $ranking) => array_map(fn ($f) => [$f['nombre'], $f['cantidad'], $formato($f['porcentaje']).' %'], $ranking);
@endphp
@push('styles')
  @include('modules.seguimiento-egresado.partials.estilos-graficos')
  <style>
    .sg-emp-filtros { grid-template-columns: repeat(2, minmax(0, 1fr)) auto; align-items: end; }
    .sg-emp-filtros .sg-actions { margin: 0; }
    .sg-modal.sg-modal-corto { width: min(640px, calc(100% - 32px)); }
    .sg-emp-enlace { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: var(--sg-space-3); align-items: center; }
    .sg-emp-enlace input { font-family: var(--sg-font-code); font-size: 13px; }
    .sg-emp-form .sg-form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
    .sg-emp-form [data-sg-promocion][hidden] { display: none; }
    .sg .sg-emp-renovar { min-height: 44px; padding: 0; border: 0; background: none; color: var(--sg-accent); font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .sg .sg-emp-renovar:hover { text-decoration: underline; }
    .sg-emp-invitaciones .sg-table :is(th, td) { vertical-align: middle; }
    .sg-emp-invitaciones form { margin: 0; }
    .sg-emp-invitaciones td:nth-of-type(2) { min-width: 210px; }
    .sg-emp-invitaciones td:nth-of-type(1), .sg-emp-invitaciones td:nth-of-type(5) { white-space: nowrap; }
    .sg-emp-encabezado { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--sg-space-4); margin-bottom: var(--sg-space-6); }
    .sg-emp-encabezado .sg-heading { margin-bottom: 0; }
    .sg-emp-encabezado .sg-button[hidden] { display: none; }
    .sg-emp-confirmar p { margin: 0; }
    .sg-emp-sin-valor { color: var(--sg-muted); white-space: nowrap; }
    .sg .sg-emp-peligro { background: var(--sg-danger); border-color: var(--sg-danger); }
    .sg .sg-emp-peligro:hover { background: var(--sg-danger); filter: brightness(.9); }
    .sg-emp-copiado { display: block; margin-top: 4px; color: var(--sg-muted); font-size: 12px; white-space: nowrap; }
    .sg-emp-form .sg-actions { justify-content: flex-end; margin-top: var(--sg-space-5); }
    .sg-emp-form [data-sg-solo-desempeno][hidden] { display: none; }
    .sg-emp-paginas { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--sg-space-3); margin-top: var(--sg-space-4); color: var(--sg-muted); font-size: 14px; }
    .sg-emp-paginas > span:empty { min-width: 1px; }
    @media (max-width: 900px) { .sg-emp-filtros { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 540px) { .sg-emp-enlace, .sg-emp-form .sg-form-grid { grid-template-columns: minmax(0, 1fr); } }
  </style>
@endpush
@section('content')
<div class="sg-emp-encabezado">
  <section class="sg-heading">
    <h1>Encuesta empresarial</h1>
    <p>Las empresas responden con un enlace, sin crear cuenta.</p>
  </section>
  @if ($puedeGenerar && ! $error)
    <button class="sg-button" type="button" data-sg-abrir-modal="sg-modal-enlace" hidden>Generar enlace</button>
  @endif
</div>

@if ($enlaceGenerado)
  <dialog class="sg-modal sg-modal-corto" id="sg-modal-enlace-listo" aria-labelledby="sg-modal-enlace-listo-titulo" data-sg-abrir-al-cargar data-sg-protegido data-sg-enlace-listo data-sg-url-enviado="{{ route('seguimiento-egresado.empresas.enlaces.enviado', ['id' => $enlaceGenerado['id']]) }}">
    @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => 'sg-modal-enlace-listo', 'titulo' => 'Enlace generado', 'detalle' => 'Vence el '.$enlaceGenerado['expira_en']])
    <div class="sg-modal-cuerpo">
      <div class="sg-field">
        <label class="sg-label" for="sg-enlace-generado">Envíalo a la empresa</label>
        <div class="sg-emp-enlace">
          <input class="sg-input" id="sg-enlace-generado" type="text" readonly value="{{ $enlaceGenerado['url'] }}" aria-describedby="sg-enlace-ayuda">
          <button class="sg-button" type="button" data-sg-copiar="sg-enlace-generado">Copiar enlace</button>
        </div>
        <p class="sg-help" id="sg-enlace-ayuda" role="status">Cópialo ahora: no se volverá a mostrar.</p>
      </div>
    </div>
  </dialog>
@endif

@if (session('aviso_enlace'))
  <div class="sg-alert sg-alert-warning sg-bloque" role="alert">{{ session('aviso_enlace') }}</div>
@endif

@if ($error)
  <div class="sg-alert sg-alert-danger sg-bloque" role="alert">
    No se pudieron consultar los datos. Revisa la conexión con la base de datos y
    <a class="sg-link" href="{{ route('seguimiento-egresado.empresas') }}">vuelve a intentarlo</a>.
  </div>
@else
  <form class="sg-panel sg-bloque" method="get" action="{{ route('seguimiento-egresado.empresas') }}" aria-label="Filtros de resultados">
    <div class="sg-filters sg-emp-filtros">
      <div class="sg-field">
        <label class="sg-label" for="campania">Campaña</label>
        <select class="sg-select" id="campania" name="campania">
          <option value="">Todas las campañas</option>
          @foreach ($campanias as $c)
            <option value="{{ $c->id }}" @selected($campaniaId === (int) $c->id)>{{ $c->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div class="sg-field">
        <label class="sg-label" for="contexto">Tipo de encuesta</label>
        <select class="sg-select" id="contexto" name="contexto">
          <option value="">Todos los tipos</option>
          @foreach ($tipos as $valor => $texto)
            <option value="{{ $valor }}" @selected($contexto === $valor)>{{ $texto }}</option>
          @endforeach
        </select>
      </div>
      <div class="sg-actions">
        <button class="sg-button" type="submit">Aplicar filtros</button>
        @if ($campaniaId || $contexto)
          <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.empresas') }}">Limpiar</a>
        @endif
      </div>
    </div>
  </form>

  <section class="sg-bloque" aria-labelledby="sg-emp-resumen">
    <div class="sg-section-title"><h2 id="sg-emp-resumen">Resultados</h2></div>
    <p class="sg-criterio" role="status">{{ $periodoTexto }} · {{ $corteTexto }} · {{ $resumen['total'] }} {{ $resumen['total'] === 1 ? 'invitación' : 'invitaciones' }}</p>
    <div class="sg-kpis">
      <article class="sg-kpi">
        <h3 class="sg-kpi-head">Cobertura de respuesta</h3>
        @if ($resumen['total'] === 0)
          <p class="sg-kpi-sin">Sin datos</p>
          <p class="sg-kpi-pie">Aún no hay invitaciones para este filtro.</p>
        @else
          <p class="sg-kpi-valor">{{ $formato($resumen['cobertura']) }} <small>%</small></p>
          <div class="sg-kpi-barra" aria-hidden="true"><span style="width: {{ $resumen['cobertura'] }}%"></span></div>
          <p class="sg-kpi-pie">{{ $resumen['respondidas'] }} de {{ $resumen['total'] }} · Pendientes: {{ $resumen['pendientes'] }} · Vencidas: {{ $resumen['vencidas'] }}</p>
        @endif
        <p class="sg-kpi-meta">{{ $periodoTexto }} · {{ $corteTexto }}<br>Fuente: Invitaciones</p>
      </article>
      <article class="sg-kpi sg-kpi-programa">
        <h3 class="sg-kpi-head"><span class="sg-kpi-code">I5</span> Satisfacción de empleadores</h3>
        @if ($resumen['i5'] === null)
          <p class="sg-kpi-sin">Sin medición</p>
          <p class="sg-kpi-pie">No hay evaluaciones de desempeño respondidas.</p>
        @else
          <p class="sg-kpi-valor">{{ $formato($resumen['i5']) }} <small>%</small></p>
          <div class="sg-kpi-barra" aria-hidden="true"><span style="width: {{ $resumen['i5'] }}%"></span></div>
          <p class="sg-kpi-pie">{{ $resumen['satisfechas'] }} de {{ $resumen['desempeno'] }} evaluaciones de desempeño</p>
        @endif
        <p class="sg-kpi-meta">{{ $periodoTexto }} · {{ $corteTexto }}<br>Fuente: Encuesta empresarial</p>
      </article>
      <article class="sg-kpi">
        <h3 class="sg-kpi-head">Encuestas de mercado</h3>
        <p class="sg-kpi-valor">{{ $resumen['mercado'] }}</p>
        <p class="sg-kpi-pie">No cuentan para I5</p>
        <p class="sg-kpi-meta">{{ $periodoTexto }} · {{ $corteTexto }}<br>Fuente: Encuesta empresarial</p>
      </article>
    </div>
  </section>

  <section class="sg-bloque" aria-labelledby="sg-emp-graficos">
    <div class="sg-section-title"><h2 id="sg-emp-graficos">Lo que dicen las empresas</h2></div>
    <div class="sg-graficos">
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-demandas', 'titulo' => 'Habilidades más demandadas', 'nota' => 'N='.$resumen['respondidas'].' respuestas · '.$coberturaTexto, 'detalle' => $alcance,
        'config' => $barras($resumen['demandas'], 'navy'), 'alto' => max(200, 44 * count($resumen['demandas']) + 40),
        'columnas' => ['Habilidad', 'Organizaciones', 'Porcentaje'], 'filas' => $filasRanking($resumen['demandas']),
        'vacio' => $resumen['demandas'] === [] ? ['Sin respuestas', 'Aún no hay respuestas para este filtro.'] : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-brechas', 'titulo' => 'Aspectos por reforzar', 'nota' => 'N='.$resumen['respondidas'].' respuestas · '.$coberturaTexto, 'detalle' => $alcance,
        'config' => $barras($resumen['brechas'], 'ambar'), 'alto' => max(200, 44 * count($resumen['brechas']) + 40),
        'columnas' => ['Aspecto', 'Organizaciones', 'Porcentaje'], 'filas' => $filasRanking($resumen['brechas']),
        'vacio' => $resumen['brechas'] === [] ? ['Sin respuestas', 'Aún no hay respuestas para este filtro.'] : null,
      ])
      @include('modules.seguimiento-egresado.partials.grafico', [
        'id' => 'sg-graf-satisfaccion', 'titulo' => 'Satisfacción con el desempeño', 'nota' => 'N='.$resumen['desempeno'].' evaluaciones', 'detalle' => $alcance,
        'config' => [
          'tipo' => 'bar', 'horizontal' => true, 'unidad' => 'n',
          'etiquetas' => array_column($resumen['satisfaccion'], 'nombre'),
          'series' => [['nombre' => 'Evaluaciones', 'color' => 'navy', 'valores' => array_column($resumen['satisfaccion'], 'cantidad'),
            'textos' => array_map(fn ($f) => (string) $f['cantidad'], $resumen['satisfaccion'])]],
        ],
        'alto' => 220,
        'columnas' => ['Nivel', 'Evaluaciones'], 'filas' => array_map(fn ($f) => [$f['nombre'], $f['cantidad']], $resumen['satisfaccion']),
        'vacio' => $resumen['desempeno'] === 0 ? ['Sin medición', 'No hay evaluaciones de desempeño respondidas.'] : null,
      ])
    </div>
  </section>

  @if ($puedeGenerar)
    <dialog class="sg-modal sg-modal-corto sg-emp-form" id="sg-modal-enlace" aria-labelledby="sg-modal-enlace-titulo" @if ($errors->any()) data-sg-abrir-al-cargar @endif>
      @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => 'sg-modal-enlace', 'titulo' => 'Generar enlace'])
      <div class="sg-modal-cuerpo">
      @if ($campanias->where('estado', 'ABIERTA')->isEmpty())
        <div class="sg-empty">
          <h3>No hay campañas abiertas para empresas</h3>
          <p>Abre una campaña de tipo Empresas o Mixta para generar enlaces.</p>
        </div>
      @else
        <form method="post" action="{{ route('seguimiento-egresado.empresas.enlaces') }}" novalidate>
          @csrf
          <div class="sg-form-grid">
            <div class="sg-field">
              <label class="sg-label" for="id_campania">Campaña</label>
              <select class="sg-select" id="id_campania" name="id_campania" required @error('id_campania') aria-invalid="true" aria-describedby="id_campania-error" @enderror>
                <option value="">Selecciona una campaña</option>
                @foreach ($campanias->where('estado', 'ABIERTA') as $c)
                  <option value="{{ $c->id }}" @selected((int) old('id_campania') === (int) $c->id)>{{ $c->nombre }}</option>
                @endforeach
              </select>
              @error('id_campania')<p class="sg-field-error" id="id_campania-error">{{ $message }}</p>@enderror
            </div>
            <div class="sg-field">
              <label class="sg-label" for="sg-contexto-enlace">Tipo de encuesta</label>
              <select class="sg-select" id="sg-contexto-enlace" name="contexto" required @error('contexto') aria-invalid="true" aria-describedby="contexto-error" @enderror>
                <option value="DESEMPENO" @selected(old('contexto', 'DESEMPENO') === 'DESEMPENO')>Desempeño (cuenta para I5)</option>
                <option value="MERCADO" @selected(old('contexto') === 'MERCADO')>Mercado general</option>
              </select>
              @error('contexto')<p class="sg-field-error" id="contexto-error">{{ $message }}</p>@enderror
            </div>
            <div class="sg-field" data-sg-solo-desempeno data-sg-promocion hidden>
              <label class="sg-label" for="sg-promocion">Promoción</label>
              <select class="sg-select" id="sg-promocion">
                <option value="">Selecciona una promoción</option>
                @foreach ($egresadosPorCohorte as $cohorte => $grupo)
                  <option value="{{ $cohorte }}">{{ $cohorte }}</option>
                @endforeach
              </select>
            </div>
            <div class="sg-field" data-sg-solo-desempeno>
              <label class="sg-label" for="id_egresado">Egresado evaluado</label>
              <select class="sg-select" id="id_egresado" name="id_egresado" aria-describedby="id_egresado-ayuda @error('id_egresado') id_egresado-error @enderror" @error('id_egresado') aria-invalid="true" @enderror>
                <option value="">Selecciona un egresado</option>
                @foreach ($egresadosPorCohorte as $cohorte => $grupo)
                  <optgroup label="{{ $cohorte }}">
                    @foreach ($grupo as $e)
                      <option value="{{ $e->id }}" data-cohorte="{{ $cohorte }}" @selected((int) old('id_egresado') === (int) $e->id)>{{ trim($e->apellido_paterno.' '.$e->apellido_materno) }}, {{ $e->nombres }}@if ($e->nombre_empleador_cliente) · {{ $e->nombre_empleador_cliente }}@endif</option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
              <p class="sg-help" id="id_egresado-ayuda">Solo quienes autorizaron contactar a su empresa.</p>
              @error('id_egresado')<p class="sg-field-error" id="id_egresado-error">{{ $message }}</p>@enderror
            </div>
            <div class="sg-field">
              <label class="sg-label" for="organizacion">Organización (opcional)</label>
              <input class="sg-input" id="organizacion" name="organizacion" type="text" maxlength="180" autocomplete="off" value="{{ old('organizacion') }}" @error('organizacion') aria-invalid="true" aria-describedby="organizacion-error" @enderror>
              @error('organizacion')<p class="sg-field-error" id="organizacion-error">{{ $message }}</p>@enderror
            </div>
            <div class="sg-field">
              <label class="sg-label" for="contacto_empresa">Persona de contacto (opcional)</label>
              <input class="sg-input" id="contacto_empresa" name="contacto_empresa" type="text" maxlength="180" autocomplete="off" value="{{ old('contacto_empresa') }}" @error('contacto_empresa') aria-invalid="true" aria-describedby="contacto_empresa-error" @enderror>
              @error('contacto_empresa')<p class="sg-field-error" id="contacto_empresa-error">{{ $message }}</p>@enderror
            </div>
            <div class="sg-field">
              <label class="sg-label" for="dias_validez">Días de validez</label>
              <input class="sg-input" id="dias_validez" name="dias_validez" type="number" inputmode="numeric" min="1" max="{{ \App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa::DIAS_MAXIMOS }}" required value="{{ old('dias_validez', \App\Modules\SeguimientoEgresado\Services\Empresas\EnlaceEmpresa::DIAS_POR_DEFECTO) }}" aria-describedby="dias_validez-ayuda @error('dias_validez') dias_validez-error @enderror" @error('dias_validez') aria-invalid="true" @enderror>
              <p class="sg-help" id="dias_validez-ayuda">Nunca supera la fecha de cierre de la campaña.</p>
              @error('dias_validez')<p class="sg-field-error" id="dias_validez-error">{{ $message }}</p>@enderror
            </div>
          </div>
          <div class="sg-actions"><button class="sg-button" type="submit">Generar enlace</button></div>
        </form>
      @endif
      </div>
    </dialog>
  @endif

  <section class="sg-bloque sg-emp-invitaciones" aria-labelledby="sg-emp-invitaciones">
    <div class="sg-section-title"><h2 id="sg-emp-invitaciones">Invitaciones</h2></div>
    @if ($resumen['total'] === 0)
      <div class="sg-empty"><h3>Sin invitaciones</h3><p>Genera un enlace o cambia los filtros.</p></div>
    @else
      <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-emp-invitaciones">
        <table class="sg-table">
          <caption class="sg-graf-oculto">Invitaciones de empresas, página {{ $paginaActual }} de {{ $totalPaginas }}</caption>
          <thead>
            <tr>
              <th scope="col">Organización</th>
              <th scope="col">Tipo</th>
              <th scope="col">Campaña</th>
              <th scope="col">Satisfacción</th>
              <th scope="col">Estado</th>
              <th scope="col">Vence</th>
              @if ($puedeGenerar)<th scope="col">Acción</th>@endif
            </tr>
          </thead>
          <tbody>
            @foreach ($resumen['invitaciones']->forPage($paginaActual, $porPagina) as $i)
              <tr>
                <th scope="row">{{ $i->organizacion_informada ?? $i->organizacion ?? 'Sin indicar' }}</th>
                <td>{{ $tipos[$i->contexto] ?? $i->contexto }}</td>
                <td>{{ $i->campania }}</td>
                <td @class(['sg-emp-sin-valor' => $i->contexto === 'MERCADO'])>{{ $i->contexto === 'MERCADO' ? 'No corresponde' : ($i->nivel_nombre ?? 'Sin medición') }}</td>
                <td>
                  <span class="sg-badge {{ $estados[$i->estado_real][1] ?? '' }}">{{ $estados[$i->estado_real][0] ?? $i->estado_real }}</span>
                  @if ($i->estado_real === 'PENDIENTE' && $i->enviado_en)
                    <small class="sg-emp-copiado">Copiado {{ \Carbon\CarbonImmutable::parse($i->enviado_en)->setTimezone(\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA)->format('d/m H:i') }}</small>
                  @endif
                </td>
                <td>{{ \Carbon\CarbonImmutable::parse($i->expira_en)->setTimezone(\App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA)->format('d/m/Y') }}</td>
                @if ($puedeGenerar)
                  <td>
                    @if (in_array($i->estado_real, ['PENDIENTE', 'EXPIRADA'], true) && $i->estado_campania === 'ABIERTA')
                      @php
                        $nombre = $i->organizacion_informada ?? $i->organizacion ?? 'esta invitación';
                        $yaCopiado = $i->estado_real === 'PENDIENTE' && $i->enviado_en;
                        $accion = match (true) {
                          $i->estado_real === 'EXPIRADA' => ['Renovar', 'El enlace de '.$nombre.' ya venció. Se generará uno nuevo.', 'Renovar enlace'],
                          $yaCopiado => ['Reemplazar', 'El enlace de '.$nombre.' ya se copió. Si la empresa lo recibió, dejará de funcionarle y tendrás que enviarle el nuevo.', 'Reemplazar enlace'],
                          default => ['Generar otro', 'Se generará otro enlace para '.$nombre.'. El anterior dejará de funcionar.', 'Generar otro enlace'],
                        };
                      @endphp
                      <form method="post" action="{{ route('seguimiento-egresado.empresas.enlaces.renovar', ['id' => $i->id]) }}" data-sg-renovar data-sg-mensaje="{{ $accion[1] }}" data-sg-confirmar="{{ $accion[2] }}" @if ($yaCopiado) data-sg-peligro @endif>
                        @csrf
                        @if ($yaCopiado)<input type="hidden" name="confirmar_reemplazo" value="1">@endif
                        <button class="sg-emp-renovar" type="submit">{{ $accion[0] }}</button>
                      </form>
                    @else
                      —
                    @endif
                  </td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($puedeGenerar)
        <dialog class="sg-modal sg-modal-corto" id="sg-modal-renovar" aria-labelledby="sg-modal-renovar-titulo">
          @include('modules.seguimiento-egresado.partials.modal-apertura', ['id' => 'sg-modal-renovar', 'titulo' => '¿Confirmas esta acción?'])
          <div class="sg-modal-cuerpo sg-emp-confirmar">
            <p data-sg-renovar-texto></p>
            <div class="sg-actions"><button class="sg-button" type="button" data-sg-renovar-confirmar>Confirmar</button></div>
          </div>
        </dialog>
      @endif
      @if ($totalPaginas > 1)
        <nav class="sg-emp-paginas" aria-label="Páginas de invitaciones">
          <span>@if ($paginaActual > 1)<a class="sg-button sg-button-secondary" href="{{ $urlPagina($paginaActual - 1) }}" rel="prev">Anterior</a>@endif</span>
          <span>Página {{ $paginaActual }} de {{ $totalPaginas }} · {{ $resumen['total'] }} invitaciones</span>
          <span>@if ($paginaActual < $totalPaginas)<a class="sg-button sg-button-secondary" href="{{ $urlPagina($paginaActual + 1) }}" rel="next">Siguiente</a>@endif</span>
        </nav>
      @endif
    @endif
  </section>
@endif
@endsection
@push('scripts')
  <script src="{{ asset('js/seguimiento-egresado/vendor/chart-4.4.9.umd.js') }}"></script>
  <script src="{{ asset('js/seguimiento-egresado/seguimiento-graficos.js') }}"></script>
  <script>
    (() => {
      const tipo = document.getElementById('sg-contexto-enlace');
      if (tipo) {
        const camposDesempeno = document.querySelectorAll('[data-sg-solo-desempeno]');
        const actualizar = () => camposDesempeno.forEach((campo) => { campo.hidden = tipo.value !== 'DESEMPENO'; });
        tipo.addEventListener('change', actualizar);
        actualizar();
      }

      // Primero la promoción; luego solo sus egresados.
      const promocion = document.getElementById('sg-promocion');
      const egresado = document.getElementById('id_egresado');
      if (promocion && egresado) {
        const inicial = egresado.options[0];
        const opciones = [...egresado.querySelectorAll('option[data-cohorte]')];
        const elegida = opciones.find((opcion) => opcion.selected);
        const cargar = (seleccion = '') => {
          egresado.replaceChildren(inicial, ...opciones.filter((opcion) => opcion.dataset.cohorte === promocion.value));
          egresado.disabled = !promocion.value;
          inicial.textContent = promocion.value ? 'Selecciona un egresado' : 'Primero elige la promoción';
          egresado.value = seleccion;
        };
        promocion.value = elegida?.dataset.cohorte ?? '';
        promocion.addEventListener('change', () => cargar());
        cargar(elegida?.value ?? '');
      }

      // Enlace generado: se copia y el aviso desaparece. Si intenta cerrar sin copiar, se le avisa una vez.
      const listo = document.querySelector('[data-sg-enlace-listo]');
      if (listo) {
        const campo = listo.querySelector('#sg-enlace-generado');
        const copiar = listo.querySelector('[data-sg-copiar]');
        const cerrar = listo.querySelector('[data-sg-cerrar-modal]');
        const ayuda = listo.querySelector('#sg-enlace-ayuda');
        let avisado = false;
        let sePuedeCerrar = false;

        const copiarTexto = async () => {
          campo.select();
          try {
            await navigator.clipboard.writeText(campo.value);
            return true;
          } catch {
            return document.execCommand('copy');
          }
        };
        const intentarCerrar = () => {
          if (avisado) {
            sePuedeCerrar = true;
            listo.close();
            return;
          }
          avisado = true;
          ayuda.textContent = 'Aún no lo copias. Si cierras, podrás generar otro desde Invitaciones.';
          cerrar.textContent = 'Cerrar sin copiar';
        };

        copiar.addEventListener('click', async () => {
          if (await copiarTexto()) {
            sePuedeCerrar = true;
            copiar.textContent = 'Enlace copiado';
            copiar.disabled = true;
            ayuda.textContent = 'Listo: pégalo en el mensaje para la empresa.';
            fetch(listo.dataset.sgUrlEnviado, {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            }).catch(() => {});
            setTimeout(() => listo.close(), 1200);
          } else {
            ayuda.textContent = 'No se pudo copiar. Selecciona el enlace y pulsa Ctrl+C.';
          }
        });
        cerrar.addEventListener('click', intentarCerrar);
        listo.addEventListener('cancel', (evento) => {
          if (sePuedeCerrar) return;
          evento.preventDefault();
          intentarCerrar();
        });
        // El navegador puede cerrar con Escape aunque se cancele: si aún no corresponde, se vuelve a abrir.
        listo.addEventListener('close', () => {
          if (!sePuedeCerrar) {
            window.sgAbrirModal(listo);
            return;
          }
          campo.value = '';
        });
      }

      // Nuevo enlace: se confirma porque el anterior deja de funcionar.
      const confirmacion = document.getElementById('sg-modal-renovar');
      if (confirmacion) {
        let pendiente = null;
        document.querySelectorAll('[data-sg-renovar]').forEach((formulario) => {
          formulario.addEventListener('submit', (evento) => {
            evento.preventDefault();
            pendiente = formulario;
            const boton = confirmacion.querySelector('[data-sg-renovar-confirmar]');
            confirmacion.querySelector('[data-sg-renovar-texto]').textContent = formulario.dataset.sgMensaje;
            boton.textContent = formulario.dataset.sgConfirmar;
            boton.classList.toggle('sg-emp-peligro', formulario.hasAttribute('data-sg-peligro'));
            window.sgAbrirModal(confirmacion);
          });
        });
        confirmacion.querySelector('[data-sg-renovar-confirmar]').addEventListener('click', () => pendiente?.submit());
      }
    })();
  </script>
@endpush
