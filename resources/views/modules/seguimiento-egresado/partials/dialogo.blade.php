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
  })();
</script>
