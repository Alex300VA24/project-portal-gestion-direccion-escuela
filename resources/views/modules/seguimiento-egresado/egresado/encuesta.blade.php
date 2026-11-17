@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', 'Encuesta de seguimiento')

@section('content')
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
  $trabaja = old('situacion') === 'TRABAJA';
  $formaciones = old('formaciones', []);
  $contactoActual = fn (string $campo) => old("contacto.$campo", $contacto[$campo] ?? '');
@endphp
<div>
  <span class="sg-kicker">Mi seguimiento</span>
  <h1 class="sg-heading">Encuesta de seguimiento a egresados</h1>
  <p class="sgs-intro">Campaña <strong>{{ $invitacion->campania }}</strong> ({{ $invitacion->periodo }}). Puedes responder hasta el {{ $fecha($invitacion->expira_en) }}. Los campos con * son obligatorios y tu respuesta no se podrá modificar después de enviarla.</p>

  @include('modules.seguimiento-egresado.egresado._resumen-errores', ['titulo' => 'Revisa los datos marcados antes de enviar:'])

  <form method="post" action="{{ route('seguimiento-egresado.egresado.encuesta.responder') }}" id="form-encuesta" novalidate>
    @csrf
    <input type="hidden" name="invitacion" value="{{ $invitacion->id }}">

    <section class="sg-panel sgs-step" aria-labelledby="s1">
      @if ($contacto)
        <h2 class="sg-section-title" id="s1">1. Confirma tus datos de contacto</h2>
        <p class="sgs-muted">Son los datos que dejaste antes. Si cambiaron, actualízalos aquí.</p>
      @else
        <h2 class="sg-section-title" id="s1">1. Tus datos de contacto</h2>
        <p class="sgs-muted">Aún no tenemos tus datos: complétalos para que podamos escribirte.</p>
      @endif
      <div class="sg-form-grid">
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'c-correo', 'nombre' => 'contacto[correo_personal]', 'etiqueta' => 'Correo personal', 'tipo' => 'email', 'requerido' => true, 'valor' => $contactoActual('correo_personal'), 'attrs' => ['maxlength' => 254, 'autocomplete' => 'email', 'inputmode' => 'email', 'data-valida' => 'correo']])
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'c-cel', 'nombre' => 'contacto[celular_whatsapp]', 'etiqueta' => 'Celular / WhatsApp', 'tipo' => 'tel', 'requerido' => true, 'valor' => $contactoActual('celular_whatsapp'), 'attrs' => ['maxlength' => 20, 'autocomplete' => 'tel', 'inputmode' => 'tel', 'placeholder' => '+51 900 000 000', 'data-valida' => 'celular']])
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'c-fijo', 'nombre' => 'contacto[telefono_fijo]', 'etiqueta' => 'Teléfono fijo (opcional)', 'tipo' => 'tel', 'valor' => $contactoActual('telefono_fijo'), 'attrs' => ['maxlength' => 20, 'inputmode' => 'tel', 'placeholder' => '044 000000', 'data-valida' => 'telefono']])
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'c-dir', 'nombre' => 'contacto[direccion]', 'etiqueta' => 'Dirección (opcional)', 'valor' => $contactoActual('direccion'), 'attrs' => ['minlength' => 5, 'maxlength' => 250, 'autocomplete' => 'street-address']])
      </div>
    </section>

    <section class="sg-panel sgs-step" aria-labelledby="s2">
      <h2 class="sg-section-title" id="s2">2. Perfil profesional</h2>
      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'nivel_ingles', 'etiqueta' => 'Nivel de inglés', 'requerido' => true, 'opciones' => $cat['niveles_ingles'], 'seleccion' => old('nivel_ingles')])
      <div class="sg-form-grid">
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'otros_idiomas', 'nombre' => 'otros_idiomas', 'etiqueta' => 'Otros idiomas (opcional)', 'valor' => old('otros_idiomas'), 'attrs' => ['minlength' => 2, 'maxlength' => 160, 'placeholder' => 'Ejemplo: portugués intermedio']])
        @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'area_profesional', 'nombre' => 'area_profesional', 'etiqueta' => 'Área profesional principal', 'requerido' => true, 'opciones' => $cat['areas'], 'seleccion' => old('area_profesional')])
      </div>
    </section>

    <section class="sg-panel sgs-step" aria-labelledby="s3">
      <h2 class="sg-section-title" id="s3">3. Situación laboral</h2>
      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'situacion', 'etiqueta' => '¿Te encuentras laborando actualmente?', 'requerido' => true, 'opciones' => $cat['situaciones'], 'seleccion' => old('situacion')])

      <div class="sgs-cond" id="grp-trabajo" @if (! $trabaja) hidden @endif>
        @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'trabajo_relacionado', 'etiqueta' => '¿Tu trabajo está relacionado con Ingeniería Informática?', 'requerido' => true, 'opciones' => ['1' => 'Sí', '0' => 'No'], 'seleccion' => old('trabajo_relacionado')])
        <div class="sg-form-grid">
          @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'modalidad_laboral', 'nombre' => 'modalidad_laboral', 'etiqueta' => 'Tipo de vínculo', 'requerido' => true, 'opciones' => $cat['vinculos'], 'seleccion' => old('modalidad_laboral')])
          @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'modalidad_trabajo', 'nombre' => 'modalidad_trabajo', 'etiqueta' => 'Modalidad de trabajo', 'requerido' => true, 'opciones' => $cat['modalidades'], 'seleccion' => old('modalidad_trabajo')])
          @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'sector_laboral', 'nombre' => 'sector_laboral', 'etiqueta' => 'Sector de la organización', 'requerido' => true, 'opciones' => $cat['sectores'], 'seleccion' => old('sector_laboral')])
          @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'nombre_empleador_cliente', 'nombre' => 'nombre_empleador_cliente', 'etiqueta' => 'Empleador o cliente', 'requerido' => true, 'valor' => old('nombre_empleador_cliente'), 'attrs' => ['minlength' => 2, 'maxlength' => 160, 'autocomplete' => 'off']])
          @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'pais_empleador', 'nombre' => 'pais_empleador', 'etiqueta' => 'País del empleo', 'requerido' => true, 'opciones' => $cat['paises'], 'seleccion' => old('pais_empleador')])
          @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'region_empleo', 'nombre' => 'region_empleo', 'etiqueta' => 'Ubicación del empleo en el Perú', 'requerido' => true, 'opciones' => $cat['regiones'], 'seleccion' => old('region_empleo'), 'contenedorId' => 'grp-region', 'oculto' => ! ($trabaja && old('pais_empleador') === 'PE')])
        </div>
      </div>

      <div class="sg-form-grid">
        @include('modules.seguimiento-egresado.egresado._lista', ['id' => 'pais_residencia', 'nombre' => 'pais_residencia', 'etiqueta' => 'País de residencia', 'requerido' => true, 'opciones' => $cat['paises'], 'seleccion' => old('pais_residencia')])
        @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'ciudad_residencia', 'nombre' => 'ciudad_residencia', 'etiqueta' => 'Ciudad de residencia (opcional)', 'valor' => old('ciudad_residencia'), 'attrs' => ['minlength' => 2, 'maxlength' => 120, 'autocomplete' => 'off']])
      </div>
    </section>

    <section class="sg-panel sgs-step" aria-labelledby="s4">
      <h2 class="sg-section-title" id="s4">4. Desarrollo profesional continuo</h2>
      <p class="sgs-muted">Posgrados, certificaciones o cursos de especialización. Puedes agregar hasta {{ \App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest::MAX_FORMACIONES }}; si no tienes, déjalo vacío.</p>
      @if ($errors->has('formaciones'))<p class="sg-field-error" role="alert">{{ $errors->first('formaciones') }}</p>@endif
      <div id="lista-formaciones">
        @foreach ($formaciones as $i => $f)
          @include('modules.seguimiento-egresado.egresado._formacion', ['i' => $i, 'f' => $f, 'cat' => $cat])
        @endforeach
      </div>
      <button type="button" class="sg-button sg-button-secondary" id="agregar-formacion">Agregar formación</button>
    </section>

    <section class="sg-panel sgs-step" aria-labelledby="s5">
      <h2 class="sg-section-title" id="s5">5. Satisfacción y habilidades</h2>
      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'satisfaccion', 'etiqueta' => '¿Qué tan satisfecho estás con la formación profesional recibida en la Escuela?', 'requerido' => true, 'opciones' => $cat['satisfaccion'], 'seleccion' => old('satisfaccion'), 'clase' => 'sgs-scale'])

      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'brechas', 'etiqueta' => '¿En qué habilidades consideras que la formación fue insuficiente?', 'tipo' => 'checkbox', 'opciones' => $cat['habilidades'], 'seleccion' => old('brechas', []), 'clase' => 'sgs-checks'])
      @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'detalle_brecha_otra', 'nombre' => 'detalle_brecha_otra', 'etiqueta' => 'Indica cuál otra habilidad', 'requerido' => true, 'valor' => old('detalle_brecha_otra'), 'attrs' => ['minlength' => 2, 'maxlength' => 180, 'autocomplete' => 'off'], 'contenedorId' => 'grp-brecha-otra', 'oculto' => ! in_array('OTRA', (array) old('brechas', []), true)])

      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'fortalezas', 'etiqueta' => '¿Cuáles consideras tus fortalezas?', 'tipo' => 'checkbox', 'opciones' => $cat['habilidades'], 'seleccion' => old('fortalezas', []), 'clase' => 'sgs-checks'])
      @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'detalle_fortaleza_otra', 'nombre' => 'detalle_fortaleza_otra', 'etiqueta' => 'Indica cuál otra fortaleza', 'requerido' => true, 'valor' => old('detalle_fortaleza_otra'), 'attrs' => ['minlength' => 2, 'maxlength' => 180, 'autocomplete' => 'off'], 'contenedorId' => 'grp-fortaleza-otra', 'oculto' => ! in_array('OTRA', (array) old('fortalezas', []), true)])

      <div class="sg-field">
        <label class="sg-label" for="comentario">Comentario (opcional)</label>
        <textarea class="sg-textarea" id="comentario" name="comentario" maxlength="1000" @if ($errors->has('comentario')) aria-invalid="true" aria-describedby="comentario-error" @endif>{{ old('comentario') }}</textarea>
        <p class="sg-field-error" id="comentario-error" @if ($errors->has('comentario')) role="alert" @endif>{{ $errors->first('comentario') }}</p>
      </div>
    </section>

    <section class="sg-panel sgs-step" id="grp-jefe-panel" aria-labelledby="s6" @if (! $trabaja) hidden @endif>
      <h2 class="sg-section-title" id="s6">6. Contacto del empleador</h2>
      @include('modules.seguimiento-egresado.egresado._grupo', ['nombre' => 'autoriza_contacto_empresa', 'etiqueta' => '¿Autorizas a la Escuela a contactar a tu empresa para evaluar tu desempeño laboral?', 'requerido' => true, 'opciones' => ['1' => 'Sí, autorizo', '0' => 'No autorizo'], 'seleccion' => old('autoriza_contacto_empresa')])
      @include('modules.seguimiento-egresado.egresado._campo', ['id' => 'contacto_jefe', 'nombre' => 'contacto_jefe', 'etiqueta' => 'Correo o celular de tu jefe directo', 'requerido' => true, 'valor' => old('contacto_jefe'), 'attrs' => ['maxlength' => 180, 'autocomplete' => 'off', 'data-valida' => 'jefe'], 'contenedorId' => 'grp-jefe', 'oculto' => ! ($trabaja && old('autoriza_contacto_empresa') === '1')])
    </section>

    <div class="sgs-navbtns">
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.egresado.inicio') }}">Cancelar</a>
      <button class="sg-button" type="submit">Enviar mi respuesta</button>
    </div>
  </form>
</div>

<template id="tpl-formacion">@include('modules.seguimiento-egresado.egresado._formacion', ['i' => '__i__', 'f' => [], 'cat' => $cat])</template>
@endsection

@push('scripts')
<script>
(() => {
  const f = document.getElementById('form-encuesta');
  const valor = (n) => (f.querySelector(`input[name="${n}"]:checked`) || f.querySelector(`select[name="${n}"]`) || {}).value;
  const mostrar = (id, si) => { const el = document.getElementById(id); if (el) el.hidden = !si; };
  const lista = document.getElementById('lista-formaciones');
  let siguiente = lista.querySelectorAll('[data-formacion]').length + 1000;

  function refrescar() {
    const trabaja = valor('situacion') === 'TRABAJA';
    mostrar('grp-trabajo', trabaja);
    mostrar('grp-jefe-panel', trabaja);
    mostrar('grp-region', trabaja && valor('pais_empleador') === 'PE');
    mostrar('grp-jefe', trabaja && valor('autoriza_contacto_empresa') === '1');
    mostrar('grp-brecha-otra', !!f.querySelector('input[name="brechas[]"][value="OTRA"]:checked'));
    mostrar('grp-fortaleza-otra', !!f.querySelector('input[name="fortalezas[]"][value="OTRA"]:checked'));
    lista.querySelectorAll('[data-formacion]').forEach((bloque) => {
      const enCurso = bloque.querySelector('[data-estado]').value === 'EN_CURSO';
      bloque.querySelector('[data-fin]').hidden = enCurso;
    });
  }

  document.getElementById('agregar-formacion').addEventListener('click', () => {
    if (lista.querySelectorAll('[data-formacion]').length >= {{ \App\Modules\SeguimientoEgresado\Requests\EncuestaSeguimientoRequest::MAX_FORMACIONES }}) return;
    const html = document.getElementById('tpl-formacion').innerHTML.replaceAll('__i__', String(siguiente++));
    lista.insertAdjacentHTML('beforeend', html);
    refrescar();
  });
  lista.addEventListener('click', (e) => {
    const boton = e.target.closest('[data-quitar-formacion]');
    if (boton) boton.closest('[data-formacion]').remove();
  });
  f.addEventListener('change', refrescar);
  refrescar();

  // Con errores, el foco va al resumen para que se lean al recargar la página.
  const resumen = document.getElementById('resumen-errores');
  if (resumen) resumen.focus();
})();
// Validación en vivo: solo avisa antes de enviar. Las mismas reglas las aplica y decide el servidor.
(() => {
  const f = document.getElementById('form-encuesta');
  const digitos = (v) => v.replace(/\D/g, '').length;
  const esCelular = (v) => /^\+?[0-9 ]+$/.test(v) && digitos(v) >= 9 && digitos(v) <= 15;
  const esCorreo = (v) => /^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/.test(v);
  const reglas = {
    correo: [esCorreo, 'Ingresa un correo personal con formato válido (ejemplo: nombre@correo.com).'],
    celular: [esCelular, 'Ingresa un celular o WhatsApp válido: de 9 a 15 dígitos, con + opcional al inicio (ejemplo: +51 900 000 000).'],
    telefono: [(v) => /^[0-9 ()+\-]+$/.test(v) && digitos(v) >= 6 && digitos(v) <= 15, 'Ingresa un teléfono fijo válido: de 6 a 15 dígitos (ejemplo: 044 000000).'],
    jefe: [(v) => esCorreo(v) || esCelular(v), 'Ingresa un correo o un celular válido para el contacto del jefe directo.'],
  };
  const oculto = (el) => !!el.closest('[hidden]');

  const marcar = (el, mensaje, aviso) => {
    if (aviso) { aviso.textContent = mensaje; mensaje ? aviso.setAttribute('role', 'alert') : aviso.removeAttribute('role'); }
    if (mensaje) { el.setAttribute('aria-invalid', 'true'); if (aviso?.id) el.setAttribute('aria-describedby', aviso.id); }
    else { el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby'); }
  };

  // Campos de texto y listas: un campo vacío solo se señala al enviar; uno escrito se revisa al salir de él.
  const validarCampo = (campo, alEnviar = false) => {
    if (oculto(campo)) return true;
    const aviso = document.getElementById(`${campo.id}-error`);
    const valor = campo.value.trim();
    if (valor === '') {
      if (alEnviar && campo.getAttribute('aria-required') === 'true') { marcar(campo, 'Este campo es obligatorio.', aviso); return false; }
      marcar(campo, '', aviso);
      return true;
    }
    const minimo = parseInt(campo.getAttribute('minlength') || '0', 10);
    if (minimo && valor.length < minimo) { marcar(campo, `Debe tener al menos ${minimo} caracteres.`, aviso); return false; }
    const regla = reglas[campo.dataset.valida];
    if (regla && !regla[0](valor)) { marcar(campo, regla[1], aviso); return false; }
    marcar(campo, '', aviso);
    return true;
  };

  // Preguntas de opción (sí/no, situación, nivel…): se exige una marcada si la pregunta es obligatoria y está a la vista.
  const validarGrupo = (grupo) => {
    const obligatoria = grupo.querySelector('legend.sgs-req');
    const aviso = grupo.querySelector('.sg-field-error');
    if (!obligatoria || oculto(grupo)) return true;
    const respondida = !!grupo.querySelector('input:checked');
    grupo.classList.toggle('sgs-invalid', !respondida);
    marcar(grupo, respondida ? '' : 'Selecciona una opción.', aviso);
    return respondida;
  };

  f.addEventListener('focusout', (e) => {
    if (e.target.matches('input.sg-input, textarea.sg-textarea, select.sg-select')) validarCampo(e.target);
  });
  f.addEventListener('input', (e) => {
    if (e.target.getAttribute('aria-invalid') === 'true') validarCampo(e.target);
  });
  f.addEventListener('change', (e) => {
    const grupo = e.target.closest('fieldset.sg-field');
    if (grupo) validarGrupo(grupo);
    else if (e.target.matches('select.sg-select')) validarCampo(e.target, true);
  });

  f.addEventListener('submit', (e) => {
    const invalidos = [];
    f.querySelectorAll('fieldset.sg-field').forEach((g) => { if (!validarGrupo(g)) invalidos.push(g.querySelector('input')); });
    f.querySelectorAll('input.sg-input, select.sg-select, textarea.sg-textarea').forEach((c) => { if (!validarCampo(c, true)) invalidos.push(c); });
    // El foco va al primero que se ve en la página, sea un campo o una pregunta de opción.
    const primero = invalidos.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0];
    if (primero) {
      e.preventDefault();
      primero.scrollIntoView({ block: 'center', behavior: 'smooth' });
      primero.focus({ preventScroll: true });
    }
  });
})();
</script>
@endpush
