{{--
  Ventana para crear o editar una campaña. La campaña se abre y se cierra sola según sus fechas, así que no se elige su estado.
  Se reutiliza desde cualquier pantalla (por ejemplo, Encuesta empresarial) para no mandar a la Secretaría a otra página.
  Variables: $promociones (id, anio, egresados), $periodos. Opcional: $tipoFijo ('EMPRESAS' o 'EGRESADOS') para no preguntar para quién es.
  JavaScript: abrirNuevaCampania(tipo?) y abrirEditarCampania(datos).
--}}
@php $tipoFijo = $tipoFijo ?? null; @endphp
<style>
  .sg-form-dialogo .sg-dialogo-cuerpo { text-align: left; padding: 24px 24px 8px; }
  .sg-form-dialogo .sg-dialogo-pie { gap: 10px; padding: 8px 24px 22px; justify-content: flex-end; }
  .sg-form-dialogo .sg-campos { display: grid; gap: 14px; margin-top: 14px; }
  .sg-form-dialogo .sg-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .sg-form-dialogo .sg-casillas { display: grid; gap: 6px; max-height: 170px; overflow: auto; padding: 4px 2px; }
  .sg-form-dialogo .sg-casillas label { display: flex; gap: 8px; align-items: center; font-size: 14px; }
  .sg-form-dialogo .sg-casillas label.apagada { opacity: .55; }
  @media (max-width: 520px) { .sg-form-dialogo .sg-dos { grid-template-columns: 1fr; } }
</style>
<dialog class="sg-dialogo sg-form-dialogo" id="dlg-campania" style="width: min(94vw, 520px);" aria-labelledby="dlg-campania-titulo">
  <form method="POST" action="{{ route('seguimiento-egresado.campanias.store') }}" id="form-campania">
    @csrf
    <input type="hidden" name="_method" value="POST" id="camp-metodo">
    <div class="sg-dialogo-cuerpo">
      <h2 class="sg-dialogo-titulo" id="dlg-campania-titulo">Nueva campaña</h2>
      <p class="sg-dialogo-mensaje" style="white-space: normal;">La campaña se abre y se cierra sola en las fechas que elijas.</p>
      <div class="sg-campos">
        <div class="sg-field">
          <label class="sg-label" for="camp-nombre">Nombre *</label>
          <input class="sg-input" id="camp-nombre" name="nombre" type="text" maxlength="160" required placeholder="Ej. Seguimiento a egresados 2026-II">
        </div>

        <div class="sg-field" id="camp-bloque-tipo" @if($tipoFijo) hidden @endif>
          <label class="sg-label" for="camp-tipo">¿Para quién es? *</label>
          <select class="sg-select" id="camp-tipo" name="tipo" required>
            <option value="EGRESADOS" @selected($tipoFijo === 'EGRESADOS')>Egresados</option>
            <option value="EMPRESAS" @selected($tipoFijo === 'EMPRESAS')>Empleadores (empresas)</option>
          </select>
        </div>
        <p class="sg-help" id="camp-tipo-fijo" hidden style="margin: 0;"></p>

        <div class="sg-dos">
          <div class="sg-field">
            <label class="sg-label" for="camp-periodo">Periodo académico *</label>
            <select class="sg-select" id="camp-periodo" name="periodo" required>
              @foreach($periodos as $periodo)
                <option value="{{ $periodo }}" @selected($periodo === now()->year.'-'.(now()->month <= 7 ? 'I' : 'II'))>{{ $periodo }}</option>
              @endforeach
            </select>
          </div>
          <div class="sg-field">
            <label class="sg-label" for="camp-horizonte">Años desde el egreso</label>
            <select class="sg-select" id="camp-horizonte" name="horizonte_anios">
              <option value="">Sin año específico</option>
              <option value="1">1 año</option>
              <option value="3">3 años</option>
              <option value="5">5 años</option>
              <option value="10">10 años</option>
            </select>
          </div>
        </div>

        <div class="sg-dos">
          <div class="sg-field">
            <label class="sg-label" for="camp-inicio">Se abre el *</label>
            <input class="sg-input" id="camp-inicio" name="fecha_inicio" type="date" required>
          </div>
          <div class="sg-field">
            <label class="sg-label" for="camp-fin">Se cierra el *</label>
            <input class="sg-input" id="camp-fin" name="fecha_fin" type="date" required>
          </div>
        </div>

        <div class="sg-field">
          <span class="sg-label">Promociones que participan *</span>
          <div class="sg-casillas" id="camp-promociones">
            @foreach($promociones as $promocion)
              <label class="{{ $promocion['egresados'] === 0 ? 'apagada' : '' }}">
                <input type="checkbox" name="cohortes[]" value="{{ $promocion['id'] }}" data-egresados="{{ $promocion['egresados'] }}">
                <span>Promoción {{ $promocion['anio'] }} · {{ $promocion['egresados'] === 0 ? 'sin egresados confirmados' : $promocion['egresados'].($promocion['egresados'] === 1 ? ' egresado' : ' egresados') }}</span>
              </label>
            @endforeach
          </div>
          <p class="sg-help">Las invitaciones se envían al correo personal de cada egresado confirmado.</p>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-descripcion">Notas (opcional)</label>
          <textarea class="sg-textarea" id="camp-descripcion" name="descripcion" rows="2" maxlength="1000"></textarea>
        </div>
      </div>
    </div>
    <div class="sg-dialogo-pie">
      <button type="button" class="sg-dialogo-secundario" onclick="document.getElementById('dlg-campania').close()">Cancelar</button>
      <button type="submit" id="camp-guardar">Crear campaña</button>
    </div>
  </form>
</dialog>

<script>
  (() => {
    const dlg = document.getElementById('dlg-campania');
    const hoy = () => new Date().toISOString().slice(0, 10);
    const casillas = () => [...document.querySelectorAll('#camp-promociones input[type=checkbox]')];
    const marcar = (ids) => casillas().forEach((c) => {
      c.checked = ids.includes(Number(c.value));
      // Sin egresados confirmados no hay a quién invitar, salvo que la promoción ya estuviera elegida.
      c.disabled = Number(c.dataset.egresados) === 0 && !c.checked;
    });
    const poner = (id, valor) => { document.getElementById(id).value = valor ?? ''; };

    window.abrirNuevaCampania = (tipo = null) => {
      document.getElementById('form-campania').action = '{{ route('seguimiento-egresado.campanias.store') }}';
      poner('camp-metodo', 'POST');
      document.getElementById('dlg-campania-titulo').textContent = tipo === 'EMPRESAS' ? 'Nueva campaña para empleadores' : 'Nueva campaña';
      document.getElementById('camp-guardar').textContent = 'Crear campaña';
      poner('camp-nombre', ''); poner('camp-horizonte', ''); poner('camp-descripcion', '');
      poner('camp-inicio', hoy()); poner('camp-fin', '');
      document.getElementById('camp-fin').min = hoy();
      if (tipo) { poner('camp-tipo', tipo); }
      document.getElementById('camp-bloque-tipo').hidden = tipo !== null || {{ $tipoFijo ? 'true' : 'false' }};
      document.getElementById('camp-tipo').disabled = false;
      document.getElementById('camp-tipo-fijo').hidden = true;
      marcar([]);
      dlg.showModal();
      document.getElementById('camp-nombre').focus();
    };

    // Editar: el «para quién» no se cambia (cambiaría las invitaciones ya enviadas).
    window.abrirEditarCampania = (d) => {
      document.getElementById('form-campania').action = d.accion;
      poner('camp-metodo', 'PUT');
      document.getElementById('dlg-campania-titulo').textContent = 'Editar campaña';
      document.getElementById('camp-guardar').textContent = 'Guardar cambios';
      poner('camp-nombre', d.nombre); poner('camp-periodo', d.periodo); poner('camp-horizonte', d.horizonte);
      poner('camp-inicio', d.inicio); poner('camp-fin', d.fin); poner('camp-descripcion', d.descripcion);
      document.getElementById('camp-fin').removeAttribute('min');
      document.getElementById('camp-bloque-tipo').hidden = true;
      document.getElementById('camp-tipo').disabled = true;
      const nota = document.getElementById('camp-tipo-fijo');
      nota.textContent = 'Campaña para ' + (d.tipo === 'EMPRESAS' ? 'empleadores.' : 'egresados.'); nota.hidden = false;
      marcar(d.promociones);
      document.getElementById('dlg-detalle')?.close();
      dlg.showModal();
    };
  })();
</script>
