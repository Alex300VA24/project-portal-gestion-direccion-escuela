<style>
    /* ===== Variables del módulo: tema claro (por defecto) ===== */
    .tz-body, .tz {
        --tz-ink: #141a33; --tz-muted: #4a5170; --tz-navy: #17224d; --tz-link: #09613f;
        --tz-green: #0d7a50; --tz-green-dark: #09613f; --tz-canvas: #f4f4ef; --tz-surface: #fff; --tz-alt: #eef0f3;
        --tz-border: #dfe3ea; --tz-field-border: #7f889c; --tz-field-bg: #fff; --tz-focus: #b45f00;
        --tz-danger: #a11d12; --tz-warning: #8a3f06;
        /* Rellenos sólidos con texto blanco (paso numerado, botón navy, barras). */
        --tz-fill: #17224d; --tz-on-fill: #fff;
        /* Botón principal: verde con texto blanco en ambos temas (5,3:1). */
        --tz-action: #0d7a50; --tz-action-hover: #09613f;
        /* Pares texto/fondo de los estados. */
        --tz-ok-fg: #0a5f3f; --tz-ok-bg: #e3f3ea; --tz-warn-fg: #7b3906; --tz-warn-bg: #fcefdf;
        --tz-ko-fg: #8e1a11; --tz-ko-bg: #fde9e7; --tz-neutral-fg: #4a5170; --tz-neutral-bg: #eceef3;
        --tz-info-fg: #17224d; --tz-info-bg: #e5eaf7; --tz-alert-ko-fg: #721c16;
        --tz-obs-bg: #fff7f6; --tz-obs-border: #f0c6c0; --tz-coautor-bg: #f1f8f4; --tz-coautor-border: #b7dcc8;
        --tz-danger-border: #e6b3ad; --tz-disabled-fg: #6b7186;
        --tz-tip-bg: #141a33; --tz-tip-fg: #fff;
        --tz-shadow: 0 16px 44px rgba(23, 34, 77, .09); --tz-pop-shadow: 0 24px 64px rgba(8, 15, 35, .25);
        /* Menú lateral: navy en ambos temas, como Calidad. */
        --tz-side-bg: #17224d; --tz-side-fg: #eef2ff; --tz-side-muted: rgba(238, 242, 255, .72); --tz-side-line: rgba(255, 255, 255, .14);
        --tz-badge-bg: #f2a900; --tz-badge-fg: #17224d;
        --tz-ease: cubic-bezier(.2, .8, .2, 1);
        --tz-side-w: 248px;
    }
    /* ===== Tema oscuro (paleta oscura de docs/DESIGN.md) ===== */
    html[data-theme="dark"] .tz-body, html[data-theme="dark"] .tz {
        --tz-ink: #eef2f7; --tz-muted: #aab5c8; --tz-navy: #c7d2fe; --tz-link: #5ee0b3;
        --tz-green: #5ee0b3; --tz-green-dark: #5ee0b3; --tz-canvas: #101724; --tz-surface: #172033; --tz-alt: #202a3b;
        --tz-border: #334158; --tz-field-border: #6b7a94; --tz-field-bg: #111a2b; --tz-focus: #93c5fd;
        --tz-danger: #ffaaa2; --tz-warning: #f7c48a;
        --tz-fill: #2e3d7a; --tz-on-fill: #fff;
        --tz-action: #0d7a50; --tz-action-hover: #09613f;
        --tz-ok-fg: #7ee2b8; --tz-ok-bg: #12372b; --tz-warn-fg: #f7c48a; --tz-warn-bg: #3d2a12;
        --tz-ko-fg: #ffaaa2; --tz-ko-bg: #43201d; --tz-neutral-fg: #c3cbdb; --tz-neutral-bg: #2a3446;
        --tz-info-fg: #c7d2fe; --tz-info-bg: #24305a; --tz-alert-ko-fg: #ffb4ab;
        --tz-obs-bg: #2e1c1b; --tz-obs-border: #7a3a34; --tz-coautor-bg: #12372b; --tz-coautor-border: #2f6b52;
        --tz-danger-border: #7a3a34; --tz-disabled-fg: #8f99ad;
        --tz-tip-bg: #eef2f7; --tz-tip-fg: #101724;
        --tz-shadow: 0 16px 44px rgba(0, 0, 0, .35); --tz-pop-shadow: 0 24px 64px rgba(0, 0, 0, .5);
        --tz-side-bg: #17224d;
        color-scheme: dark;
    }

    /* ===== Base ===== */
    .tz-body { margin: 0; min-height: 100dvh; background: var(--tz-canvas); color: var(--tz-ink); }
    .tz { font: 400 16px/1.55 'Fira Sans', system-ui, sans-serif; color: var(--tz-ink); background: var(--tz-canvas); -webkit-font-smoothing: antialiased; min-height: 100dvh; }
    .tz *, .tz *::before, .tz *::after { box-sizing: border-box; }
    .tz :focus-visible { outline: 3px solid var(--tz-focus); outline-offset: 3px; }
    .tz a { color: var(--tz-link); }
    .tz a.skip-link { z-index: 1000; color: #141a33; background: #fff; font-weight: 700; box-shadow: var(--tz-pop-shadow); }
    .tz h1, .tz h2, .tz h3 { color: var(--tz-navy); margin: 0; text-wrap: balance; }
    .tz h1 { font-size: clamp(1.6rem, 3vw, 2.1rem); font-weight: 700; line-height: 1.15; letter-spacing: -.02em; }
    .tz h2 { font-size: 20px; font-weight: 700; }
    .tz h3 { font-size: 17px; font-weight: 700; }
    .tz p { margin: 0; }
    .tz svg.tz-i { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: none; }
    .tz-code { font-family: 'Fira Code', ui-monospace, monospace; font-weight: 600; font-size: 13px; }
    .tz-eyebrow { font-family: 'Fira Code', ui-monospace, monospace; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); }
    .tz-muted { color: var(--tz-muted); }
    .tz-small { font-size: 13px; }
    .tz-warning-text { color: var(--tz-warning); }

    /* ===== Contenedor: menú lateral fijo + barra superior + contenido a todo el ancho ===== */
    .tz-side { position: fixed; z-index: 60; inset: 0 auto 0 0; width: var(--tz-side-w); display: flex; flex-direction: column; gap: 6px;
        padding: 18px 12px; background: var(--tz-side-bg); color: var(--tz-side-fg); overflow-y: auto; overflow-x: visible;
        transition: width 200ms var(--tz-ease); }
    .tz-brand { display: flex; align-items: center; gap: 12px; min-height: 48px; padding: 0 6px 18px; margin-bottom: 8px; border-bottom: 1px solid var(--tz-side-line); color: #fff !important; text-decoration: none; }
    .tz-brand-mark { width: 42px; height: 42px; flex: none; border-radius: 11px; background: var(--tz-action); color: #fff; display: grid; place-items: center; font-family: 'Fira Code', monospace; font-weight: 600; font-size: 13px; }
    .tz-brand-text { min-width: 0; }
    .tz-brand strong { display: block; font-size: 17px; font-weight: 700; line-height: 1.2; color: #fff; white-space: nowrap; }
    .tz-brand small { display: block; font-size: 12px; color: rgba(238, 242, 255, .75); line-height: 1.3; }
    .tz-nav { display: grid; gap: 4px; }
    .tz-side a:not(.tz-brand) { position: relative; display: flex; align-items: center; gap: 12px; min-height: 46px; padding: 0 13px; border-radius: 10px; color: var(--tz-side-fg); text-decoration: none; font-size: 15px; font-weight: 500; transition: background 160ms var(--tz-ease); }
    .tz-side a:not(.tz-brand):hover { background: rgba(255, 255, 255, .08); }
    .tz-nav a[aria-current="page"] { background: #fff; color: #17224d; font-weight: 700; }
    .tz-module-switch { margin-top: auto; border: 1px solid var(--tz-side-line); color: var(--tz-side-muted) !important; font-weight: 600 !important; }
    .tz-module-switch:hover { color: #fff !important; }
    .tz-side-label { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tz-side .tz-count { font: 600 11px/20px 'Fira Code', monospace; min-width: 22px; height: 20px; padding: 0 4px; text-align: center; border-radius: 999px; background: var(--tz-badge-bg); color: var(--tz-badge-fg); }
    .tz-side :focus-visible { outline-color: #f2a900; }

    .tz-main-shell { margin-left: var(--tz-side-w); min-height: 100dvh; min-width: 0; transition: margin-left 200ms var(--tz-ease); }
    .tz-topbar { position: sticky; top: 0; z-index: 40; display: flex; align-items: center; gap: 12px; height: 72px; padding: 0 clamp(12px, 2.4vw, 28px); background: var(--tz-surface); border-bottom: 1px solid var(--tz-border); }
    .tz-icon-btn { position: relative; display: inline-grid; place-items: center; width: 44px; height: 44px; flex: none; padding: 0; border-radius: 10px; border: 1px solid var(--tz-border); background: var(--tz-surface); color: var(--tz-ink); cursor: pointer; transition: border-color 160ms var(--tz-ease), color 160ms var(--tz-ease); }
    .tz-icon-btn:hover { color: var(--tz-link); border-color: var(--tz-link); }
    .tz-breadcrumb { display: flex; align-items: center; gap: 6px; min-width: 0; font-size: 14px; color: var(--tz-muted); }
    .tz-breadcrumb a { color: var(--tz-muted); text-decoration: none; white-space: nowrap; }
    .tz-breadcrumb a:hover { color: var(--tz-link); text-decoration: underline; }
    .tz-breadcrumb a[aria-current] { color: var(--tz-ink); font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
    .tz-breadcrumb svg.tz-i { width: 14px; height: 14px; }
    .tz-topbar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
    .tz-icon-sun { display: none; }
    html[data-theme="dark"] .tz-icon-sun { display: block; }
    html[data-theme="dark"] .tz-icon-moon { display: none; }

    .tz-bell-wrap { position: relative; }
    .tz-bell-count { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: var(--tz-badge-bg); color: var(--tz-badge-fg); font: 600 11px/20px 'Fira Code', monospace; }
    .tz-pop { position: absolute; right: 0; top: 52px; width: min(380px, 90vw); background: var(--tz-surface); color: var(--tz-ink); border: 1px solid var(--tz-border); border-radius: 14px; box-shadow: var(--tz-pop-shadow); z-index: 50; overflow: hidden; }
    .tz-pop header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--tz-border); }
    .tz-pop ul { list-style: none; margin: 0; padding: 0; max-height: 380px; overflow: auto; }
    .tz-pop li { padding: 12px 16px; border-bottom: 1px solid var(--tz-border); font-size: 14px; }
    .tz-pop li:has(form) { padding: 0; }
    .tz-aviso { display: grid; gap: 2px; width: 100%; min-height: 44px; padding: 12px 16px; text-align: left; background: none; border: 0; font: inherit; font-size: 14px; color: var(--tz-ink); cursor: pointer; }
    .tz-aviso:hover { background: var(--tz-alt); }
    .tz-pop li.is-new .tz-aviso strong::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--tz-badge-bg); margin-right: 8px; }
    .tz-link-btn { background: none; border: 0; padding: 0; min-height: 44px; color: var(--tz-link); font: inherit; font-size: 13px; text-decoration: underline; cursor: pointer; }

    .tz-user-menu { position: relative; }
    .tz-user-trigger { display: inline-flex; align-items: center; gap: 9px; min-height: 46px; padding: 3px 6px 3px 3px; border: 1px solid transparent; border-radius: 10px; background: transparent; color: var(--tz-ink); cursor: pointer; text-align: left; }
    .tz-user-trigger:hover, .tz-user-trigger[aria-expanded="true"] { background: var(--tz-alt); border-color: var(--tz-border); }
    .tz-avatar { width: 38px; height: 38px; flex: none; border-radius: 50%; display: grid; place-items: center; background: var(--tz-action); color: #fff; font: 600 12px 'Fira Code', monospace; }
    .tz-user-text strong, .tz-user-text small { display: block; }
    .tz-user-text strong { font-size: 13px; line-height: 1.25; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tz-user-text small { font-size: 11px; color: var(--tz-muted); }
    .tz-user-trigger > svg.tz-i { width: 16px; height: 16px; }
    .tz-user-panel { position: absolute; z-index: 90; top: calc(100% + 8px); right: 0; width: 240px; padding: 6px; background: var(--tz-surface); color: var(--tz-ink); border: 1px solid var(--tz-border); border-radius: 12px; box-shadow: var(--tz-pop-shadow); }
    .tz-user-panel-head { padding: 8px 12px 10px; margin-bottom: 4px !important; border-bottom: 1px solid var(--tz-border); }
    .tz-user-panel-head strong, .tz-user-panel-head small { display: block; }
    .tz-user-panel-head small { color: var(--tz-muted); font-size: 12px; }
    .tz-user-panel a, .tz-user-panel button { display: flex; align-items: center; width: 100%; min-height: 44px; padding: 0 12px; border: 0; border-radius: 8px; background: transparent; color: var(--tz-ink) !important; font: inherit; font-size: 14px; text-decoration: none; cursor: pointer; }
    .tz-user-panel a:hover, .tz-user-panel button:hover { background: var(--tz-alt); }
    .tz-user-panel form { margin: 4px 0 0; padding-top: 4px; border-top: 1px solid var(--tz-border); }
    .tz-user-panel form button { color: var(--tz-danger) !important; font-weight: 600; }

    .tz-main { display: block; padding: 28px clamp(16px, 3vw, 40px) 56px; min-width: 0; }
    .tz-main:focus { outline: none; }
    .tz-page { display: grid; gap: 22px; }
    /* Los hijos de las grillas no se ensanchan por sus tablas: cada tabla ya tiene su propio scroll (.tz-table-wrap). */
    .tz-page > *, .tz-form > * { min-width: 0; }
    .tz-velo { display: none; }

    /* Plegado (solo escritorio): barra de íconos; el nombre aparece como tooltip con el mouse o el foco del teclado. */
    @media (min-width: 901px) {
        .tz.is-plegado { --tz-side-w: 72px; }
        .tz.is-plegado .tz-brand { justify-content: center; padding: 0 0 18px; }
        .tz.is-plegado .tz-brand-text { display: none; }
        .tz.is-plegado .tz-side a:not(.tz-brand) { justify-content: center; padding: 0; gap: 0; }
        .tz.is-plegado .tz-side { overflow: visible; }
        .tz.is-plegado .tz-side-label { position: absolute; left: calc(100% + 14px); top: 50%; transform: translate(-4px, -50%); flex: none; overflow: visible;
            padding: 7px 12px; border-radius: 8px; background: var(--tz-tip-bg); color: var(--tz-tip-fg); font-size: 13px; font-weight: 600; box-shadow: var(--tz-pop-shadow);
            opacity: 0; visibility: hidden; pointer-events: none; z-index: 80; transition: opacity 140ms var(--tz-ease), transform 140ms var(--tz-ease), visibility 0s linear 140ms; }
        .tz.is-plegado .tz-side a:hover .tz-side-label, .tz.is-plegado .tz-side a:focus-visible .tz-side-label { opacity: 1; visibility: visible; transform: translate(0, -50%); transition-delay: 0s; }
        .tz.is-plegado .tz-side .tz-count { position: absolute; top: 4px; right: 2px; min-width: 18px; height: 18px; font-size: 10px; line-height: 18px; padding: 0 3px; }
        .tz.is-plegado .tz-nav a[aria-current="page"]::before { content: ''; position: absolute; left: -12px; top: 10px; bottom: 10px; width: 4px; border-radius: 0 4px 4px 0; background: var(--tz-badge-bg); }
    }
    @media (max-width: 900px) {
        /* Celular: el menú empieza oculto y se abre como cajón superpuesto, con íconos y nombres. */
        .tz-side { width: min(300px, 86vw); box-shadow: 0 24px 64px rgba(8, 15, 35, .35); transform: translateX(-100%); visibility: hidden;
            transition: transform 220ms var(--tz-ease), visibility 0s linear 220ms; }
        .tz.is-abierto .tz-side { transform: none; visibility: visible; transition: transform 220ms var(--tz-ease); }
        .tz-main-shell { margin-left: 0; }
        .tz-velo { display: block; position: fixed; inset: 0; z-index: 55; background: rgba(8, 15, 35, .58); }
        .tz-user-text { display: none; }
        .tz-breadcrumb a:first-child, .tz-breadcrumb svg.tz-i { display: none; }
    }
    @media (max-width: 480px) {
        .tz-topbar { gap: 8px; }
        .tz-topbar-actions { gap: 6px; }
        .tz-user-trigger > svg.tz-i { display: none; }
    }

    .tz-back { display: inline-flex; align-items: center; gap: 8px; justify-self: start; min-height: 44px; padding: 0 12px 0 6px; margin: -8px 0 -10px -6px; border-radius: 10px; font-size: 15px; font-weight: 600; text-decoration: none; color: var(--tz-link) !important; }
    .tz-back:hover { background: var(--tz-alt); text-decoration: underline; }
    .tz-back svg.tz-i { width: 18px; height: 18px; }
    .tz-crumb { font-size: 13px; color: var(--tz-muted); margin-bottom: 6px; }
    .tz-crumb a { color: var(--tz-muted); }
    .tz-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
    /* El contenido usa todo el ancho; solo los párrafos largos conservan una medida cómoda de lectura. */
    .tz-head p, .tz-prose { color: var(--tz-muted); margin-top: 6px; max-width: 760px; }

    /* ===== Paneles y tarjetas ===== */
    .tz-panel { background: var(--tz-surface); border: 1px solid var(--tz-border); border-radius: 14px; }
    .tz-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--tz-border); flex-wrap: wrap; }
    .tz-panel-body { padding: 20px 22px; }
    .tz-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .tz-grid-2 { display: grid; gap: 22px; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
    .tz-grid-2.is-lista-detalle { grid-template-columns: minmax(0, .8fr) minmax(0, 1.6fr); }
    @media (max-width: 1050px) { .tz-grid-2, .tz-grid-2.is-lista-detalle { grid-template-columns: minmax(0, 1fr); } }
    .tz-card { background: var(--tz-surface); border: 1px solid var(--tz-border); border-radius: 14px; padding: 20px 22px; display: grid; gap: 6px; align-content: start; }
    .tz-stat strong { font-size: 2.2rem; line-height: 1; color: var(--tz-navy); font-weight: 700; }
    .tz-facts { display: grid; gap: 14px 24px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 16px; }
    .tz-facts dt { font-family: 'Fira Code', monospace; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); }
    .tz-facts dd { margin: 2px 0 0; }
    .tz-tags { display: flex; flex-wrap: wrap; gap: 8px; }

    /* ===== Etiquetas de estado ===== */
    .tz-status { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .tz-status.is-success { color: var(--tz-ok-fg); background: var(--tz-ok-bg); }
    .tz-status.is-warning { color: var(--tz-warn-fg); background: var(--tz-warn-bg); }
    .tz-status.is-danger { color: var(--tz-ko-fg); background: var(--tz-ko-bg); }
    .tz-status.is-neutral { color: var(--tz-neutral-fg); background: var(--tz-neutral-bg); }
    .tz-status.is-info { color: var(--tz-info-fg); background: var(--tz-info-bg); }

    /* ===== Avisos ===== */
    .tz-alert { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 10px; font-size: 15px; }
    .tz-alert.is-success { background: var(--tz-ok-bg); color: var(--tz-ok-fg); }
    .tz-alert.is-danger { background: var(--tz-ko-bg); color: var(--tz-alert-ko-fg); }
    .tz-alert.is-warning { background: var(--tz-warn-bg); color: var(--tz-warn-fg); }
    .tz-alert.is-info { background: var(--tz-info-bg); color: var(--tz-info-fg); }
    .tz-alert a { color: inherit; font-weight: 700; }
    .tz-alert ul { margin: 6px 0 0; padding-left: 18px; font-size: 14px; list-style: disc; }

    /* ===== Tablas ===== */
    .tz-table-wrap { overflow-x: auto; position: relative; } /* relative: lo absoluto de dentro (p. ej. .sr-only) no escapa del recorte */
    .tz-table { width: 100%; border-collapse: collapse; font-size: 15px; }
    .tz-table th { text-align: left; font-family: 'Fira Code', monospace; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); background: var(--tz-alt); padding: 12px 14px; }
    .tz-table td { padding: 14px; border-bottom: 1px solid var(--tz-border); vertical-align: top; }
    .tz-table tr:last-child td { border-bottom: 0; }

    /* ===== Botones ===== */
    .tz-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid transparent; font: 700 15px 'Fira Sans', sans-serif; cursor: pointer; text-decoration: none !important; transition: background 160ms var(--tz-ease), border-color 160ms var(--tz-ease); }
    .tz-btn-primary { background: var(--tz-action); color: #fff !important; }
    .tz-btn-primary:hover { background: var(--tz-action-hover); }
    .tz-btn-navy { background: var(--tz-fill); color: var(--tz-on-fill) !important; }
    .tz-btn-secondary { background: var(--tz-surface); color: var(--tz-navy) !important; border-color: var(--tz-field-border); }
    .tz-btn-secondary:hover { border-color: var(--tz-navy); }
    .tz-btn-danger { background: var(--tz-surface); color: var(--tz-danger) !important; border-color: var(--tz-danger-border); }
    .tz-btn:disabled, .tz-btn[aria-disabled="true"] { background: var(--tz-alt); color: var(--tz-disabled-fg) !important; border-color: var(--tz-border); cursor: not-allowed; }
    .tz-btn-sm { min-height: 44px; padding: 0 12px; font-size: 14px; }
    .tz-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

    /* ===== Formularios ===== */
    .tz-form { display: grid; gap: 18px; }
    .tz-fields { display: grid; gap: 16px 20px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .tz-field { display: grid; gap: 6px; align-content: start; }
    .tz-field.is-wide { grid-column: 1 / -1; }
    .tz-field label, .tz-label { font-size: 14px; font-weight: 700; color: var(--tz-ink); }
    .tz-field .tz-hint { font-size: 12px; color: var(--tz-muted); }
    .tz-input, .tz-select, .tz-textarea { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid var(--tz-field-border); border-radius: 10px; background: var(--tz-field-bg); color: var(--tz-ink); font: 400 15px 'Fira Sans', sans-serif; transition: border-color 160ms var(--tz-ease), box-shadow 160ms var(--tz-ease); }
    .tz-input::placeholder, .tz-textarea::placeholder { color: var(--tz-muted); opacity: 1; }
    .tz-textarea { min-height: 96px; resize: vertical; }
    .tz-input:focus, .tz-select:focus, .tz-textarea:focus { outline: none; border-color: var(--tz-green); box-shadow: 0 0 0 3px rgba(13, 122, 80, .25); }
    .tz-input[aria-invalid="true"], .tz-select[aria-invalid="true"], .tz-textarea[aria-invalid="true"] { border-color: var(--tz-danger); }
    .tz-error { font-size: 13px; color: var(--tz-danger); font-weight: 600; }
    .tz-coautor { display: flex; align-items: flex-start; gap: 12px; margin-top: 4px; padding: 12px 14px; border: 1px solid var(--tz-coautor-border); border-left: 4px solid var(--tz-green); border-radius: 10px; background: var(--tz-coautor-bg); }
    .tz-coautor > div { flex: 1; min-width: 0; }
    .tz-coautor p { margin: 4px 0 0; }
    .tz-check { display: flex; gap: 10px; min-height: 44px; align-items: center; font-size: 15px; }
    .tz-check input { width: 22px; height: 22px; flex: none; accent-color: var(--tz-action); }
    .tz-fieldset { border: 1px solid var(--tz-border); border-radius: 12px; padding: 18px; display: grid; gap: 14px; }
    .tz-fieldset legend { padding: 0 6px; font-weight: 700; color: var(--tz-navy); }
    .tz-step { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--tz-fill); color: var(--tz-on-fill); font-size: 13px; font-weight: 700; margin-right: 6px; }

    /* ===== Rúbrica ===== */
    .tz-rubric-sec { padding: 10px 22px; background: var(--tz-alt); font-family: 'Fira Code', monospace; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); }
    .tz-rubric-row { padding: 14px 22px; border-bottom: 1px solid var(--tz-border); display: grid; gap: 10px; }
    .tz-rubric-line { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 10px; align-items: center; }
    .tz-rubric-line > b { color: var(--tz-navy); }
    .tz-seg { display: inline-flex; border: 1px solid var(--tz-field-border); border-radius: 10px; overflow: hidden; }
    .tz-seg label { display: inline-flex; align-items: center; gap: 4px; min-height: 44px; padding: 0 12px; font-size: 14px; font-weight: 700; cursor: pointer; border-left: 1px solid var(--tz-field-border); color: var(--tz-ink); }
    .tz-seg label:first-child { border-left: 0; }
    .tz-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .tz-seg label.is-on-ok { background: var(--tz-ok-bg); color: var(--tz-ok-fg); }
    .tz-seg label.is-on-ko { background: var(--tz-ko-bg); color: var(--tz-ko-fg); }
    .tz-seg label.is-on-na { background: var(--tz-neutral-bg); color: var(--tz-neutral-fg); }
    .tz-seg label:has(input:focus-visible) { outline: 3px solid var(--tz-focus); outline-offset: -3px; }
    .tz-obsbox { border: 1px solid var(--tz-obs-border); background: var(--tz-obs-bg); border-radius: 10px; padding: 12px; display: grid; gap: 6px; }
    .tz-obsbox label { color: var(--tz-ko-fg); font-size: 13px; }
    @media (max-width: 760px) { .tz-rubric-line { grid-template-columns: 26px 1fr; } .tz-rubric-line .tz-seg { grid-column: 2; } }
    .tz-progress { height: 10px; border-radius: 999px; background: var(--tz-ko-bg); overflow: hidden; }
    .tz-progress span { display: block; height: 100%; background: var(--tz-action); border-radius: 999px; }
    .tz-bar { height: 10px; border-radius: 999px; background: var(--tz-alt); overflow: hidden; }
    .tz-bar span { display: block; height: 100%; background: var(--tz-navy); border-radius: 999px; }
    .tz-sticky { position: sticky; bottom: 0; z-index: 5; }

    /* ===== Línea de tiempo y pasos ===== */
    .tz-steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; }
    .tz-steps li { text-align: center; display: grid; justify-items: center; gap: 4px; font-size: 13px; color: var(--tz-muted); }
    .tz-steps li b { font-size: 14px; color: var(--tz-ink); }
    .tz-dot { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; border: 2px solid var(--tz-field-border); color: var(--tz-muted); font-weight: 700; font-size: 13px; background: var(--tz-surface); }
    .tz-steps li.is-done .tz-dot { background: var(--tz-action); border-color: var(--tz-action); color: #fff; }
    .tz-steps li.is-now .tz-dot { background: var(--tz-badge-bg); border-color: var(--tz-badge-bg); color: var(--tz-badge-fg); box-shadow: 0 0 0 5px var(--tz-warn-bg); }
    .tz-timeline { list-style: none; margin: 0; padding: 0; display: grid; gap: 0; }
    .tz-timeline li { position: relative; padding: 0 0 16px 26px; border-left: 2px solid var(--tz-border); margin-left: 7px; }
    .tz-timeline li:last-child { padding-bottom: 0; border-left-color: transparent; }
    .tz-timeline li::before { content: ''; position: absolute; left: -8px; top: 3px; width: 14px; height: 14px; border-radius: 50%; background: var(--tz-action); border: 3px solid var(--tz-surface); box-shadow: 0 0 0 1px var(--tz-action); }
    .tz-list { list-style: none; margin: 0; padding: 0; }
    .tz-list > li { padding: 16px 22px; border-bottom: 1px solid var(--tz-border); display: grid; gap: 8px; }
    .tz-list > li:last-child { border-bottom: 0; }

    /* ===== Estado vacío ===== */
    .tz-empty { min-height: 200px; display: grid; place-items: center; text-align: center; padding: 32px 20px; color: var(--tz-muted); }
    .tz-empty svg { width: 40px; height: 40px; margin: 0 auto 10px; }
    .tz-empty strong { display: block; color: var(--tz-ink); font-size: 17px; margin-bottom: 4px; }
    .tz-empty p { max-width: 520px; margin: 0 auto; }

    /* ===== Selección de rol ===== */
    .tz-roles { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .tz-role { border: 1px solid var(--tz-border); border-radius: 16px; padding: 24px; background: var(--tz-surface); display: grid; gap: 10px; text-decoration: none !important; color: var(--tz-ink) !important; transition: border-color 180ms var(--tz-ease), box-shadow 180ms var(--tz-ease); }
    .tz-role:hover { border-color: var(--tz-green); box-shadow: var(--tz-shadow); }
    .tz-role b { color: var(--tz-link); }

    /* ===== Acta imprimible: papel blanco en ambos temas ===== */
    .tz-doc { background: #fff; border: 1px solid var(--tz-border); border-radius: 6px; padding: clamp(20px, 5vw, 56px); font-family: 'Times New Roman', Georgia, serif; font-size: 16px; line-height: 1.7; color: #000; }
    .tz-doc h2, .tz-doc h3 { color: #000; }
    .tz-doc h2 { text-align: center; font-size: 17px; margin-bottom: 14px; }
    .tz-doc .tz-doc-center { text-align: center; }
    .tz-doc .tz-firmas { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 40px 24px; margin-top: 64px; text-align: center; font-size: 14px; }
    .tz-doc .tz-firmas div { border-top: 1px solid #000; padding-top: 6px; }
    @media print {
        body * { visibility: hidden; }
        .tz-doc, .tz-doc * { visibility: visible; }
        .tz-doc { position: absolute; inset: 0; border: 0; }
    }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @media (prefers-reduced-motion: reduce) { .tz * { transition: none !important; } }
</style>
