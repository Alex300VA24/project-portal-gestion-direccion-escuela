{{--
  Ventanas para registrar o editar una promoción y para registrar un plan de estudios. Reutilizan los mismos
  endpoints de siempre (padron.guardar-cohorte y padron.guardar-plan). Las usan la lista de promociones y la página de cada una.
  Variables: $planes (id, codigo_version, estado).
--}}
<style>
  .sg-form-dialogo { text-align: left; }
  .sg-form-dialogo .sg-dialogo-cuerpo { text-align: left; padding: 24px 24px 8px; }
  .sg-form-dialogo .sg-dialogo-titulo { font-size: 1.15rem; }
  .sg-form-dialogo form.sg-campos { display: grid; gap: 14px; margin-top: 14px; }
  .sg-form-dialogo .sg-dialogo-pie { gap: 10px; padding: 8px 24px 22px; justify-content: flex-end; }
  .sg-form-dialogo .sg-fila-plan { display: flex; gap: 8px; align-items: center; }
  .sg-form-dialogo .sg-fila-plan select { flex: 1; }
  .sg-form-dialogo .sg-error-formulario { color: var(--sg-danger, #c0362c); font-size: 13px; margin: 0; }
</style>

<dialog class="sg-dialogo sg-form-dialogo" id="dlg-promocion" style="width: min(94vw, 460px);" aria-labelledby="dlg-promocion-titulo">
  <form class="sg-campos-envoltorio" onsubmit="return false;">
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-promocion-titulo">Nueva promoción</h2>
      <p class="sg-dialogo-mensaje" style="white-space: normal;">Una promoción es el grupo de alumnos que ingresó el mismo año.</p>
      <div class="sg-campos" style="display: grid; gap: 14px; margin-top: 14px;">
        <div class="sg-field">
          <label class="sg-label" for="pr-anio">Año de ingreso *</label>
          <input class="sg-input" id="pr-anio" type="number" min="2000" max="2035" placeholder="Ej. 2022" required>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="pr-total">Total de ingresantes *</label>
          <input class="sg-input" id="pr-total" type="number" min="1" max="1000" placeholder="Ej. 50" required>
          <p class="sg-help">Con este total se calcula qué porcentaje de la promoción llega a egresar.</p>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="pr-plan">Plan de estudios *</label>
          <div class="sg-fila-plan">
            <select class="sg-select" id="pr-plan" required>
              @foreach($planes as $plan)
                <option value="{{ $plan->id }}">{{ $plan->codigo_version }}</option>
              @endforeach
            </select>
            <button type="button" class="sg-button sg-button-secondary" onclick="abrirNuevoPlan()">+ Otro plan</button>
          </div>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="pr-obs">Observaciones (opcional)</label>
          <textarea class="sg-textarea" id="pr-obs" rows="2" maxlength="500" placeholder="Cualquier nota útil sobre esta promoción."></textarea>
        </div>
        <p class="sg-error-formulario" id="pr-error" role="alert" hidden></p>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-promocion').close()">Cancelar</button>
      <button type="submit" id="pr-guardar" onclick="guardarPromocion()">Guardar</button>
    </div>
  </form>
</dialog>

<dialog class="sg-dialogo sg-form-dialogo" id="dlg-plan" style="width: min(94vw, 420px);" aria-labelledby="dlg-plan-titulo">
  <form onsubmit="return false;">
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-plan-titulo">Nuevo plan de estudios</h2>
      <div class="sg-campos" style="display: grid; gap: 14px; margin-top: 14px;">
        <div class="sg-field">
          <label class="sg-label" for="pl-codigo">Nombre del plan *</label>
          <input class="sg-input" id="pl-codigo" type="text" maxlength="35" placeholder="Ej. Plan 2027" required>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="pl-desde">Vigente desde *</label>
          <input class="sg-input" id="pl-desde" type="date" required>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="pl-estado">Estado *</label>
          <select class="sg-select" id="pl-estado">
            <option value="VIGENTE">Vigente</option>
            <option value="BORRADOR">En preparación</option>
            <option value="CERRADO">Cerrado</option>
          </select>
        </div>
        <p class="sg-error-formulario" id="pl-error" role="alert" hidden></p>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-plan').close()">Cancelar</button>
      <button type="submit" onclick="guardarPlan()">Guardar</button>
    </div>
  </form>
</dialog>

<script>
  (() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const enviar = async (url, datos) => {
      const respuesta = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(datos),
      });
      const json = await respuesta.json().catch(() => ({}));
      if (!respuesta.ok) {
        const detalle = json.errors ? Object.values(json.errors).flat().join(' ') : '';
        throw new Error(detalle || json.message || 'No se pudo guardar. Intenta de nuevo.');
      }
      return json;
    };
    const mostrarError = (id, mensaje) => { const el = document.getElementById(id); el.textContent = mensaje; el.hidden = !mensaje; };
    const recargarConAviso = (mensaje, destino) => {
      try { sessionStorage.setItem('sgAviso', mensaje); } catch (_) {}
      if (destino) { window.location.href = destino; } else { window.location.reload(); }
    };

    let editando = null;

    // Nueva promoción: al guardarla se abre su página, en el paso de cargar alumnos.
    window.abrirNuevaPromocion = () => {
      editando = null;
      document.getElementById('dlg-promocion-titulo').textContent = 'Nueva promoción';
      document.getElementById('pr-anio').value = '';
      document.getElementById('pr-anio').readOnly = false;
      document.getElementById('pr-total').value = '';
      document.getElementById('pr-obs').value = '';
      mostrarError('pr-error', '');
      document.getElementById('dlg-promocion').showModal();
      document.getElementById('pr-anio').focus();
    };

    window.abrirEditarPromocion = (datos) => {
      editando = datos;
      document.getElementById('dlg-promocion-titulo').textContent = 'Editar la promoción ' + datos.anio;
      document.getElementById('pr-anio').value = datos.anio;
      document.getElementById('pr-anio').readOnly = true;
      document.getElementById('pr-total').value = datos.total;
      document.getElementById('pr-plan').value = datos.plan;
      document.getElementById('pr-obs').value = datos.obs || '';
      mostrarError('pr-error', '');
      document.getElementById('dlg-promocion').showModal();
    };

    window.guardarPromocion = async () => {
      const anio = document.getElementById('pr-anio').value;
      const total = document.getElementById('pr-total').value;
      const plan = document.getElementById('pr-plan').value;
      if (!anio || !total || !plan) { mostrarError('pr-error', 'Completa el año, el total de ingresantes y el plan de estudios.'); return; }
      try {
        const json = await enviar('{{ route('seguimiento-egresado.padron.guardar-cohorte') }}', {
          id: editando ? editando.id : null, anio_ingreso: Number(anio), total_ingresantes: Number(total),
          id_plan_curricular: plan, observacion: document.getElementById('pr-obs').value || null,
        });
        const destino = editando ? null : '{{ url('seguimiento-egresado/padron/promocion') }}/' + json.cohorte.id + '#paso-2';
        recargarConAviso(json.message, destino);
      } catch (e) {
        mostrarError('pr-error', e.message);
      }
    };

    window.abrirNuevoPlan = () => {
      document.getElementById('pl-codigo').value = '';
      document.getElementById('pl-desde').value = '';
      document.getElementById('pl-estado').value = 'VIGENTE';
      mostrarError('pl-error', '');
      document.getElementById('dlg-plan').showModal();
      document.getElementById('pl-codigo').focus();
    };

    window.guardarPlan = async () => {
      const codigo = document.getElementById('pl-codigo').value.trim();
      const desde = document.getElementById('pl-desde').value;
      if (!codigo || !desde) { mostrarError('pl-error', 'Escribe el nombre del plan y desde cuándo está vigente.'); return; }
      try {
        const json = await enviar('{{ route('seguimiento-egresado.padron.guardar-plan') }}', {
          codigo_version: codigo, vigencia_desde: desde, estado: document.getElementById('pl-estado').value,
        });
        // El plan nuevo se suma a la lista y queda elegido, sin cerrar el formulario de la promoción.
        const lista = document.getElementById('pr-plan');
        const opcion = new Option(json.plan.codigo_version, json.plan.id, true, true);
        lista.add(opcion);
        document.getElementById('dlg-plan').close();
        if (window.sgDialogo) window.sgDialogo({ tipo: 'success', mensaje: json.message });
      } catch (e) {
        mostrarError('pl-error', e.message);
      }
    };
  })();
</script>
