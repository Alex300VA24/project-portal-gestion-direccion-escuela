{{--
  Sección de informes de logro (objetivos educacionales F8 o competencias F9): una tabla con un informe por promoción y periodo, y una
  ventana para registrarlo con el nivel de cada elemento a la vez.
  Variables: $id, $anclaje, $numero, $titulo, $ayuda, $items (id, codigo, nombre), $informes, $niveles, $promociones, $periodos,
             $ruta, $conDecisiones, $puedeRegistrar, $puedeValidar, $formato (F8/F9)
--}}
@php
  $clasesNivel = ['MUY_BUENO' => 'sg-badge-success', 'BUENO' => 'sg-badge-success', 'REGULAR' => 'sg-badge-warning', 'MALO' => 'sg-badge-danger', 'MUY_MALO' => 'sg-badge-danger'];
  $fechaCorta = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
@endphp
<section class="sg-panel sg-bloque" id="{{ $anclaje }}" aria-labelledby="t-{{ $id }}">
  <div class="sg-emp-cab">
    <h2 class="sg-section-title" id="t-{{ $id }}" style="margin: 0;">{{ $numero }}. {{ $titulo }}</h2>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      @if ($puedeRegistrar)<button type="button" class="sg-button" onclick="abrirInforme_{{ $id }}(null)">+ Nuevo informe</button>@endif
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.formato', $formato) }}">Descargar {{ $formato }} (Excel)</a>
    </div>
  </div>
  <p class="sg-help" style="margin-bottom: 10px;">{{ $ayuda }}</p>

  @if ($informes->isEmpty())
    <p class="sg-help">Aún no hay informes. {{ $puedeRegistrar ? 'Crea el primero con «+ Nuevo informe».' : '' }}</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-emp-tabla">
        <caption class="sg-sr-only">Informes de logro por promoción y periodo</caption>
        <thead>
          <tr>
            <th scope="col">Promoción</th><th scope="col">Periodo</th>
            @foreach ($items as $item)<th scope="col" title="{{ $item['nombre'] }}">{{ $item['codigo'] }}</th>@endforeach
            <th scope="col">Resultado</th><th scope="col">Estado</th><th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($informes as $informe)
            @php
              $datos = [
                  'cohorte' => $informe['cohorte_id'], 'periodo' => $informe['periodo'], 'estado' => $informe['estado'],
                  'niveles' => (object) array_map(fn ($n) => $n['id'], $informe['niveles']),
                  'observacion' => $informe['observacion'], 'decisiones' => $informe['decisiones'],
              ];
              $soloLectura = $informe['estado'] === 'VALIDADA' && ! $puedeValidar;
            @endphp
            <tr>
              <td><strong>Promoción {{ $informe['promocion'] }}</strong></td>
              <td>{{ $informe['periodo'] }}</td>
              @foreach ($items as $item)
                @php $nivel = $informe['niveles'][$item['id']] ?? null; @endphp
                <td>@if ($nivel)<span class="sg-badge {{ $clasesNivel[$nivel['codigo']] ?? '' }}">{{ $nivel['nombre'] }}</span>@else<span class="sg-help">—</span>@endif</td>
              @endforeach
              <td><strong>{{ $informe['logrados'] }} de {{ $informe['total'] }}</strong> <span class="sg-help">buenos o mejor</span></td>
              <td>
                <span class="sg-badge {{ $informe['estado'] === 'VALIDADA' ? 'sg-badge-success' : 'sg-badge-warning' }}">{{ $informe['estado'] === 'VALIDADA' ? 'Validado' : 'Borrador' }}</span>
              </td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="abrirInforme_{{ $id }}(@js($datos))">{{ ($puedeRegistrar && ! $soloLectura) ? ($informe['estado'] === 'BORRADOR' && $puedeValidar ? 'Revisar y validar' : 'Editar') : 'Ver' }}</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<dialog class="sg-dialogo sg-form-dialogo" id="dlg-{{ $id }}" style="width: min(94vw, 560px);" aria-labelledby="dlg-{{ $id }}-titulo">
  <form method="POST" action="{{ $ruta }}" id="form-{{ $id }}">
    @csrf
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-{{ $id }}-titulo">Informe de logro</h2>
      <p class="sg-dialogo-mensaje" style="white-space: normal;" id="dlg-{{ $id }}-aviso">{{ $puedeValidar ? 'Elige el nivel de logro de cada elemento. Al validar el informe, cuenta en los indicadores.' : 'Elige el nivel de logro de cada elemento. El informe queda como borrador hasta que la Dirección lo valide.' }}</p>
      <div class="sg-campos">
        <div class="sg-dos">
          <div class="sg-field">
            <label class="sg-label" for="{{ $id }}-cohorte">Promoción *</label>
            <select class="sg-select" id="{{ $id }}-cohorte" name="id_cohorte" required>
              <option value="">Elige…</option>
              @foreach ($promociones as $p)<option value="{{ $p->id }}">Promoción {{ $p->anio_ingreso }}</option>@endforeach
            </select>
          </div>
          <div class="sg-field">
            <label class="sg-label" for="{{ $id }}-periodo">Periodo *</label>
            <select class="sg-select" id="{{ $id }}-periodo" name="periodo" required>
              @foreach ($periodos as $per)<option value="{{ $per }}" @selected($per === now()->year.'-'.(now()->month <= 7 ? 'I' : 'II'))>{{ $per }}</option>@endforeach
            </select>
          </div>
        </div>
        @foreach ($items as $item)
          <div class="sg-field">
            <label class="sg-label" for="{{ $id }}-nivel-{{ $item['id'] }}"><strong>{{ $item['codigo'] }}</strong> · {{ $item['nombre'] }} *</label>
            <select class="sg-select" id="{{ $id }}-nivel-{{ $item['id'] }}" name="niveles[{{ $item['id'] }}]" required>
              <option value="">Nivel de logro…</option>
              @foreach ($niveles as $nivel)<option value="{{ $nivel->id }}">{{ $nivel->nombre }}</option>@endforeach
            </select>
          </div>
        @endforeach
        <div class="sg-field">
          <label class="sg-label" for="{{ $id }}-observacion">Conclusiones</label>
          <textarea class="sg-textarea" id="{{ $id }}-observacion" name="observacion" rows="2" maxlength="1000"></textarea>
        </div>
        @if ($conDecisiones)
          <div class="sg-field">
            <label class="sg-label" for="{{ $id }}-decisiones">Toma de decisiones</label>
            <textarea class="sg-textarea" id="{{ $id }}-decisiones" name="acciones_derivadas" rows="2" maxlength="1000"></textarea>
          </div>
        @endif
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-{{ $id }}').close()">Cerrar</button>
      @if ($puedeRegistrar)
        <button type="submit" name="accion" value="borrador" class="sg-dialogo-secundario" data-guardar>Guardar borrador</button>
        @if ($puedeValidar)<button type="submit" name="accion" value="validar" data-guardar>Guardar y validar</button>@endif
      @endif
    </div>
  </form>
</dialog>

<script>
  (() => {
    const dlg = document.getElementById('dlg-{{ $id }}');
    const form = document.getElementById('form-{{ $id }}');
    window.abrirInforme_{{ $id }} = (d) => {
      form.reset();
      const campos = form.querySelectorAll('select, textarea');
      campos.forEach((c) => { c.disabled = false; });
      if (d) {
        document.getElementById('{{ $id }}-cohorte').value = d.cohorte;
        document.getElementById('{{ $id }}-periodo').value = d.periodo;
        Object.entries(d.niveles).forEach(([item, nivel]) => { const s = document.getElementById('{{ $id }}-nivel-' + item); if (s) s.value = nivel; });
        document.getElementById('{{ $id }}-observacion').value = d.observacion || '';
        @if ($conDecisiones)document.getElementById('{{ $id }}-decisiones').value = d.decisiones || '';@endif
      }
      const promocion = document.getElementById('{{ $id }}-cohorte').selectedOptions[0];
      document.getElementById('dlg-{{ $id }}-titulo').textContent = d ? 'Informe de logro · ' + (promocion ? promocion.textContent : '') + ' · ' + d.periodo : 'Nuevo informe de logro';
      const validado = d && d.estado === 'VALIDADA';
      const bloqueado = {{ $puedeRegistrar ? 'false' : 'true' }} || (validado && {{ $puedeValidar ? 'false' : 'true' }});
      campos.forEach((c) => { c.disabled = bloqueado; });
      // La promoción y el periodo identifican el informe: al editar no se cambian (se crea otro informe).
      if (d && !bloqueado) { document.getElementById('{{ $id }}-cohorte').disabled = false; }
      form.querySelectorAll('[data-guardar]').forEach((b) => { b.hidden = bloqueado; });
      document.getElementById('dlg-{{ $id }}-aviso').textContent = bloqueado
        ? 'Este informe ya está validado. Solo la Dirección puede modificarlo.'
        : @js($puedeValidar ? 'Elige el nivel de logro de cada elemento. Al validar el informe, cuenta en los indicadores.' : 'Elige el nivel de logro de cada elemento. El informe queda como borrador hasta que la Dirección lo valide.');
      dlg.showModal();
    };
    // Los campos deshabilitados no se envían: se habilitan justo antes de enviar.
    form.addEventListener('submit', () => form.querySelectorAll('select, textarea').forEach((c) => { c.disabled = false; }));
  })();
</script>
