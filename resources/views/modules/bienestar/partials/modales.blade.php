<script>
    document.addEventListener('click', (e) => {
        const abrir = e.target.closest('[data-abrir-modal]');
        if (abrir) {
            const modal = document.getElementById(abrir.dataset.abrirModal);
            if (!modal) return;
            const form = modal.querySelector('form');
            if (abrir.dataset.accion) {
                form.reset();
                modal.querySelector('.aviso-error')?.remove();
                form.action = abrir.dataset.accion;
                form.elements._accion.value = new URL(abrir.dataset.accion, location.href).pathname;
            }
            Object.entries(JSON.parse(abrir.dataset.campos || '{}')).forEach(([clave, valor]) => {
                modal.querySelectorAll(`[data-campo="${clave}"]`).forEach((el) => {
                    if (el.type === 'checkbox') el.checked = Array.isArray(valor) ? valor.includes(el.value) : Boolean(valor);
                    else if ('value' in el && el.tagName !== 'BUTTON' && el.tagName !== 'LI') el.value = valor ?? '';
                    else el.textContent = valor ?? '';
                });
            });
            modal.showModal();
        }
        // La X, Cancelar o un clic en el fondo cierran la ventana
        if (e.target.closest('[data-cerrar-modal]')) e.target.closest('dialog').close();
        else if (e.target.matches('dialog.modal-unt')) {
            const r = e.target.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) e.target.close();
        }
    });
    // Evita envíos duplicados
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented || !e.target.closest('dialog.modal-unt')) return;
        setTimeout(() => e.target.querySelectorAll('[type=submit]').forEach((b) => { b.disabled = true; }));
    });
    window.addEventListener('pageshow', () => document.querySelectorAll('dialog.modal-unt [type=submit]').forEach((b) => { b.disabled = false; }));
    // Si la validación falló, se reabre la misma ventana con lo escrito
    document.addEventListener('DOMContentLoaded', () => {
        const previo = @json($errors->any() ? old('_modal') : null), accion = @json($errors->any() ? old('_accion') : null);
        // Un enlace directo (?abrir=m-...) también abre la ventana, si ya sabe a dónde enviar
        const url = new URL(location.href), pedido = url.searchParams.get('abrir');
        if (pedido) { url.searchParams.delete('abrir'); history.replaceState(null, '', url); }
        const enlazado = /^m-[\w-]+$/.test(pedido || '') && document.getElementById(pedido);
        const modal = (typeof previo === 'string' && document.getElementById(previo))
            || (enlazado && enlazado.matches('dialog.modal-unt') && enlazado.querySelector('form')?.getAttribute('action') && enlazado);
        if (!modal || !modal.showModal) return;
        if (typeof accion === 'string' && /^\/bienestar\//.test(accion)) {
            modal.querySelector('form').action = accion;
            modal.querySelector('[name=_accion]').value = accion;
        }
        modal.showModal();
    });
</script>
