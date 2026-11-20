@extends('modules.seguimiento-egresado.empresas.publico')
@section('title', 'Encuesta empresarial')
@php
  $esDesempeno = $invitacion->contexto === 'DESEMPENO';
  $nombreEgresado = trim($invitacion->egresado_nombres.' '.$invitacion->egresado_paterno.' '.$invitacion->egresado_materno);
  $porCategoria = $habilidades->groupBy('categoria');
  $maximo = \App\Modules\SeguimientoEgresado\Services\Empresas\RespuestaEmpresaService::MAXIMO_HABILIDADES;
@endphp
@push('styles')
<style>
  .sg-enc-tarjeta { width: 100%; max-width: 720px; margin-inline: auto; padding: var(--sg-space-6); border: var(--sg-stroke); border-radius: var(--sg-radius); background: var(--sg-surface); }
  .sg-pub-contenido .sg-enc-tarjeta h1 { margin: 0; font-size: 24px; }
  .sg-enc-contexto { margin: var(--sg-space-1) 0 var(--sg-space-5); color: var(--sg-muted); }
  .sg-enc-contexto strong { color: var(--sg-text); }
  .sg-enc-progreso { margin-bottom: var(--sg-space-5); font-size: var(--sg-text-sm); font-weight: 600; color: var(--sg-muted); }
  .sg-enc-progreso[hidden] { display: none; }
  .sg-enc-barra { height: 6px; margin-top: var(--sg-space-2); border-radius: 3px; background: var(--sg-border); overflow: hidden; }
  .sg-enc-barra span { display: block; height: 100%; background: var(--sg-success); }
  .sg-enc-seccion { margin: 0 0 var(--sg-space-5); padding: 0; border: 0; min-width: 0; }
  .sg-enc-seccion[hidden] { display: none; }
  .sg-enc-seccion:focus { outline: none; }
  .sg-enc-seccion legend { float: left; width: 100%; padding: 0; margin: 0 0 var(--sg-space-3); color: var(--sg-navy); font-size: var(--sg-text-lg); font-weight: 650; }
  .sg-enc-seccion legend + * { clear: both; }
  .sg-enc-seccion .sg-form-grid { grid-template-columns: minmax(0, 1fr); }
  .sg-enc-grupo { margin: var(--sg-space-4) 0 var(--sg-space-2); color: var(--sg-muted); font-size: var(--sg-text-sm); font-weight: 600; }
  .sg-enc-opciones { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr)); gap: var(--sg-space-2); }
  .sg-enc-opcion { display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 6px 12px; border: 1px solid var(--sg-border); border-radius: var(--sg-radius-sm); background: var(--sg-surface); font-size: 14px; line-height: 1.3; cursor: pointer; }
  .sg-enc-opcion:hover { border-color: var(--sg-accent); }
  .sg-enc-opcion:has(input:checked) { border-color: var(--sg-accent); background: var(--sg-soft); font-weight: 600; }
  .sg-enc-opcion:has(input:disabled) { color: var(--sg-muted); cursor: not-allowed; }
  .sg-enc-opcion:focus-within { outline: 3px solid var(--sg-focus); outline-offset: 0; }
  .sg-enc-opcion input { flex: none; margin: 0; accent-color: var(--sg-accent); }
  .sg-enc-otra { margin-top: var(--sg-space-3); }
  .sg-enc-otra[hidden] { display: none; }
  .sg-enc-error-paso { margin-bottom: var(--sg-space-4); font-weight: 600; }
  .sg-enc-error-paso[hidden] { display: none; }
  .sg .sg-enc-seccion .sg-input[aria-invalid="true"], .sg .sg-enc-seccion .sg-select[aria-invalid="true"] { border-color: var(--sg-danger); box-shadow: inset 0 0 0 1px var(--sg-danger); }
  .sg-enc-errores { margin-bottom: var(--sg-space-4); }
  .sg-enc-errores ul { margin: var(--sg-space-2) 0 0; padding-left: var(--sg-space-5); }
  .sg-enc-acciones { display: flex; justify-content: space-between; gap: var(--sg-space-3); padding-top: var(--sg-space-4); border-top: var(--sg-stroke); }
  @media (max-width: 540px) { .sg-enc-tarjeta { padding: var(--sg-space-4); } }
  .sg-enc-acciones .sg-button[hidden] { display: none; }
  .sg-enc-acciones-fin { display: flex; gap: var(--sg-space-3); margin-left: auto; }
</style>
@endpush
@section('content')
<div class="sg-enc-tarjeta">
<h1>{{ $esDesempeno ? 'Evaluación de desempeño' : 'Demanda de habilidades' }}</h1>

<div class="sg-enc-contexto">
  @if ($esDesempeno)
    <p>Evaluarás a <strong>{{ $nombreEgresado }}</strong>, de {{ $invitacion->egresado_programa }}.</p>
  @else
    <p>Queremos saber qué busca tu organización. No evalúa a ninguna persona.</p>
  @endif
</div>

@if ($errors->any())
  <div class="sg-alert sg-alert-danger sg-enc-errores" role="alert" tabindex="-1" id="sg-enc-errores">
    <div>
      <strong>Revisa estos datos antes de enviar:</strong>
      <ul>
        @foreach (collect($errors->all())->unique() as $mensaje)
          <li>{{ $mensaje }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

<div class="sg-enc-progreso" data-sg-progreso hidden>
  <span data-sg-progreso-texto aria-live="polite"></span>
  <div class="sg-enc-barra" aria-hidden="true"><span data-sg-progreso-barra></span></div>
</div>

<div class="sg-alert sg-alert-danger sg-enc-error-paso" data-sg-error-paso role="alert" hidden></div>

<form method="post" action="{{ route('seguimiento-egresado.encuesta-empresa.responder', ['token' => $token]) }}" data-sg-encuesta novalidate>
  @csrf

  <fieldset class="sg-enc-seccion" data-sg-paso tabindex="-1">
    <legend>Tu organización</legend>
    <div class="sg-form-grid">
      <div class="sg-field">
        <label class="sg-label" for="organizacion">Organización</label>
        <input class="sg-input" id="organizacion" name="organizacion" type="text" required maxlength="180" autocomplete="organization" data-sg-mensaje="Indica el nombre de la organización."
               value="{{ old('organizacion', $invitacion->organizacion) }}" @error('organizacion') aria-invalid="true" aria-describedby="organizacion-error" @enderror>
        @error('organizacion')<p class="sg-field-error" id="organizacion-error">{{ $message }}</p>@enderror
      </div>
      <div class="sg-field">
        <label class="sg-label" for="sector">Sector</label>
        <select class="sg-select" id="sector" name="sector" required data-sg-mensaje="Selecciona el sector de la organización." @error('sector') aria-invalid="true" aria-describedby="sector-error" @enderror>
          <option value="">Selecciona un sector</option>
          @foreach ($sectores as $s)
            <option value="{{ $s->codigo }}" @selected(old('sector') === $s->codigo)>{{ $s->nombre }}</option>
          @endforeach
        </select>
        @error('sector')<p class="sg-field-error" id="sector-error">{{ $message }}</p>@enderror
      </div>
      <div class="sg-field">
        <label class="sg-label" for="cargo">Tu cargo (opcional)</label>
        <input class="sg-input" id="cargo" name="cargo" type="text" maxlength="120" autocomplete="organization-title" value="{{ old('cargo') }}" @error('cargo') aria-invalid="true" aria-describedby="cargo-error" @enderror>
        @error('cargo')<p class="sg-field-error" id="cargo-error">{{ $message }}</p>@enderror
      </div>
    </div>
  </fieldset>

  @foreach ([
    ['demandas', 'detalle_demanda_otra', '¿Qué habilidades busca tu organización?', 'Selecciona al menos una habilidad.'],
    ['brechas', 'detalle_brecha_otra', '¿Qué debería reforzarse en la formación?', 'Selecciona al menos un aspecto.'],
  ] as [$campo, $detalle, $pregunta, $mensajeMinimo])
    <fieldset class="sg-enc-seccion" data-sg-paso data-sg-grupo="{{ $campo }}" data-sg-maximo="{{ $maximo }}" data-sg-mensaje="{{ $mensajeMinimo }}" tabindex="-1" @error($campo) aria-invalid="true" @enderror aria-describedby="{{ $campo }}-ayuda @error($campo) {{ $campo }}-error @enderror">
      <legend>{{ $pregunta }}</legend>
      <p class="sg-help" id="{{ $campo }}-ayuda">Elige hasta {{ $maximo }}.</p>
      @foreach ($porCategoria as $categoria => $opciones)
        <p class="sg-enc-grupo">{{ $categoria }}</p>
        <div class="sg-enc-opciones">
          @foreach ($opciones as $h)
            <label class="sg-enc-opcion">
              <input type="checkbox" name="{{ $campo }}[]" value="{{ $h->codigo }}" @checked(in_array($h->codigo, (array) old($campo, []), true))
                     @if ($h->codigo === 'OTRA') data-sg-otra="{{ $detalle }}" @endif>
              <span>{{ $h->nombre }}</span>
            </label>
          @endforeach
        </div>
      @endforeach
      @error($campo)<p class="sg-field-error" id="{{ $campo }}-error">{{ $message }}</p>@enderror
      @error($campo.'.*')<p class="sg-field-error">{{ $message }}</p>@enderror
      <div class="sg-field sg-enc-otra" data-sg-detalle="{{ $detalle }}">
        <label class="sg-label" for="{{ $detalle }}">Describe la otra opción</label>
        <input class="sg-input" id="{{ $detalle }}" name="{{ $detalle }}" type="text" maxlength="180" value="{{ old($detalle) }}" @error($detalle) aria-invalid="true" aria-describedby="{{ $detalle }}-error" @enderror>
        @error($detalle)<p class="sg-field-error" id="{{ $detalle }}-error">{{ $message }}</p>@enderror
      </div>
      </fieldset>
  @endforeach

  @if ($esDesempeno)
    <fieldset class="sg-enc-seccion" data-sg-paso data-sg-unico="satisfaccion" data-sg-mensaje="Selecciona tu nivel de satisfacción." tabindex="-1" @error('satisfaccion') aria-invalid="true" aria-describedby="satisfaccion-error" @enderror>
      <legend>¿Qué tan satisfecho estás con su desempeño?</legend>
      <div class="sg-enc-opciones">
        @foreach ($niveles as $n)
          <label class="sg-enc-opcion">
            <input type="radio" name="satisfaccion" value="{{ $n->codigo }}" required @checked(old('satisfaccion') === $n->codigo)>
            <span>{{ $n->nombre }}</span>
          </label>
        @endforeach
      </div>
      @error('satisfaccion')<p class="sg-field-error" id="satisfaccion-error">{{ $message }}</p>@enderror
      </fieldset>
  @endif

  <fieldset class="sg-enc-seccion" data-sg-paso tabindex="-1">
    <legend>Comentario (opcional)</legend>
    <div class="sg-field">
      <label class="sg-label" for="comentario">¿Algo más que quieras contarnos?</label>
      <textarea class="sg-textarea" id="comentario" name="comentario" rows="4" maxlength="1000" @error('comentario') aria-invalid="true" aria-describedby="comentario-error" @enderror>{{ old('comentario') }}</textarea>
      @error('comentario')<p class="sg-field-error" id="comentario-error">{{ $message }}</p>@enderror
    </div>
  </fieldset>

  <div class="sg-enc-acciones">
    <button class="sg-button sg-button-secondary" type="button" data-sg-atras hidden>Atrás</button>
    <div class="sg-enc-acciones-fin">
      <button class="sg-button" type="button" data-sg-siguiente hidden>Siguiente</button>
      <button class="sg-button" type="submit" data-sg-enviar>Enviar mis respuestas</button>
    </div>
  </div>
</form>
</div>
@endsection
@push('scripts')
<script>
  (() => {
    const formulario = document.querySelector('[data-sg-encuesta]');
    const pasos = [...formulario.querySelectorAll('[data-sg-paso]')];
    const atras = formulario.querySelector('[data-sg-atras]');
    const siguiente = formulario.querySelector('[data-sg-siguiente]');
    const enviar = formulario.querySelector('[data-sg-enviar]');
    const progreso = document.querySelector('[data-sg-progreso]');
    const textoProgreso = document.querySelector('[data-sg-progreso-texto]');
    const barraProgreso = document.querySelector('[data-sg-progreso-barra]');
    let actual = Math.max(0, pasos.findIndex((paso) => paso.querySelector('.sg-field-error:not([data-sg-error-paso])')));

    const mostrar = (indice, mover = true) => {
      actual = indice;
      pasos.forEach((paso, i) => { paso.hidden = i !== actual; });
      atras.hidden = actual === 0;
      siguiente.hidden = actual === pasos.length - 1;
      enviar.hidden = actual !== pasos.length - 1;
      textoProgreso.textContent = `Pregunta ${actual + 1} de ${pasos.length}`;
      barraProgreso.style.width = `${((actual + 1) / pasos.length) * 100}%`;
      if (mover) {
        limpiarError();
        pasos[actual].focus({ preventScroll: true });
        window.scrollTo(0, 0);
      }
    };

    const alerta = document.querySelector('[data-sg-error-paso]');
    const limpiarError = () => {
      alerta.hidden = true;
      formulario.querySelectorAll('[data-sg-paso] [aria-invalid="true"]').forEach((campo) => {
        if (!campo.closest('.sg-field')?.querySelector('.sg-field-error')) campo.removeAttribute('aria-invalid');
      });
    };

    const faltante = (paso) => {
      const campo = [...paso.querySelectorAll('input[type="text"][required], select[required]')].find((c) => !c.value.trim());
      if (campo) return { mensaje: campo.dataset.sgMensaje, campo };
      if (paso.dataset.sgGrupo && !paso.querySelector('input[type="checkbox"]:checked')) return { mensaje: paso.dataset.sgMensaje };
      if (paso.dataset.sgUnico && !paso.querySelector('input[type="radio"]:checked')) return { mensaje: paso.dataset.sgMensaje };
      const otra = paso.querySelector('[data-sg-otra]:checked');
      if (otra && !formulario.elements[otra.dataset.sgOtra].value.trim()) return { mensaje: 'Describe la otra opción.', campo: formulario.elements[otra.dataset.sgOtra] };
      return null;
    };

    const validar = () => {
      const paso = pasos[actual];
      const problema = faltante(paso);
      limpiarError();
      if (!problema) return true;

      alerta.textContent = problema.mensaje;
      alerta.hidden = false;
      if (problema.campo) {
        problema.campo.setAttribute('aria-invalid', 'true');
        problema.campo.focus({ preventScroll: true });
      }
      window.scrollTo(0, 0);
      return false;
    };

    formulario.addEventListener('input', limpiarError);
    formulario.addEventListener('change', limpiarError);

    siguiente.addEventListener('click', () => { if (validar()) mostrar(actual + 1); });
    atras.addEventListener('click', () => mostrar(actual - 1));
    formulario.addEventListener('keydown', (evento) => {
      if (evento.key === 'Enter' && evento.target.matches('input, select') && actual < pasos.length - 1) {
        evento.preventDefault();
        siguiente.click();
      }
    });

    document.querySelectorAll('[data-sg-otra]').forEach((casilla) => {
      const detalle = document.querySelector(`[data-sg-detalle="${casilla.dataset.sgOtra}"]`);
      const actualizar = () => { detalle.hidden = !casilla.checked; };
      casilla.addEventListener('change', actualizar);
      actualizar();
    });

    formulario.querySelectorAll('[data-sg-grupo]').forEach((grupo) => {
      const casillas = [...grupo.querySelectorAll('input[type="checkbox"]')];
      const limitar = () => {
        const lleno = casillas.filter((c) => c.checked).length >= Number(grupo.dataset.sgMaximo);
        casillas.forEach((c) => { c.disabled = lleno && !c.checked; });
      };
      casillas.forEach((c) => c.addEventListener('change', limitar));
      limitar();
    });

    formulario.addEventListener('submit', () => {
      enviar.disabled = true;
      enviar.textContent = 'Enviando…';
    });

    progreso.hidden = false;
    mostrar(actual, false);
    document.getElementById('sg-enc-errores')?.focus();
  })();
</script>
@endpush
