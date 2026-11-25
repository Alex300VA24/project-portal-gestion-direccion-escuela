{{-- Avisos de cambio de estado: aparecen solos mientras la persona usa el portal --}}
<div id="avisos-vivo" class="avisos-vivo" role="status" aria-live="polite"></div>
<script>
    (() => {
        const caja = document.getElementById('avisos-vivo'), clave = 'bienestar-aviso-{{ auth()->id() }}';
        const ver = () => { try { return Number(localStorage.getItem(clave)) || 0; } catch { return 0; } };
        const guardar = (id) => { try { localStorage.setItem(clave, id); } catch {} };
        const mostrar = (a) => {
            const el = document.createElement('div');
            el.className = 'aviso-vivo';
            const enlace = document.createElement('a'), titulo = document.createElement('b'), texto = document.createElement('span'), cerrar = document.createElement('button');
            enlace.href = a.url; titulo.textContent = a.titulo; texto.textContent = a.mensaje;
            cerrar.type = 'button'; cerrar.textContent = '×'; cerrar.setAttribute('aria-label', 'Cerrar aviso'); cerrar.onclick = () => el.remove();
            enlace.append(titulo, texto); el.append(enlace, cerrar); caja.append(el);
            setTimeout(() => el.remove(), 12000);
        };
        const consultar = async () => {
            if (document.hidden) return;
            try {
                const r = await fetch('{{ route('bienestar.notificaciones.recientes') }}', { headers: { Accept: 'application/json' } });
                if (!r.ok || r.redirected) return;
                const d = await r.json(), ultimo = ver();
                d.avisos.filter((a) => a.id > ultimo).reverse().forEach(mostrar);
                if (d.avisos.length) guardar(Math.max(ultimo, ...d.avisos.map((a) => a.id)));
                const campana = document.querySelector('.campana summary');
                let cuenta = campana?.querySelector('span');
                if (campana && d.sinLeer && !cuenta) { cuenta = document.createElement('span'); campana.append(cuenta); }
                if (cuenta) { if (d.sinLeer) cuenta.textContent = d.sinLeer > 9 ? '9+' : d.sinLeer; else cuenta.remove(); }
            } catch {}
        };
        consultar();
        setInterval(consultar, 20000);
        document.addEventListener('visibilitychange', consultar);
    })();
</script>
