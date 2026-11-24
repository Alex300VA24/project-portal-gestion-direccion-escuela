{{--
  Ventana «Ver respuesta», compartida por las pantallas internas. Cualquier botón con data-respuesta="URL" la abre encima de la
  página actual y carga ahí la respuesta (así no se sale de la lista ni se cargan todas las respuestas de golpe).
--}}
<dialog class="sg-dialogo sg-form-dialogo" id="dlg-respuesta" style="width: min(94vw, 640px);" aria-labelledby="dlg-respuesta-titulo">
  <form method="dialog">
    <div class="sg-dialogo-cuerpo" id="respuesta-contenido"></div>
    <div class="sg-dialogo-pie"><button type="submit" class="sg-dialogo-secundario" autofocus>Cerrar</button></div>
  </form>
</dialog>
<script>
  (() => {
    const dlg = document.getElementById('dlg-respuesta');
    const contenido = document.getElementById('respuesta-contenido');
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
    document.addEventListener('click', async (e) => {
      const boton = e.target.closest('[data-respuesta]');
      if (!boton) return;
      contenido.innerHTML = '<p class="sg-help">Cargando…</p>';
      dlg.showModal();
      try {
        const r = await fetch(boton.dataset.respuesta, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error();
        contenido.innerHTML = await r.text();
      } catch (_) {
        contenido.innerHTML = '<p class="sg-help">No se pudo cargar la respuesta. Ciérrala e inténtalo de nuevo.</p>';
      }
    });
  })();
</script>
