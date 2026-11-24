{{--
  Diálogo único para avisar el resultado de una acción (guardar, cargar, enviar…).
  Se muestra solo si la página anterior dejó un mensaje en la sesión:
    success / exito → listo · warning → revisar · info → aviso · error → no se pudo completar.
  Desde JavaScript también se puede abrir: window.sgDialogo({ tipo: 'success', mensaje: '…' }).
  Sin JavaScript el mensaje se ve igual, porque el <dialog> llega abierto desde el servidor.
--}}
@php
  $aviso = null;
  foreach (['error' => 'error', 'warning' => 'warning', 'success' => 'success', 'exito' => 'success', 'info' => 'info'] as $clave => $tipo) {
      if (session()->has($clave) && is_string(session($clave)) && trim(session($clave)) !== '') {
          $aviso = ['tipo' => $tipo, 'mensaje' => session($clave)];
          break;
      }
  }
  $titulos = ['success' => 'Listo', 'info' => 'Aviso', 'warning' => 'Revisa esto', 'error' => 'No se pudo completar'];
  $iconos = [
      'success' => 'M5 13l4 4L19 7',
      'info' => 'M12 8h.01M11 12h1v4h1',
      'warning' => 'M12 7v6M12 17h.01',
      'error' => 'M7 7l10 10M17 7L7 17',
  ];
@endphp
<style>
  .sg-dialogo { border: 0; padding: 0; border-radius: 14px; width: min(92vw, 400px); background: var(--sg-surface, #fff); color: var(--sg-text, #15233d); box-shadow: 0 24px 60px rgba(15, 23, 42, .28); font-family: var(--sg-font, system-ui, sans-serif); overflow: hidden; }
  .sg-dialogo::backdrop { background: rgba(15, 23, 42, .45); }
  .sg-dialogo[open] { animation: sg-dialogo-in .18s ease-out; }
  @keyframes sg-dialogo-in { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
  .sg-dialogo-cuerpo { padding: 28px 28px 20px; text-align: center; }
  .sg-dialogo-icono { width: 48px; height: 48px; border-radius: 50%; margin: 0 auto 14px; display: grid; place-items: center; background: var(--sg-soft, #eef0ff); color: var(--sg-accent, #4f46e5); }
  .sg-dialogo[data-tipo="success"] .sg-dialogo-icono { background: var(--sg-success-soft, #e7f7f0); color: var(--sg-success, #087f5b); }
  .sg-dialogo[data-tipo="warning"] .sg-dialogo-icono { background: var(--sg-warning-soft, #fff3d8); color: var(--sg-warning, #a65d00); }
  .sg-dialogo[data-tipo="error"] .sg-dialogo-icono { background: var(--sg-danger-soft, #ffebe8); color: var(--sg-danger, #c0362c); }
  .sg-dialogo-icono svg { width: 24px; height: 24px; }
  .sg-dialogo-titulo { margin: 0 0 6px; font-size: 1.125rem; font-weight: 600; }
  .sg-dialogo-mensaje { margin: 0; color: var(--sg-muted, #536078); font-size: .9375rem; line-height: 1.5; white-space: pre-line; }
  .sg-dialogo-pie { padding: 0 28px 24px; display: flex; justify-content: center; }
  .sg-dialogo-pie button { min-width: 120px; padding: 9px 22px; border: 0; border-radius: 8px; background: var(--sg-navy, #172554); color: #fff; font-weight: 600; cursor: pointer; }
  .sg-dialogo-pie button:hover { filter: brightness(1.15); }
  .sg-dialogo-pie .sg-dialogo-secundario { background: transparent; color: var(--sg-text, #15233d); border: 1px solid var(--sg-border, #dde1da); }
  /* Ventanas con formulario: nunca más altas que la pantalla. El cuerpo se desplaza y los botones quedan siempre a la vista (también con zoom). */
  .sg-form-dialogo[open] { display: flex; flex-direction: column; max-height: calc(100vh - 32px); max-height: calc(100dvh - 32px); }
  .sg-form-dialogo > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
  .sg-form-dialogo .sg-dialogo-cuerpo { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
  .sg-form-dialogo .sg-dialogo-pie { flex: none; border-top: 1px solid var(--sg-border, #dde1da); padding-top: 14px; }
</style>
<dialog class="sg-dialogo" id="sg-dialogo" data-tipo="{{ $aviso['tipo'] ?? 'info' }}" aria-labelledby="sg-dialogo-titulo" aria-describedby="sg-dialogo-mensaje" @if($aviso) open @endif>
  <div class="sg-dialogo-cuerpo">
    <div class="sg-dialogo-icono" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path id="sg-dialogo-trazo" d="{{ $iconos[$aviso['tipo'] ?? 'info'] }}"/></svg>
    </div>
    <h2 class="sg-dialogo-titulo" id="sg-dialogo-titulo">{{ $titulos[$aviso['tipo'] ?? 'info'] }}</h2>
    <p class="sg-dialogo-mensaje" id="sg-dialogo-mensaje" role="status">{{ $aviso['mensaje'] ?? '' }}</p>
  </div>
  <form class="sg-dialogo-pie" method="dialog"><button type="submit" autofocus>Entendido</button></form>
</dialog>
<dialog class="sg-dialogo" id="sg-confirmar" data-tipo="info" aria-labelledby="sg-confirmar-titulo" aria-describedby="sg-confirmar-mensaje">
  <div class="sg-dialogo-cuerpo">
    <div class="sg-dialogo-icono" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8h.01M11 12h1v4h1"/></svg>
    </div>
    <h2 class="sg-dialogo-titulo" id="sg-confirmar-titulo">¿Continuar?</h2>
    <p class="sg-dialogo-mensaje" id="sg-confirmar-mensaje"></p>
  </div>
  <form class="sg-dialogo-pie" method="dialog" style="gap: 10px;">
    <button type="submit" value="no" class="sg-dialogo-secundario">Cancelar</button>
    <button type="submit" value="si" autofocus>Continuar</button>
  </form>
</dialog>
<script>
  (() => {
    const dialogo = document.getElementById('sg-dialogo');
    if (!dialogo || typeof dialogo.showModal !== 'function') return;
    const titulos = @json($titulos);
    const iconos = @json($iconos);
    const abrir = () => {
      if (dialogo.open) dialogo.close();
      dialogo.showModal();
    };
    // Un clic fuera de la tarjeta también lo cierra.
    dialogo.addEventListener('click', (evento) => { if (evento.target === dialogo) dialogo.close(); });
    window.sgDialogo = ({ tipo = 'info', mensaje = '', titulo = null }) => {
      dialogo.dataset.tipo = tipo;
      document.getElementById('sg-dialogo-titulo').textContent = titulo || titulos[tipo] || titulos.info;
      document.getElementById('sg-dialogo-mensaje').textContent = mensaje;
      document.getElementById('sg-dialogo-trazo').setAttribute('d', iconos[tipo] || iconos.info);
      abrir();
    };
    @if($aviso)
      abrir();
    @endif
    // Aviso pendiente de una acción que recargó la página (por ejemplo, guardar una promoción).
    try {
      const pendiente = sessionStorage.getItem('sgAviso');
      if (pendiente) {
        sessionStorage.removeItem('sgAviso');
        window.sgDialogo({ tipo: /^Aviso|Aviso:/.test(pendiente) ? 'warning' : 'success', mensaje: pendiente });
      }
    } catch (_) {}
  })();

  // Confirmación sobria para acciones que no se pueden deshacer: <form data-sg-confirmar="¿Seguro?"> o window.sgConfirmar().
  (() => {
    const confirmar = document.getElementById('sg-confirmar');
    window.sgConfirmar = (mensaje, titulo = '¿Continuar?') => new Promise((resolver) => {
      if (!confirmar || typeof confirmar.showModal !== 'function') { resolver(window.confirm(mensaje)); return; }
      document.getElementById('sg-confirmar-titulo').textContent = titulo;
      document.getElementById('sg-confirmar-mensaje').textContent = mensaje;
      confirmar.returnValue = 'no';
      confirmar.addEventListener('close', () => resolver(confirmar.returnValue === 'si'), { once: true });
      confirmar.showModal();
    });
    document.addEventListener('submit', (evento) => {
      const formulario = evento.target;
      if (!(formulario instanceof HTMLFormElement) || !formulario.dataset.sgConfirmar || formulario.dataset.sgConfirmado === '1') return;
      evento.preventDefault();
      window.sgConfirmar(formulario.dataset.sgConfirmar).then((acepto) => {
        if (!acepto) return;
        formulario.dataset.sgConfirmado = '1';
        formulario.requestSubmit(evento.submitter || undefined);
      });
    });
  })();

  // Acciones que tardan (enviar correos, cargar un archivo): al enviar el formulario, el botón queda ocupado con el mismo
  // giro del acceso, para que no parezca que no pasó nada y no se pulse dos veces. Cada formulario puede indicar el texto
  // (data-sg-cargando="Enviando invitaciones…") y una nota (data-sg-nota="Puede tardar unos segundos."). Va después de la
  // confirmación: si la persona cancela, el botón no se toca.
  (() => {
    const ocupados = [];
    const restaurar = () => {
      ocupados.splice(0).forEach(({ boton, html }) => {
        boton.disabled = false;
        boton.removeAttribute('aria-busy');
        boton.classList.remove('sg-ocupado');
        boton.innerHTML = html;
      });
      document.querySelectorAll('.sg-ocupado-nota').forEach((nota) => nota.remove());
    };
    // Al volver con «Atrás» el navegador puede mostrar la página tal como quedó: se deja lista otra vez.
    window.addEventListener('pageshow', (evento) => { if (evento.persisted) restaurar(); });
    document.addEventListener('submit', (evento) => {
      const formulario = evento.target;
      if (evento.defaultPrevented || !(formulario instanceof HTMLFormElement) || formulario.method.toLowerCase() !== 'post') return;
      const boton = evento.submitter || formulario.querySelector('button[type="submit"]');
      if (!boton || boton.classList.contains('sg-ocupado')) return;
      const html = boton.innerHTML;
      // Se espera a que el navegador reúna los datos del formulario antes de desactivar el botón.
      setTimeout(() => {
        ocupados.push({ boton, html });
        boton.disabled = true;
        boton.setAttribute('aria-busy', 'true');
        boton.classList.add('sg-ocupado');
        boton.replaceChildren();
        const giro = document.createElement('i');
        giro.className = 'sg-giro';
        giro.setAttribute('aria-hidden', 'true');
        const texto = document.createElement('span');
        texto.textContent = formulario.dataset.sgCargando || 'Procesando…';
        boton.append(giro, texto);
        if (formulario.dataset.sgNota) {
          const nota = document.createElement('span');
          nota.className = 'sg-help sg-ocupado-nota';
          nota.setAttribute('role', 'status');
          nota.textContent = formulario.dataset.sgNota;
          boton.insertAdjacentElement('afterend', nota);
        }
        // Red de seguridad: si la página no cambia (por ejemplo, una descarga), el botón vuelve a estar disponible.
        setTimeout(restaurar, 120000);
      }, 0);
    });
  })();
</script>
