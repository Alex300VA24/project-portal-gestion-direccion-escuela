@extends('modules.seguimiento-egresado.layout')
@section('title', 'Evaluación institucional')
@section('section_name', 'Evaluación institucional')

@push('styles')
<style>
  .sg-resumen-global { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: var(--sg-space-5); }
  .sg-resumen-global > div { background: var(--sg-surface); border: var(--sg-stroke); border-radius: var(--sg-radius); padding: 12px 16px; }
  .sg-resumen-global strong { display: block; font-size: 1.5rem; line-height: 1.2; }
  .sg-resumen-global span { font-size: 13px; color: var(--sg-muted); }
  .sg-emp-tabla th, .sg-emp-tabla td { white-space: nowrap; vertical-align: middle; padding-left: 10px; padding-right: 10px; }
  .sg-emp-cab { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
  .sg-objetivo { display: grid; grid-template-columns: 70px 1fr; gap: 10px; padding: 10px 0; border-top: 1px solid var(--sg-border); }
  .sg-objetivo:first-of-type { border-top: 0; }
  .sg-form-dialogo .sg-dialogo-cuerpo { text-align: left; padding: 24px 24px 8px; max-height: 70vh; overflow: auto; }
  .sg-form-dialogo .sg-dialogo-pie { gap: 10px; padding: 8px 24px 22px; justify-content: flex-end; flex-wrap: wrap; }
  .sg-form-dialogo .sg-campos { display: grid; gap: 14px; margin-top: 14px; }
  .sg-form-dialogo .sg-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .sg-celda-texto { white-space: normal !important; min-width: 220px; max-width: 320px; }
  @media (max-width: 520px) { .sg-form-dialogo .sg-dos { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
@php
  $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y') : '—';
  $itemsOe = $objetivos->map(fn ($o) => ['id' => $o->id, 'codigo' => $o->codigo, 'nombre' => $o->titulo]);
  $itemsComp = $competencias->map(fn ($c) => ['id' => $c->id, 'codigo' => $c->codigo, 'nombre' => $c->nombre]);
  $estadosCambio = ['PROPUESTA' => ['Propuesta', ''], 'EVALUACION' => ['En evaluación', 'sg-badge-warning'], 'APROBADA' => ['Aprobada', 'sg-badge-warning'], 'EN_EJECUCION' => ['En ejecución', 'sg-badge-warning'], 'IMPLEMENTADA' => ['Implementada', 'sg-badge-success'], 'DESCARTADA' => ['Descartada', 'sg-badge-danger']];
  $tiposCambio = ['PERFIL' => 'Perfil de egreso (F10)', 'OE' => 'Objetivos educacionales (F11)', 'OTRA' => 'Otro cambio del currículo'];
  $estadosPlan = ['PLANIFICADA' => 'Planificado', 'EN_EJECUCION' => 'En ejecución', 'FINALIZADA' => 'Finalizado', 'EVALUADA' => 'Evaluado'];
  $tiposPlan = ['VINCULACION' => 'Vinculación con egresados (F5)', 'INSERCION' => 'Inserción laboral (F7)'];
  $estadosDireccion = ['APROBADA', 'EN_EJECUCION', 'IMPLEMENTADA', 'DESCARTADA'];
@endphp

<section class="sg-heading" aria-labelledby="sg-inst-title">
  <h1 id="sg-inst-title">Evaluación institucional</h1>
  <p>Aquí se registra si se lograron los objetivos del programa y qué cambios se hacen a partir de los resultados. Lo que prepara la Secretaría lo valida la Dirección.</p>
</section>

<div class="sg-resumen-global" aria-label="Resumen">
  <div>
    <strong>{{ $resumen['i3_total'] ? $resumen['i3_logrados'].' de '.$resumen['i3_total'] : '—' }}</strong>
    <span>objetivos logrados (I3){{ $resumen['i3_valor'] !== null ? ' · '.number_format($resumen['i3_valor'], 1, ',', '.').' %' : '' }}</span>
  </div>
  <div>
    <strong>{{ $resumen['competencias_conformes'] !== null ? $resumen['competencias_conformes'].' de '.$resumen['competencias_total'] : '—' }}</strong>
    <span>competencias en nivel bueno o mejor</span>
  </div>
  <div><strong>{{ $resumen['i6'] }}</strong><span>{{ $resumen['i6'] === 1 ? 'cambio implementado' : 'cambios implementados' }} (I6)</span></div>
  <div><strong>{{ $resumen['por_validar'] }}</strong><span>{{ $resumen['por_validar'] === 1 ? 'pendiente de la Dirección' : 'pendientes de la Dirección' }}</span></div>
</div>

{{-- 1 · OBJETIVOS EDUCACIONALES --}}
<section class="sg-panel sg-bloque" id="objetivos" aria-labelledby="t-objetivos">
  <h2 class="sg-section-title" id="t-objetivos">1. Objetivos educacionales del programa</h2>
  <p class="sg-help" style="margin-bottom: 6px;">Lo que se espera que los egresados logren en su ejercicio profesional{{ $objetivos->first()?->planCurricular ? ' · '.$objetivos->first()->planCurricular->codigo_version : '' }}.</p>
  @forelse ($objetivos as $objetivo)
    <div class="sg-objetivo"><strong>{{ $objetivo->codigo }}</strong><span>{{ $objetivo->descripcion }}</span></div>
  @empty
    <p class="sg-help">Aún no hay objetivos registrados.</p>
  @endforelse
</section>

{{-- 2 · INFORME DE LOGRO DE LOS OBJETIVOS (F8) --}}
@include('modules.seguimiento-egresado.partials.informe-logro', [
  'id' => 'oe', 'anclaje' => 'logro', 'numero' => 2, 'titulo' => 'Informe de logro de los objetivos (F8)',
  'ayuda' => 'Por cada promoción y periodo, el nivel de logro de cada objetivo. Con los informes validados se calcula el indicador I3.',
  'items' => $itemsOe, 'informes' => $informesOe, 'niveles' => $niveles, 'promociones' => $promociones, 'periodos' => $periodos,
  'ruta' => route('seguimiento-egresado.institucional.informe-logro'), 'conDecisiones' => true, 'formato' => 'F8',
  'puedeRegistrar' => $puedeRegistrar, 'puedeValidar' => $puedeValidar,
])

{{-- 3 · INFORME DE COMPETENCIAS (F9) --}}
@include('modules.seguimiento-egresado.partials.informe-logro', [
  'id' => 'comp', 'anclaje' => 'competencias', 'numero' => 3, 'titulo' => 'Informe de logro de las competencias (F9)',
  'ayuda' => 'Lo mismo para las competencias genéricas y específicas del perfil de egreso.',
  'items' => $itemsComp, 'informes' => $informesCompetencia, 'niveles' => $niveles, 'promociones' => $promociones, 'periodos' => $periodos,
  'ruta' => route('seguimiento-egresado.institucional.informe-competencias'), 'conDecisiones' => false, 'formato' => 'F9',
  'puedeRegistrar' => $puedeRegistrar, 'puedeValidar' => $puedeValidar,
])

{{-- 4 · CAMBIOS QUE SE DEBEN HACER (F10 / F11) --}}
<section class="sg-panel sg-bloque" id="cambios" aria-labelledby="t-cambios">
  <div class="sg-emp-cab">
    <h2 class="sg-section-title" id="t-cambios" style="margin: 0;">4. Cambios que se deben hacer (F10 y F11)</h2>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      @if ($puedeRegistrar)<button type="button" class="sg-button" onclick="abrirCambio(null)">+ Registrar cambio</button>@endif
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.formato', 'F10') }}">Descargar F10 (CSV)</a>
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.formato', 'F11') }}">Descargar F11 (CSV)</a>
    </div>
  </div>
  <p class="sg-help" style="margin-bottom: 10px;">Registro de lo que «dice» hoy y lo que «debe decir» en el perfil de egreso o en los objetivos. <strong>Cada cambio implementado, con su documento de respaldo, suma 1 al indicador I6</strong> (hoy: {{ $resumen['i6'] }}).</p>
  @if ($cambios->isEmpty())
    <p class="sg-help">Aún no hay cambios registrados.</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-emp-tabla">
        <caption class="sg-sr-only">Cambios al perfil de egreso y a los objetivos educacionales</caption>
        <thead><tr><th scope="col">Cambio</th><th scope="col">Qué cambia</th><th scope="col">Dice</th><th scope="col">Debe decir</th><th scope="col">Estado</th><th scope="col">Documento</th><th scope="col">Acción</th></tr></thead>
        <tbody>
          @foreach ($cambios as $cambio)
            @php
              [$estadoTexto, $estadoClase] = $estadosCambio[$cambio->estado] ?? [$cambio->estado, ''];
              $bloqueado = ! $puedeValidar && in_array($cambio->estado, $estadosDireccion, true);
              $datosCambio = [
                  'id' => $cambio->id, 'tipo' => $cambio->tipo, 'titulo' => $cambio->titulo, 'dice' => $cambio->dice, 'debe_decir' => $cambio->debe_decir,
                  'descripcion' => $cambio->descripcion, 'estado' => $cambio->estado, 'documento' => $cambio->documento_referencia,
                  'fecha_implementacion' => $cambio->fecha_implementacion ? \Illuminate\Support\Carbon::parse($cambio->fecha_implementacion)->format('Y-m-d') : null,
                  'origen' => (bool) $cambio->origen_seguimiento, 'codigo' => $cambio->codigo,
              ];
            @endphp
            <tr>
              <td class="sg-celda-texto"><strong>{{ $cambio->titulo }}</strong><br><span class="sg-help">{{ $cambio->codigo }}</span></td>
              <td>{{ $tiposCambio[$cambio->tipo] ?? $cambio->tipo }}</td>
              <td class="sg-celda-texto">{{ $cambio->dice ?: '—' }}</td>
              <td class="sg-celda-texto">{{ $cambio->debe_decir ?: '—' }}</td>
              <td>
                <span class="sg-badge {{ $estadoClase }}">{{ $estadoTexto }}</span>
                @if ($cambio->estado === 'IMPLEMENTADA')<span class="sg-help" style="display: block;">{{ $fecha($cambio->fecha_implementacion) }}{{ $cambio->origen_seguimiento && $cambio->documento_referencia ? ' · suma a I6' : '' }}</span>@endif
              </td>
              <td class="sg-celda-texto">{{ $cambio->documento_referencia ?: '—' }}</td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="abrirCambio(@js($datosCambio))">{{ ($puedeRegistrar && ! $bloqueado) ? 'Editar' : 'Ver' }}</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

{{-- 5 · PLANES DE ACCIÓN (F5 / F7) --}}
<section class="sg-panel sg-bloque" id="planes" aria-labelledby="t-planes">
  <div class="sg-emp-cab">
    <h2 class="sg-section-title" id="t-planes" style="margin: 0;">5. Planes de acción (F5 y F7)</h2>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      @if ($puedeRegistrar)<button type="button" class="sg-button" onclick="abrirPlan(null)">+ Registrar plan</button>@endif
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.formato', 'F5') }}">Descargar F5 (CSV)</a>
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.formato', 'F7') }}">Descargar F7 (CSV)</a>
    </div>
  </div>
  <p class="sg-help" style="margin-bottom: 10px;">Actividades para vincularse con los egresados (F5) y para mejorar su inserción laboral (F7), con su avance.</p>
  @if ($planesAccion->isEmpty())
    <p class="sg-help">Aún no hay planes registrados.</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-emp-tabla">
        <thead><tr><th scope="col">Plan</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Avance</th><th scope="col">Fecha límite</th><th scope="col">Acción</th></tr></thead>
        <tbody>
          @foreach ($planesAccion as $plan)
            @php $datosPlan = ['id' => $plan->id, 'titulo' => $plan->titulo, 'tipo' => $plan->tipo, 'estado' => $plan->estado, 'actividad' => $plan->actividad, 'responsable' => $plan->responsable, 'fecha_limite' => $plan->fecha_limite ? \Illuminate\Support\Carbon::parse($plan->fecha_limite)->format('Y-m-d') : null, 'grado' => $plan->grado_cumplimiento, 'observacion' => $plan->observacion]; @endphp
            <tr>
              <td class="sg-celda-texto"><strong>{{ $plan->titulo }}</strong><br><span class="sg-help">{{ $plan->codigo }}</span></td>
              <td>{{ $tiposPlan[$plan->tipo] ?? $plan->tipo }}</td>
              <td>{{ $estadosPlan[$plan->estado] ?? $plan->estado }}</td>
              <td>{{ $plan->grado_cumplimiento !== null ? number_format($plan->grado_cumplimiento, 0).' %' : '—' }}</td>
              <td>{{ $fecha($plan->fecha_limite) }}</td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="abrirPlan(@js($datosPlan))">{{ $puedeRegistrar ? 'Editar' : 'Ver' }}</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

{{-- Ventana: cambio «Dice / Debe decir» --}}
<dialog class="sg-dialogo sg-form-dialogo" id="dlg-cambio" style="width: min(94vw, 600px);" aria-labelledby="dlg-cambio-titulo">
  <form method="POST" action="{{ route('seguimiento-egresado.institucional.cambio') }}" id="form-cambio">
    @csrf
    <input type="hidden" name="id" id="cambio-id">
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-cambio-titulo">Registrar cambio</h2>
      <p class="sg-dialogo-mensaje" style="white-space: normal;" id="cambio-aviso">Qué se cambia, qué dice hoy y cómo debe decir.</p>
      <div class="sg-campos">
        <div class="sg-field">
          <label class="sg-label" for="cambio-tipo">¿Qué se cambia? *</label>
          <select class="sg-select" id="cambio-tipo" name="tipo" required>
            @foreach ($tiposCambio as $valor => $texto)<option value="{{ $valor }}">{{ $texto }}</option>@endforeach
          </select>
        </div>
        <div class="sg-field"><label class="sg-label" for="cambio-titulo-in">Título *</label><input class="sg-input" id="cambio-titulo-in" name="titulo" maxlength="200" required></div>
        <div class="sg-field" data-dice><label class="sg-label" for="cambio-dice">Dice *</label><textarea class="sg-textarea" id="cambio-dice" name="dice" rows="2" maxlength="2000"></textarea></div>
        <div class="sg-field" data-dice><label class="sg-label" for="cambio-debe">Debe decir *</label><textarea class="sg-textarea" id="cambio-debe" name="debe_decir" rows="2" maxlength="2000"></textarea></div>
        <div class="sg-field"><label class="sg-label" for="cambio-motivo">¿Por qué se hace? *</label><textarea class="sg-textarea" id="cambio-motivo" name="descripcion" rows="2" maxlength="2000" required></textarea></div>
        <div class="sg-field">
          <label class="sg-label" for="cambio-estado">Estado *</label>
          <select class="sg-select" id="cambio-estado" name="estado" required>
            @foreach ($estadosCambio as $valor => [$texto])
              <option value="{{ $valor }}" @if (! $puedeValidar && in_array($valor, $estadosDireccion, true)) disabled @endif>{{ $texto }}{{ (! $puedeValidar && in_array($valor, $estadosDireccion, true)) ? ' (solo Dirección)' : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="sg-dos" id="cambio-implementado" hidden>
          <div class="sg-field"><label class="sg-label" for="cambio-documento">Documento de respaldo *</label><input class="sg-input" id="cambio-documento" name="documento_referencia" maxlength="200" placeholder="Resolución o acta"></div>
          <div class="sg-field"><label class="sg-label" for="cambio-fecha">Fecha de implementación *</label><input class="sg-input" id="cambio-fecha" name="fecha_implementacion" type="date" max="{{ now()->toDateString() }}"></div>
        </div>
        <label style="display: flex; gap: 8px; align-items: center; font-size: 14px;"><input type="hidden" name="origen_seguimiento" value="0"><input type="checkbox" name="origen_seguimiento" value="1" id="cambio-origen" checked> Nace de los resultados del seguimiento a egresados</label>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-cambio').close()">Cerrar</button>
      @if ($puedeRegistrar)<button type="submit" id="cambio-guardar">Guardar</button>@endif
    </div>
  </form>
</dialog>

{{-- Ventana: plan de acción --}}
<dialog class="sg-dialogo sg-form-dialogo" id="dlg-plan-accion" style="width: min(94vw, 560px);" aria-labelledby="dlg-plan-titulo">
  <form method="POST" action="{{ route('seguimiento-egresado.institucional.guardar-plan') }}" id="form-plan-accion">
    @csrf
    <input type="hidden" name="id" id="plan-id">
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-plan-titulo">Registrar plan de acción</h2>
      <div class="sg-campos">
        <div class="sg-field"><label class="sg-label" for="plan-titulo-in">Nombre del plan *</label><input class="sg-input" id="plan-titulo-in" name="titulo" maxlength="200" required></div>
        <div class="sg-dos">
          <div class="sg-field"><label class="sg-label" for="plan-tipo">Tipo *</label><select class="sg-select" id="plan-tipo" name="tipo" required>@foreach ($tiposPlan as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach</select></div>
          <div class="sg-field"><label class="sg-label" for="plan-estado">Estado *</label><select class="sg-select" id="plan-estado" name="estado" required>@foreach ($estadosPlan as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach</select></div>
        </div>
        <div class="sg-field"><label class="sg-label" for="plan-actividad">¿Qué se hará? *</label><textarea class="sg-textarea" id="plan-actividad" name="actividad" rows="2" maxlength="2000" required></textarea></div>
        <div class="sg-dos">
          <div class="sg-field"><label class="sg-label" for="plan-responsable">Responsable</label><input class="sg-input" id="plan-responsable" name="responsable" maxlength="160"></div>
          <div class="sg-field"><label class="sg-label" for="plan-fecha">Fecha límite</label><input class="sg-input" id="plan-fecha" name="fecha_limite" type="date"></div>
        </div>
        <div class="sg-dos">
          <div class="sg-field"><label class="sg-label" for="plan-grado">Avance (%)</label><input class="sg-input" id="plan-grado" name="grado_cumplimiento" type="number" min="0" max="100"></div>
        </div>
        <div class="sg-field"><label class="sg-label" for="plan-obs">Observaciones</label><textarea class="sg-textarea" id="plan-obs" name="observacion" rows="2" maxlength="2000"></textarea></div>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-plan-accion').close()">Cerrar</button>
      @if ($puedeRegistrar)<button type="submit">Guardar</button>@endif
    </div>
  </form>
</dialog>

<script>
  (() => {
    const poner = (id, v) => { const e = document.getElementById(id); if (e) e.value = v ?? ''; };

    // ----- Cambio «Dice / Debe decir»
    const dlgCambio = document.getElementById('dlg-cambio');
    const tipo = document.getElementById('cambio-tipo');
    const estado = document.getElementById('cambio-estado');
    const actualizarCambio = () => {
      document.querySelectorAll('#dlg-cambio [data-dice]').forEach((c) => {
        c.hidden = tipo.value === 'OTRA';
        c.querySelector('textarea').required = tipo.value !== 'OTRA';
      });
      const implementado = estado.value === 'IMPLEMENTADA';
      document.getElementById('cambio-implementado').hidden = !implementado;
      document.getElementById('cambio-documento').required = implementado;
      document.getElementById('cambio-fecha').required = implementado;
    };
    tipo.addEventListener('change', actualizarCambio);
    estado.addEventListener('change', actualizarCambio);
    window.abrirCambio = (d) => {
      document.getElementById('form-cambio').reset();
      poner('cambio-id', d?.id);
      document.getElementById('dlg-cambio-titulo').textContent = d ? 'Cambio ' + d.codigo : 'Registrar cambio';
      if (d) {
        tipo.value = d.tipo; poner('cambio-titulo-in', d.titulo); poner('cambio-dice', d.dice); poner('cambio-debe', d.debe_decir);
        poner('cambio-motivo', d.descripcion); estado.value = d.estado; poner('cambio-documento', d.documento); poner('cambio-fecha', d.fecha_implementacion);
        document.getElementById('cambio-origen').checked = !!d.origen;
      }
      const estadosDireccion = ['APROBADA', 'EN_EJECUCION', 'IMPLEMENTADA', 'DESCARTADA'];
      const soloLectura = {{ $puedeRegistrar ? 'false' : 'true' }} || (d && {{ $puedeValidar ? 'false' : 'true' }} && estadosDireccion.includes(d.estado));
      document.querySelectorAll('#form-cambio input:not([type=hidden]), #form-cambio select, #form-cambio textarea').forEach((c) => { c.disabled = soloLectura; });
      if (!soloLectura) { [...estado.options].forEach((o) => { o.disabled = {{ $puedeValidar ? 'false' : 'true' }} && estadosDireccion.includes(o.value); }); }
      const guardar = document.getElementById('cambio-guardar'); if (guardar) guardar.hidden = soloLectura;
      document.getElementById('cambio-aviso').textContent = soloLectura ? 'Solo la Dirección puede modificar un cambio ya aprobado o implementado.' : 'Qué se cambia, qué dice hoy y cómo debe decir.';
      actualizarCambio();
      dlgCambio.showModal();
    };
    document.getElementById('form-cambio').addEventListener('submit', () => document.querySelectorAll('#form-cambio input, #form-cambio select, #form-cambio textarea').forEach((c) => { c.disabled = false; }));

    // ----- Plan de acción
    window.abrirPlan = (d) => {
      document.getElementById('form-plan-accion').reset();
      poner('plan-id', d?.id);
      document.getElementById('dlg-plan-titulo').textContent = d ? 'Plan de acción' : 'Registrar plan de acción';
      if (d) {
        poner('plan-titulo-in', d.titulo); document.getElementById('plan-tipo').value = d.tipo; document.getElementById('plan-estado').value = d.estado;
        poner('plan-actividad', d.actividad); poner('plan-responsable', d.responsable); poner('plan-fecha', d.fecha_limite); poner('plan-grado', d.grado); poner('plan-obs', d.observacion);
      }
      document.getElementById('dlg-plan-accion').showModal();
    };

    @if ($errors->any())
      if (window.sgDialogo) window.sgDialogo({ tipo: 'error', mensaje: @json($errors->all()).join('\n') });
    @endif
  })();
</script>
@endsection
