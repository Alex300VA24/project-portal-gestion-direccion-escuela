{{--
  Ventana «Elegir de los estudiantes del portal»: segunda forma, opcional, de cargar alumnos a una promoción.
  La lista se pide al abrirla (no se carga con la página). Variable: $promocion (Cohorte).
--}}
<style>
  #dlg-estudiantes { width: min(94vw, 780px); }
  #dlg-estudiantes .sg-dialogo-cuerpo { text-align: left; padding: 22px 24px 8px; }
  #dlg-estudiantes .sg-dialogo-pie { gap: 10px; padding: 12px 24px 20px; justify-content: flex-end; align-items: center; }
  #dlg-estudiantes h2 { margin: 0 0 4px; font-size: 1.125rem; }
  #dlg-estudiantes .sg-est-ayuda { margin: 0 0 12px; color: var(--sg-muted); font-size: 13px; line-height: 1.45; }
  #dlg-estudiantes .sg-est-buscar { margin-bottom: 10px; }
  #dlg-estudiantes .sg-table-wrap { max-height: 46vh; overflow: auto; }
  #dlg-estudiantes .sg-table th, #dlg-estudiantes .sg-table td { padding: 8px 10px; font-size: 13px; }
  #dlg-estudiantes .sg-table td.sg-est-sin { color: var(--sg-warning); }
  #dlg-estudiantes .sg-est-estado { margin: 10px 0 0; color: var(--sg-muted); font-size: 13px; min-height: 18px; }
  #dlg-estudiantes .sg-est-contador { margin-right: auto; color: var(--sg-muted); font-size: 13px; }
  #dlg-estudiantes .sg-dialogo-pie button:disabled:not(.sg-ocupado) { opacity: .5; cursor: not-allowed; filter: none; }
</style>
<dialog class="sg-dialogo sg-form-dialogo" id="dlg-estudiantes" aria-labelledby="dlg-est-titulo">
  <form method="POST" data-sg-cargando="Agregando alumnos…" action="{{ route('seguimiento-egresado.padron.importar-estudiantes') }}" id="form-estudiantes">
    @csrf
    <input type="hidden" name="promocion" value="{{ $promocion->id }}">
    <div class="sg-dialogo-cuerpo">
      <h2 id="dlg-est-titulo">Elegir de los estudiantes del portal</h2>
      <p class="sg-est-ayuda">Estudiantes que ingresaron en {{ $promocion->anio_ingreso }} y todavía no están en el padrón. Los que marques se agregan a la Promoción {{ $promocion->anio_ingreso }} como aptos para egresar, y a su correo se enviará después el enlace de contacto.</p>
      <div class="sg-field sg-est-buscar">
        <label class="sg-label" for="est-buscar">Buscar</label>
        <input class="sg-input" id="est-buscar" type="search" placeholder="Matrícula, documento o nombre" autocomplete="off">
      </div>
      <div class="sg-table-wrap">
        <table class="sg-table">
          <thead>
            <tr>
              <th scope="col"><input type="checkbox" id="est-todos" aria-label="Marcar todos los estudiantes que se pueden agregar"></th>
              <th scope="col">Matrícula</th>
              <th scope="col">Estudiante</th>
              <th scope="col">Documento</th>
              <th scope="col">Correo</th>
            </tr>
          </thead>
          <tbody id="est-filas"></tbody>
        </table>
      </div>
      <p class="sg-est-estado" id="est-estado" role="status"></p>
    </div>
    <div class="sg-dialogo-pie">
      <span class="sg-est-contador" id="est-contador">0 marcados</span>
      <button type="button" class="sg-dialogo-secundario" id="est-cancelar">Cancelar</button>
      <button type="submit" id="est-agregar" disabled>Agregar a la promoción</button>
    </div>
  </form>
</dialog>
<script>
  (() => {
    const dlg = document.getElementById('dlg-estudiantes');
    const abrir = document.getElementById('btn-elegir-estudiantes');
    if (!dlg || !abrir) return;
    const filas = document.getElementById('est-filas');
    const estado = document.getElementById('est-estado');
    const buscar = document.getElementById('est-buscar');
    const todos = document.getElementById('est-todos');
    const contador = document.getElementById('est-contador');
    const agregar = document.getElementById('est-agregar');
    const form = document.getElementById('form-estudiantes');
    const url = @json(route('seguimiento-egresado.padron.estudiantes-del-portal'));
    const promocion = @json($promocion->id);
    const elegidos = new Set();
    let espera = null;
    let pedido = 0;

    const actualizarContador = () => {
      contador.textContent = elegidos.size === 1 ? '1 marcado' : elegidos.size + ' marcados';
      agregar.disabled = elegidos.size === 0;
    };
    const celda = (texto, clase) => { const td = document.createElement('td'); td.textContent = texto; if (clase) td.className = clase; return td; };

    function pintar(d) {
      filas.replaceChildren();
      todos.checked = false;
      if (!d.disponible || d.estudiantes.length === 0) {
        const buscando = buscar.value.trim() !== '';
        estado.textContent = buscando
          ? 'Ningún estudiante coincide con lo que buscas.'
          : 'No hay estudiantes del portal que hayan ingresado en ' + d.promocion + ' y falten en el padrón.';
        return;
      }
      d.estudiantes.forEach((e) => {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        const cb = document.createElement('input');
        cb.type = 'checkbox'; cb.value = e.id; cb.disabled = !e.puede_agregarse; cb.checked = elegidos.has(e.id);
        cb.setAttribute('aria-label', 'Marcar a ' + e.nombre);
        cb.addEventListener('change', () => { cb.checked ? elegidos.add(e.id) : elegidos.delete(e.id); actualizarContador(); });
        td.appendChild(cb); tr.appendChild(td);
        tr.appendChild(celda(e.matricula));
        tr.appendChild(celda(e.nombre));
        tr.appendChild(celda(e.documento));
        tr.appendChild(e.puede_agregarse ? celda(e.correo) : celda('Sin correo registrado', 'sg-est-sin'));
        filas.appendChild(tr);
      });
      estado.textContent = d.total > d.estudiantes.length
        ? 'Se muestran los primeros ' + d.estudiantes.length + ' de ' + d.total + '. Usa el buscador para acotar.'
        : (d.total === 1 ? '1 estudiante.' : d.total + ' estudiantes.');
    }

    async function cargar() {
      const este = ++pedido;
      estado.textContent = 'Cargando…';
      try {
        const r = await fetch(url + '?promocion=' + promocion + '&q=' + encodeURIComponent(buscar.value.trim()), { headers: { 'Accept': 'application/json' } });
        if (!r.ok) throw new Error();
        const d = await r.json();
        if (este === pedido) pintar(d);
      } catch (_) {
        if (este === pedido) { filas.replaceChildren(); estado.textContent = 'No se pudo cargar la lista. Cierra la ventana e inténtalo de nuevo.'; }
      }
    }

    abrir.addEventListener('click', () => { elegidos.clear(); actualizarContador(); buscar.value = ''; dlg.showModal(); cargar(); });
    document.getElementById('est-cancelar').addEventListener('click', () => dlg.close());
    buscar.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(cargar, 300); });
    todos.addEventListener('change', () => {
      filas.querySelectorAll('input[type=checkbox]:not(:disabled)').forEach((cb) => {
        cb.checked = todos.checked;
        todos.checked ? elegidos.add(Number(cb.value)) : elegidos.delete(Number(cb.value));
      });
      actualizarContador();
    });
    form.addEventListener('submit', () => {
      form.querySelectorAll('input[name="estudiantes[]"]').forEach((i) => i.remove());
      elegidos.forEach((id) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'estudiantes[]'; i.value = id; form.appendChild(i); });
    });
  })();
</script>
