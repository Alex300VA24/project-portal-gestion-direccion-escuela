<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fira-sans:400,500,600,700|fira-code:500,600&display=swap" rel="stylesheet">
<style>
    .tz { --tz-ink: var(--portal-ink, #141a33); --tz-muted: var(--portal-muted, #4a5170); --tz-navy: var(--portal-navy, #17224d);
          --tz-green: var(--portal-green, #0d7a50); --tz-green-dark: var(--portal-green-dark, #09613f); --tz-canvas: var(--portal-canvas, #f4f4ef);
          --tz-surface: #fff; --tz-alt: var(--portal-surface-alt, #eef0f3); --tz-border: var(--portal-border, #dfe3ea);
          --tz-focus: var(--portal-focus, #f2a900); --tz-danger: var(--portal-danger, #a11d12); --tz-ease: cubic-bezier(.2, .8, .2, 1);
          font: 400 16px/1.55 'Fira Sans', system-ui, sans-serif; color: var(--tz-ink); background: var(--tz-canvas); -webkit-font-smoothing: antialiased; }
    .tz *, .tz *::before, .tz *::after { box-sizing: border-box; }
    .tz :focus-visible { outline: 3px solid var(--tz-focus); outline-offset: 3px; }
    .tz a { color: var(--tz-green-dark); }
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

    /* Estructura */
    .tz-band { background: var(--tz-navy); color: #fff; }
    .tz-band-inner { max-width: 1500px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; gap: 16px; }
    .tz-brand { display: flex; align-items: center; gap: 12px; color: #fff !important; text-decoration: none; }
    .tz-brand-mark { width: 42px; height: 42px; border-radius: 11px; background: var(--tz-green); display: grid; place-items: center; font-family: 'Fira Code', monospace; font-weight: 600; font-size: 13px; }
    .tz-brand strong { display: block; font-size: 17px; font-weight: 700; line-height: 1.2; }
    .tz-brand span { font-size: 13px; color: #c9d1ea; }
    .tz-band-actions { margin-left: auto; display: flex; align-items: center; gap: 12px; }
    .tz-user { display: flex; align-items: center; gap: 10px; }
    .tz-avatar { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #7de5ba; display: grid; place-items: center; font-weight: 700; font-size: 14px; }
    .tz-user strong { display: block; font-size: 14px; line-height: 1.2; }
    .tz-user small { font-family: 'Fira Code', monospace; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #7de5ba; }
    .tz-user small a { color: #7de5ba; }
    .tz-bell { position: relative; width: 44px; height: 44px; border-radius: 10px; border: 1px solid rgba(255,255,255,.25); background: transparent; color: #fff; display: grid; place-items: center; cursor: pointer; }
    .tz-bell-count { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px; background: var(--tz-focus); color: var(--tz-navy); font: 600 11px/20px 'Fira Code', monospace; }
    .tz-pop { position: absolute; right: 0; top: 52px; width: min(380px, 90vw); background: #fff; color: var(--tz-ink); border: 1px solid var(--tz-border); border-radius: 14px; box-shadow: 0 24px 64px rgba(8,15,35,.25); z-index: 50; overflow: hidden; }
    .tz-pop header { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--tz-border); }
    .tz-pop ul { list-style: none; margin: 0; padding: 0; max-height: 380px; overflow: auto; }
    .tz-pop li { padding: 12px 16px; border-bottom: 1px solid var(--tz-border); font-size: 14px; }
    .tz-pop li.is-new strong::before { content: ''; display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--tz-focus); margin-right: 8px; }
    .tz-link-btn { background: none; border: 0; padding: 0; color: var(--tz-green-dark); font: inherit; font-size: 13px; text-decoration: underline; cursor: pointer; }

    .tz-shell { max-width: 1500px; margin: 0 auto; display: grid; grid-template-columns: 248px minmax(0, 1fr); min-height: calc(100vh - 140px); }
    .tz-side { background: var(--tz-navy); color: #eef2ff; padding: 18px 12px; }
    .tz-side nav { display: grid; gap: 4px; position: sticky; top: 12px; }
    .tz-side a { display: flex; align-items: center; justify-content: space-between; min-height: 46px; padding: 0 14px; border-radius: 10px; color: #eef2ff; text-decoration: none; font-size: 15px; font-weight: 500; transition: background 160ms var(--tz-ease); }
    .tz-side a:hover { background: rgba(255,255,255,.08); }
    .tz-side a[aria-current="page"] { background: #fff; color: var(--tz-navy); font-weight: 700; }
    .tz-side .tz-count { font: 600 11px/20px 'Fira Code', monospace; min-width: 22px; height: 20px; text-align: center; border-radius: 999px; background: var(--tz-focus); color: var(--tz-navy); }
    .tz-main { padding: 28px clamp(16px, 3vw, 40px) 56px; min-width: 0; }
    .tz-page { max-width: 1180px; display: grid; gap: 22px; }
    @media (max-width: 900px) {
        .tz-shell { grid-template-columns: 1fr; }
        .tz-side { padding: 8px; }
        .tz-side nav { display: flex; overflow-x: auto; position: static; }
        .tz-side a { white-space: nowrap; }
        .tz-user div { display: none; }
    }

    .tz-crumb { font-size: 13px; color: var(--tz-muted); margin-bottom: 6px; }
    .tz-crumb a { color: var(--tz-muted); }
    .tz-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
    .tz-head p { color: var(--tz-muted); margin-top: 6px; max-width: 760px; }

    /* Paneles y tarjetas */
    .tz-panel { background: var(--tz-surface); border: 1px solid var(--tz-border); border-radius: 14px; }
    .tz-panel-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--tz-border); flex-wrap: wrap; }
    .tz-panel-body { padding: 20px 22px; }
    .tz-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .tz-grid-2 { display: grid; gap: 22px; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); }
    @media (max-width: 1050px) { .tz-grid-2 { grid-template-columns: 1fr; } }
    .tz-card { background: var(--tz-surface); border: 1px solid var(--tz-border); border-radius: 14px; padding: 20px 22px; display: grid; gap: 6px; align-content: start; }
    .tz-stat strong { font-size: 2.2rem; line-height: 1; color: var(--tz-navy); font-weight: 700; }
    .tz-facts { display: grid; gap: 14px 24px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 16px; }
    .tz-facts dt { font-family: 'Fira Code', monospace; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); }
    .tz-facts dd { margin: 2px 0 0; }
    .tz-tags { display: flex; flex-wrap: wrap; gap: 8px; }

    /* Etiquetas de estado */
    .tz-status { display: inline-flex; align-items: center; gap: 6px; min-height: 28px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .tz-status.is-success { color: #0a5f3f; background: #e3f3ea; }
    .tz-status.is-warning { color: #7b3906; background: #fcefdf; }
    .tz-status.is-danger { color: #8e1a11; background: #fde9e7; }
    .tz-status.is-neutral { color: #4a5170; background: #eceef3; }
    .tz-status.is-info { color: var(--tz-navy); background: #e5eaf7; }

    /* Avisos */
    .tz-alert { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 10px; font-size: 15px; }
    .tz-alert.is-success { background: #e3f3ea; color: #0a5f3f; }
    .tz-alert.is-danger { background: #fde9e7; color: #721c16; }
    .tz-alert.is-warning { background: #fcefdf; color: #7b3906; }
    .tz-alert.is-info { background: #e5eaf7; color: var(--tz-navy); }
    .tz-alert ul { margin: 6px 0 0; padding-left: 18px; font-size: 14px; }

    /* Tablas */
    .tz-table-wrap { overflow-x: auto; }
    .tz-table { width: 100%; border-collapse: collapse; font-size: 15px; }
    .tz-table th { text-align: left; font-family: 'Fira Code', monospace; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); background: var(--tz-alt); padding: 12px 14px; }
    .tz-table td { padding: 14px; border-bottom: 1px solid var(--tz-border); vertical-align: top; }
    .tz-table tr:last-child td { border-bottom: 0; }

    /* Botones */
    .tz-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid transparent; font: 700 15px 'Fira Sans', sans-serif; cursor: pointer; text-decoration: none !important; transition: background 160ms var(--tz-ease), border-color 160ms var(--tz-ease); }
    .tz-btn-primary { background: var(--tz-green); color: #fff !important; }
    .tz-btn-primary:hover { background: var(--tz-green-dark); }
    .tz-btn-navy { background: var(--tz-navy); color: #fff !important; }
    .tz-btn-secondary { background: #fff; color: var(--tz-navy) !important; border-color: #bbc2ce; }
    .tz-btn-secondary:hover { border-color: var(--tz-navy); }
    .tz-btn-danger { background: #fff; color: var(--tz-danger) !important; border-color: #e6b3ad; }
    .tz-btn:disabled, .tz-btn[aria-disabled="true"] { background: var(--tz-alt); color: #8a90a6 !important; border-color: var(--tz-border); cursor: not-allowed; }
    .tz-btn-sm { min-height: 36px; padding: 0 12px; font-size: 14px; }
    .tz-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

    /* Formularios */
    .tz-form { display: grid; gap: 18px; }
    .tz-fields { display: grid; gap: 16px 20px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .tz-field { display: grid; gap: 6px; align-content: start; }
    .tz-field.is-wide { grid-column: 1 / -1; }
    .tz-field label, .tz-label { font-size: 14px; font-weight: 700; color: var(--tz-ink); }
    .tz-field .tz-hint { font-size: 12px; color: var(--tz-muted); }
    .tz-input, .tz-select, .tz-textarea { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #bbc2ce; border-radius: 10px; background: #fff; color: var(--tz-ink); font: 400 15px 'Fira Sans', sans-serif; transition: border-color 160ms var(--tz-ease), box-shadow 160ms var(--tz-ease); }
    .tz-textarea { min-height: 96px; resize: vertical; }
    .tz-input:focus, .tz-select:focus, .tz-textarea:focus { outline: none; border-color: var(--tz-green); box-shadow: 0 0 0 3px rgba(13,122,80,.15); }
    .tz-input[aria-invalid="true"], .tz-select[aria-invalid="true"], .tz-textarea[aria-invalid="true"] { border-color: var(--tz-danger); }
    .tz-error { font-size: 13px; color: var(--tz-danger); font-weight: 600; }
    .tz-check { display: flex; gap: 10px; align-items: flex-start; font-size: 15px; }
    .tz-check input { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--tz-green); }
    .tz-fieldset { border: 1px solid var(--tz-border); border-radius: 12px; padding: 18px; display: grid; gap: 14px; }
    .tz-fieldset legend { padding: 0 6px; font-weight: 700; color: var(--tz-navy); }
    .tz-step { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--tz-navy); color: #fff; font-size: 13px; font-weight: 700; margin-right: 6px; }

    /* Rúbrica */
    .tz-rubric-sec { padding: 10px 22px; background: var(--tz-alt); font-family: 'Fira Code', monospace; font-size: 12px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--tz-muted); }
    .tz-rubric-row { padding: 14px 22px; border-bottom: 1px solid var(--tz-border); display: grid; gap: 10px; }
    .tz-rubric-line { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 10px; align-items: center; }
    .tz-rubric-line > b { color: var(--tz-navy); }
    .tz-seg { display: inline-flex; border: 1px solid #bbc2ce; border-radius: 10px; overflow: hidden; }
    .tz-seg label { display: inline-flex; align-items: center; gap: 4px; min-height: 40px; padding: 0 12px; font-size: 14px; font-weight: 700; cursor: pointer; border-left: 1px solid #bbc2ce; color: var(--tz-ink); }
    .tz-seg label:first-child { border-left: 0; }
    .tz-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .tz-seg label.is-on-ok { background: #e3f3ea; color: #0a5f3f; }
    .tz-seg label.is-on-ko { background: #fde9e7; color: #8e1a11; }
    .tz-seg label.is-on-na { background: #eceef3; color: #4a5170; }
    .tz-seg label:has(input:focus-visible) { outline: 3px solid var(--tz-focus); outline-offset: -3px; }
    .tz-obsbox { border: 1px solid #f0c6c0; background: #fff7f6; border-radius: 10px; padding: 12px; display: grid; gap: 6px; }
    .tz-obsbox label { color: #8e1a11; font-size: 13px; }
    @media (max-width: 760px) { .tz-rubric-line { grid-template-columns: 26px 1fr; } .tz-rubric-line .tz-seg { grid-column: 2; } }
    .tz-progress { height: 10px; border-radius: 999px; background: #fde9e7; overflow: hidden; }
    .tz-progress span { display: block; height: 100%; background: var(--tz-green); border-radius: 999px; }
    .tz-bar { height: 10px; border-radius: 999px; background: var(--tz-alt); overflow: hidden; }
    .tz-bar span { display: block; height: 100%; background: var(--tz-navy); border-radius: 999px; }
    .tz-sticky { position: sticky; bottom: 0; z-index: 5; }

    /* Línea de tiempo y pasos */
    .tz-steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 12px; }
    .tz-steps li { text-align: center; display: grid; justify-items: center; gap: 4px; font-size: 13px; color: var(--tz-muted); }
    .tz-steps li b { font-size: 14px; color: var(--tz-ink); }
    .tz-dot { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; border: 2px solid #bbc2ce; color: var(--tz-muted); font-weight: 700; font-size: 13px; background: #fff; }
    .tz-steps li.is-done .tz-dot { background: var(--tz-green); border-color: var(--tz-green); color: #fff; }
    .tz-steps li.is-now .tz-dot { background: var(--tz-focus); border-color: var(--tz-focus); color: var(--tz-navy); box-shadow: 0 0 0 5px #fcefdf; }
    .tz-timeline { list-style: none; margin: 0; padding: 0; display: grid; gap: 0; }
    .tz-timeline li { position: relative; padding: 0 0 16px 26px; border-left: 2px solid var(--tz-border); margin-left: 7px; }
    .tz-timeline li:last-child { padding-bottom: 0; border-left-color: transparent; }
    .tz-timeline li::before { content: ''; position: absolute; left: -8px; top: 3px; width: 14px; height: 14px; border-radius: 50%; background: var(--tz-green); border: 3px solid #fff; box-shadow: 0 0 0 1px var(--tz-green); }
    .tz-list { list-style: none; margin: 0; padding: 0; }
    .tz-list > li { padding: 16px 22px; border-bottom: 1px solid var(--tz-border); display: grid; gap: 8px; }
    .tz-list > li:last-child { border-bottom: 0; }

    /* Estado vacío */
    .tz-empty { min-height: 200px; display: grid; place-items: center; text-align: center; padding: 32px 20px; color: var(--tz-muted); }
    .tz-empty svg { width: 40px; height: 40px; margin: 0 auto 10px; }
    .tz-empty strong { display: block; color: var(--tz-ink); font-size: 17px; margin-bottom: 4px; }
    .tz-empty p { max-width: 520px; margin: 0 auto; }

    /* Selección de rol */
    .tz-roles { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .tz-role { border: 1px solid var(--tz-border); border-radius: 16px; padding: 24px; background: #fff; display: grid; gap: 10px; text-decoration: none !important; color: var(--tz-ink) !important; transition: border-color 180ms var(--tz-ease), box-shadow 180ms var(--tz-ease); }
    .tz-role:hover { border-color: var(--tz-green); box-shadow: 0 16px 44px rgba(23,34,77,.09); }
    .tz-role b { color: var(--tz-green-dark); }

    /* Acta imprimible */
    .tz-doc { background: #fff; border: 1px solid var(--tz-border); border-radius: 6px; padding: clamp(20px, 5vw, 56px); font-family: 'Times New Roman', Georgia, serif; font-size: 16px; line-height: 1.7; color: #000; }
    .tz-doc h2 { color: #000; text-align: center; font-size: 17px; margin-bottom: 14px; }
    .tz-doc .tz-doc-center { text-align: center; }
    .tz-doc .tz-firmas { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 40px 24px; margin-top: 64px; text-align: center; font-size: 14px; }
    .tz-doc .tz-firmas div { border-top: 1px solid #000; padding-top: 6px; }
    @media print {
        body * { visibility: hidden; }
        .tz-doc, .tz-doc * { visibility: visible; }
        .tz-doc { position: absolute; inset: 0; border: 0; }
    }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    @media (prefers-reduced-motion: reduce) { .tz * { transition: none !important; } }
</style>
