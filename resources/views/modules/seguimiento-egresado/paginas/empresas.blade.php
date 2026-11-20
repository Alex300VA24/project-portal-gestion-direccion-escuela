@extends('modules.seguimiento-egresado.layout')
@section('title', 'Encuesta empresarial')
@section('section_name', 'Encuesta empresarial')
@php
  $formato = fn (?float $v) => $v === null ? '—' : number_format($v, 1, ',', '.');
  $fecha = fn ($v, $f = 'd/m/Y') => $v ? \Illuminate\Support\Carbon::parse($v)->format($f) : '—';
  $campaniaActual = $campania;
  $zona = \App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores::ZONA;
  $periodoTexto = $campaniaActual ? 'Periodo '.$campaniaActual->periodo : 'Histórico hasta '.now($zona)->year;
  $corteTexto = 'Corte '.now($zona)->format('d/m/Y');
  $alcance = ($campaniaActual->nombre ?? 'Todas las campañas').' · '.$periodoTexto.' · '.$corteTexto;
  $coberturaTexto = ($resumen['cobertura'] ?? null) === null ? 'Cobertura sin datos' : 'Cobertura '.$formato($resumen['cobertura']).' %';
  $abierta = $campaniaActual && $campaniaActual->estado === 'ABIERTA';
  $estadosCampania = ['ABIERTA' => ['Abierta', 'sg-badge-success'], 'PROGRAMADA' => ['Programada', 'sg-badge-warning'], 'CERRADA' => ['Cerrada', '']];
  $etiquetas = [
    'sin_enlace' => ['Sin enlace', ''], 'pendiente' => ['Pendiente', 'sg-badge-warning'],
    'respondida' => ['Respondida', 'sg-badge-success'], 'vencida' => ['Vencida', 'sg-badge-danger'],
  ];
  $faltan = $evaluables->whereIn('estado', ['sin_enlace', 'vencida'])->count();
  $enlacesGenerados = session('enlaces_empresa');

  $barras = fn (array $ranking, string $color) => [
    'tipo' => 'bar', 'horizontal' => true, 'unidad' => 'n',
    'etiquetas' => array_column($ranking, 'nombre'),
    'series' => [[
      'nombre' => 'Organizaciones', 'color' => $color, 'valores' => array_column($ranking, 'cantidad'),
      'textos' => array_map(fn ($f) => $f['cantidad'].' de '.$f['base'], $ranking),
    ]],
  ];
  $filasRanking = fn (array $ranking) => array_map(fn ($f) => [$f['nombre'], $f['cantidad'], $formato($f['porcentaje']).' %'], $ranking);
@endphp
@push('styles')
  @include('modules.seguimiento-egresado.partials.estilos-graficos')
  <style>
    .sg-emp-barra { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
    .sg-emp-barra .sg-field { min-width: 280px; flex: 1; max-width: 520px; }
    .sg-emp-tabla th, .sg-emp-tabla td { white-space: nowrap; vertical-align: middle; padding-left: 10px; padding-right: 10px; }
    .sg-emp-tabla form { margin: 0; }
    .sg-emp-cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
    .sg-emp-cab h2 { margin: 0; }
    .sg-emp-lote { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin: 10px 0 14px; }
    #dlg-enlaces-empresa { width: min(98vw, 1200px); max-width: 98vw; max-height: 92vh; text-align: left; overflow: auto; }
    #dlg-enlaces-empresa .sg-detalle-cuerpo { padding: 22px; }
    #dlg-enlaces-empresa input.sg-input { font-family: var(--sg-font-code); font-size: 12px; min-width: 280px; }
    .sg-emp-pie { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; margin-top: 14px; }
  </style>
@endpush
@section('content')
<section class="sg-heading">
  <h1>Encuesta empresarial</h1>
  <p>Las empresas responden con un enlace, sin crear cuenta. Elige la campaña y entrega a cada empresa su enlace.</p>
</section>

@if ($error)
  <div class="sg-alert sg-alert-danger sg-bloque" role="alert">
    No se pudieron cargar los datos en este momento.
    <a class="sg-link" href="{{ route('seguimiento-egresado.empresas') }}">Inténtalo de nuevo</a>.
  </div>
@else
  {{-- CAMPAÑA --}}
  <section class="sg-panel sg-bloque" aria-labelledby="sg-emp-campania">
    <h2 class="sg-section-title" id="sg-emp-campania">Campaña de empleadores</h2>
    <div class="sg-emp-barra">
      @if ($campanias->isNotEmpty())
        <form method="get" action="{{ route('seguimiento-egresado.empresas') }}" class="sg-field">
          <label class="sg-label" for="campania">Campaña</label>
          <select class="sg-select" id="campania" name="campania" onchange="this.form.submit()">
            @foreach ($campanias as $c)
              <option value="{{ $c->id }}" @selected($campaniaId === (int) $c->id)>{{ $c->nombre }} · {{ $c->periodo }}</option>
            @endforeach
          </select>
        </form>
      @endif
      @if ($puedeGenerar)
        <button type="button" class="sg-button {{ $campanias->isEmpty() ? '' : 'sg-button-secondary' }}" onclick="abrirNuevaCampania('EMPRESAS')">+ Nueva campaña de empleadores</button>
      @endif
    </div>
    @if ($campaniaActual)
      @php [$estadoTexto, $estadoClase] = $estadosCampania[$campaniaActual->estado] ?? [$campaniaActual->estado, '']; @endphp
      <p class="sg-help" style="margin-top: 10px;">
        <span class="sg-badge {{ $estadoClase }}">{{ $estadoTexto }}</span>
        Del {{ $fecha($campaniaActual->fecha_inicio) }} al {{ $fecha($campaniaActual->fecha_fin) }}. Los enlaces valen hasta el cierre de la campaña.
        @unless ($abierta) No se pueden generar enlaces mientras la campaña no esté abierta. @endunless
      </p>
    @else
      <p class="sg-help" style="margin-top: 10px;">Aún no hay campañas para empleadores. Crea la primera con el botón de arriba.</p>
    @endif
  </section>

  @if ($campaniaActual)
    {{-- ① OPINIÓN SOBRE CADA EGRESADO --}}
    <section class="sg-panel sg-bloque" aria-labelledby="sg-emp-desempeno">
      <div class="sg-emp-cab">
        <h2 class="sg-section-title" id="sg-emp-desempeno" style="margin: 0;">Opinión de la empresa sobre cada egresado</h2>
        @if ($puedeGenerar && $abierta)
          <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces.lote') }}"
                data-sg-confirmar="Se generará un enlace para cada egresado que aún no lo tiene o cuyo enlace venció. ¿Continuar?">
            @csrf
            <input type="hidden" name="id_campania" value="{{ $campaniaId }}">
            <input type="hidden" name="contexto" value="DESEMPENO">
            <button type="submit" class="sg-button" @disabled($faltan === 0)>Generar los enlaces que faltan{{ $faltan > 0 ? ' ('.$faltan.')' : '' }}</button>
          </form>
        @endif
      </div>
      <p class="sg-help" style="margin-bottom: 10px;">Aquí están los egresados de las promociones de esta campaña que autorizaron contactar a su empresa. La empresa y el contacto son los que ellos mismos indicaron.</p>
      @if ($evaluables->isEmpty())
        <p class="sg-help">Todavía ningún egresado de estas promociones autorizó contactar a su empresa. Aparecerán cuando respondan la encuesta de seguimiento.</p>
      @else
        <div class="sg-table-wrap">
          <table class="sg-table sg-emp-tabla">
            <caption class="sg-sr-only">Egresados a evaluar y el estado de su enlace</caption>
            <thead>
              <tr><th scope="col">Egresado</th><th scope="col">Promoción</th><th scope="col">Empresa</th><th scope="col">Contacto del jefe</th><th scope="col">Estado</th><th scope="col">Acción</th></tr>
            </thead>
            <tbody>
              @foreach ($evaluables as $fila)
                @php
                  $inv = $fila->invitacion;
                  [$texto, $clase] = $etiquetas[$fila->estado];
                @endphp
                <tr>
                  <td><strong>{{ $fila->nombre }}</strong></td>
                  <td>{{ $fila->anio_ingreso }}</td>
                  <td>{{ $fila->empresa ?: '—' }}</td>
                  <td>{{ $fila->contacto ?: '—' }}</td>
                  <td>
                    <span class="sg-badge {{ $clase }}">{{ $texto }}</span>
                    @if ($fila->estado === 'pendiente')
                      <span class="sg-help" style="display: block;">hasta el {{ $fecha($inv->expira_en) }}{{ $inv->enviado_en ? ' · entregado el '.$fecha($inv->enviado_en) : '' }}</span>
                    @elseif ($fila->estado === 'vencida')
                      <span class="sg-help" style="display: block;">venció el {{ $fecha($inv->expira_en) }}</span>
                    @endif
                  </td>
                  <td>
                    @if ($fila->estado === 'respondida')
                      <button type="button" class="sg-button sg-button-secondary" data-respuesta="{{ route('seguimiento-egresado.empresas.respuesta', $fila->invitacion->id) }}">Ver respuesta</button>
                    @elseif ($puedeGenerar && $abierta)
                      @if ($fila->estado === 'sin_enlace')
                        <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces') }}">
                          @csrf
                          <input type="hidden" name="id_campania" value="{{ $campaniaId }}">
                          <input type="hidden" name="contexto" value="DESEMPENO">
                          <input type="hidden" name="id_egresado" value="{{ $fila->id }}">
                          <button type="submit" class="sg-button sg-button-secondary">Generar enlace</button>
                        </form>
                      @else
                        <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces.renovar', $inv->id) }}"
                              @if ($fila->estado === 'pendiente') data-sg-confirmar="{{ $inv->enviado_en ? 'Ya entregaste este enlace a la empresa. Si generas uno nuevo, el anterior dejará de funcionar.' : 'Si generas un enlace nuevo, el anterior dejará de funcionar.' }} ¿Continuar?" @endif>
                          @csrf
                          <input type="hidden" name="confirmar_reemplazo" value="1">
                          <button type="submit" class="sg-button sg-button-secondary">Enlace nuevo</button>
                        </form>
                      @endif
                    @else
                      <span class="sg-help">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>

    {{-- ② DEMANDA DE HABILIDADES --}}
    <section class="sg-panel sg-bloque" aria-labelledby="sg-emp-mercado">
      <h2 class="sg-section-title" id="sg-emp-mercado">Demanda de habilidades (encuesta general)</h2>
      <p class="sg-help">No es sobre un egresado: pregunta a cualquier empresa qué habilidades busca. Cada enlace admite una sola respuesta, así que genera uno por empresa.</p>
      @if ($puedeGenerar && $abierta)
        <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces.lote') }}" class="sg-emp-lote">
          @csrf
          <input type="hidden" name="id_campania" value="{{ $campaniaId }}">
          <input type="hidden" name="contexto" value="MERCADO">
          <div class="sg-field">
            <label class="sg-label" for="cantidad-mercado">¿Cuántos enlaces?</label>
            <input class="sg-input" id="cantidad-mercado" type="number" name="cantidad" min="1" max="50" value="5" required style="width: 110px;">
          </div>
          <button type="submit" class="sg-button sg-button-secondary">Generar enlaces</button>
        </form>
      @endif
      @if ($mercado->isEmpty())
        <p class="sg-help">Aún no hay enlaces de esta encuesta.</p>
      @else
        <div class="sg-table-wrap">
          <table class="sg-table sg-emp-tabla">
            <caption class="sg-sr-only">Enlaces de la encuesta general de mercado</caption>
            <thead><tr><th scope="col">N.º</th><th scope="col">Estado</th><th scope="col">Empresa que respondió</th><th scope="col">Acción</th></tr></thead>
            <tbody>
              @foreach ($mercado as $n => $m)
                @php [$texto, $clase] = $etiquetas[$m->estado_fila]; @endphp
                <tr>
                  <td>{{ $n + 1 }}</td>
                  <td>
                    <span class="sg-badge {{ $clase }}">{{ $texto }}</span>
                    @if ($m->estado_fila === 'pendiente')<span class="sg-help" style="display: block;">hasta el {{ $fecha($m->expira_en) }}{{ $m->enviado_en ? ' · entregado el '.$fecha($m->enviado_en) : '' }}</span>@endif
                  </td>
                  <td>{{ $m->organizacion_informada ?: '—' }}</td>
                  <td>
                    @if ($m->estado_fila === 'respondida')
                      <button type="button" class="sg-button sg-button-secondary" data-respuesta="{{ route('seguimiento-egresado.empresas.respuesta', $m->id) }}">Ver respuesta</button>
                    @elseif ($puedeGenerar && $abierta)
                      <form method="POST" action="{{ route('seguimiento-egresado.empresas.enlaces.renovar', $m->id) }}"
                            @if ($m->estado_fila === 'pendiente') data-sg-confirmar="{{ $m->enviado_en ? 'Ya entregaste este enlace. Si generas uno nuevo, el anterior dejará de funcionar.' : 'Si generas un enlace nuevo, el anterior dejará de funcionar.' }} ¿Continuar?" @endif>
                        @csrf
                        <input type="hidden" name="confirmar_reemplazo" value="1">
                        <button type="submit" class="sg-button sg-button-secondary">Enlace nuevo</button>
                      </form>
                    @else
                      <span class="sg-help">—</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>
  @endif

  {{-- ③ RESULTADOS: totales y gráficas de la campaña elegida --}}
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
@endif

@if ($enlacesGenerados)
  <dialog class="sg-dialogo" id="dlg-enlaces-empresa" aria-labelledby="dlg-enlaces-titulo">
    <div class="sg-detalle-cuerpo">
      <div class="sg-detalle-cabecera">
        <div>
          <h2 class="sg-dialogo-titulo" id="dlg-enlaces-titulo" style="text-align: left; margin: 0 0 4px;">{{ session('enlaces_titulo', 'Enlaces generados') }}</h2>
          <p class="sg-help" style="margin: 0;">Se muestran una sola vez. Cópialos o descárgalos antes de cerrar y entrégalos a cada empresa. Si se pierde uno, genera un enlace nuevo.</p>
        </div>
        <button type="button" class="sg-dialogo-cerrar" aria-label="Cerrar" onclick="document.getElementById('dlg-enlaces-empresa').close()">✕</button>
      </div>
      <div class="sg-table-wrap">
        <table class="sg-table sg-emp-tabla" id="tabla-enlaces-empresa">
          <thead><tr><th scope="col">Egresado</th><th scope="col">Empresa</th><th scope="col">Contacto del jefe</th><th scope="col">Vence</th><th scope="col">Enlace</th><th scope="col">Copiar</th></tr></thead>
          <tbody>
            @foreach ($enlacesGenerados as $e)
              <tr data-id="{{ $e['id'] }}">
                <td>{{ $e['egresado'] }}</td>
                <td>{{ $e['empresa'] ?: '—' }}</td>
                <td>{{ $e['contacto'] ?: '—' }}</td>
                <td>{{ $e['expira_en'] }}</td>
                <td><input class="sg-input" type="text" readonly value="{{ $e['url'] }}" aria-label="Enlace de {{ $e['egresado'] }}" onfocus="this.select()"></td>
                <td><button type="button" class="sg-button sg-button-secondary" data-copiar-fila>Copiar</button></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="sg-emp-pie">
        <button type="button" class="sg-button sg-button-secondary" id="btn-copiar-todos">Copiar todos</button>
        <button type="button" class="sg-button sg-button-secondary" id="btn-csv-enlaces">Descargar lista (CSV)</button>
        <button type="button" class="sg-button" onclick="document.getElementById('dlg-enlaces-empresa').close()">Cerrar</button>
      </div>
    </div>
  </dialog>
@endif

@include('modules.seguimiento-egresado.partials.campania-formulario', ['promociones' => $promociones, 'periodos' => $periodos, 'tipoFijo' => 'EMPRESAS', 'volverA' => 'empresas'])
@endsection
@push('scripts')
  <script src="{{ asset('js/seguimiento-egresado/vendor/chart-4.4.9.umd.js') }}"></script>
  <script src="{{ asset('js/seguimiento-egresado/seguimiento-graficos.js') }}"></script>
  <script>
    (() => {
      @if ($errors->any())
        if (window.sgDialogo) window.sgDialogo({ tipo: 'error', mensaje: @json($errors->all()).join('\n') });
      @endif

      const dialogo = document.getElementById('dlg-enlaces-empresa');
      if (!dialogo) return;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const urlEnviado = (id) => '{{ url('seguimiento-egresado/empresas/enlaces') }}/' + id + '/enviado';
      const filas = [...dialogo.querySelectorAll('tbody tr')];
      const datos = (tr) => [...tr.children].slice(0, 3).map((td) => td.textContent.trim()).concat(tr.querySelector('input').value);
      const marcar = (tr) => fetch(urlEnviado(tr.dataset.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } }).catch(() => {});
      const copiar = async (texto) => {
        try { await navigator.clipboard.writeText(texto); return true; } catch (_) { return false; }
      };

      dialogo.querySelectorAll('[data-copiar-fila]').forEach((boton) => boton.addEventListener('click', async () => {
        const tr = boton.closest('tr');
        if (await copiar(tr.querySelector('input').value)) { boton.textContent = 'Copiado'; marcar(tr); }
        else { tr.querySelector('input').select(); }
      }));

      document.getElementById('btn-copiar-todos').addEventListener('click', async (e) => {
        const texto = filas.map((tr) => { const [egresado, empresa, contacto, url] = datos(tr); return `${egresado} · ${empresa} · ${contacto}\n${url}`; }).join('\n\n');
        if (await copiar(texto)) { e.target.textContent = 'Copiados'; filas.forEach(marcar); }
      });

      document.getElementById('btn-csv-enlaces').addEventListener('click', () => {
        const comillas = (v) => '"' + String(v).replace(/"/g, '""') + '"';
        const lineas = [['Egresado', 'Empresa', 'Contacto del jefe', 'Enlace'].map(comillas).join(',')]
          .concat(filas.map((tr) => datos(tr).map(comillas).join(',')));
        const blob = new Blob(['\ufeff' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = 'enlaces-encuesta-empresarial.csv'; a.click();
        URL.revokeObjectURL(a.href);
        filas.forEach(marcar);
      });

      dialogo.showModal();
    })();
  </script>
@endpush
