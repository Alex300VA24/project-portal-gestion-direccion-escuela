{{-- Aplica el tema guardado antes de pintar y define el botón claro/oscuro --}}
<script>
    (() => {
        const d = document.documentElement.dataset;
        const leer = (clave, defecto) => { try { return localStorage.getItem(clave) || defecto; } catch { return defecto; } };
        d.modulo = 'bienestar';
        d.theme = leer('portal-theme', 'light');
        d.contrast = leer('portal-contrast', 'normal');
        d.fontSize = leer('portal-font-size', 'normal');
        const sincronizar = () => document.querySelectorAll('[data-boton-tema]').forEach((b) => {
            const oscuro = d.theme === 'dark';
            b.setAttribute('aria-pressed', String(oscuro));
            b.setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
            b.title = b.getAttribute('aria-label');
        });
        const aplicar = (tema) => {
            d.theme = tema;
            sincronizar();
            window.dispatchEvent(new CustomEvent('portal-theme', { detail: tema }));
        };
        window.portalToggleTheme = () => {
            const tema = d.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem('portal-theme', tema); } catch { /* sin almacenamiento: solo esta página */ }
            aplicar(tema);
        };
        // Mantiene sincronizadas otras pestañas abiertas
        window.addEventListener('storage', (e) => { if (e.key === 'portal-theme') aplicar(e.newValue || 'light'); });
        document.addEventListener('DOMContentLoaded', sincronizar);
    })();
</script>
