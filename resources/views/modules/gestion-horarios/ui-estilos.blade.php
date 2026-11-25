{{-- Estilos del módulo, sin modificar los estilos compartidos del portal. --}}
<style>
.gh-ui { --gh-ui-ink:var(--quality-ink,var(--portal-ink,#141a33)); --gh-ui-muted:var(--quality-muted,var(--portal-muted,#4a5170)); --gh-ui-border:var(--quality-border,var(--portal-border,#dfe3ea)); --gh-ui-accent:var(--portal-green,#0d7a50); --gh-ui-soft:var(--quality-surface-alt,var(--portal-surface-alt,#eef0f3)); --gh-ui-surface:var(--quality-surface,var(--portal-surface,#fff)); --gh-ui-navy:var(--portal-navy,#17224d); color:var(--gh-ui-ink); font-size:1rem; line-height:1.55; }
.gh-ui :is(h1,h2,h3) { font-weight:700; line-height:1.25; }
.gh-ui .quality-page-heading { display:block; margin:1.15rem 0; }
.gh-ui .quality-page-heading h1 { font-size:clamp(1.65rem,3vw,2.4rem); margin:.35rem 0; letter-spacing:-.025em; }
.gh-ui :is(h2,h3) { font-size:1.12rem; margin:0 0 .4rem; }
.gh-ui p { margin:.45rem 0; }
.gh-ui > nav[aria-label="Ruta actual"] { font-size:.82rem; color:var(--gh-ui-muted); }
.gh-ui > nav[aria-label="Ruta actual"] a { color:var(--gh-ui-accent); text-decoration:underline; text-underline-offset:2px; }
.gh-ui .quality-panel { border:1px solid var(--gh-ui-border); border-radius:12px; background:var(--gh-ui-surface); box-shadow:none; min-width:0; }
.gh-ui .quality-panel-heading { padding:1rem 1.1rem; border-bottom:1px solid var(--gh-ui-border); }
.gh-ui .quality-panel-heading p { font-size:.86rem; color:var(--gh-ui-muted); }
.gh-ui label { font-size:.85rem; font-weight:600; min-width:0; max-width:100%; }
.gh-ui :is(select,input:not([type=checkbox]):not([type=radio]),textarea) { max-width:100%; min-width:0; border:1px solid #bbc2ce; border-radius:10px; padding:.56rem .7rem; font-size:.88rem; color:var(--gh-ui-ink); background:var(--gh-ui-surface); }
.gh-ui label .quality-select { display:block; width:100%; margin-top:.3rem; }
.gh-ui [aria-label^="Filtros"] > div { align-items:end; }
.gh-ui [aria-label^="Filtros"] > div > label { flex:1 1 185px; }
.gh-ui [aria-label^="Filtros"] > div > p { flex:1 1 100%; }
.gh-ui :is(button:not([role=tab]),.institutional-primary,.institutional-secondary,.quality-open-link) { display:inline-flex; align-items:center; justify-content:center; gap:.35rem; border:1px solid var(--gh-ui-border); border-radius:10px; padding:.45rem .7rem; min-height:40px; max-width:100%; font:inherit; font-size:.875rem; font-weight:700; color:var(--portal-green-dark,#09613f); background:var(--gh-ui-surface); text-decoration:none; cursor:pointer; white-space:normal; }
.gh-ui :is(.institutional-primary,button[type=submit],.gh-ui-primary) { color:#fff; background:var(--gh-ui-accent); border-color:var(--gh-ui-accent); }
.gh-ui :is(button,.quality-open-link):hover:not(:disabled) { box-shadow:0 0 0 2px #09613f20; }
.gh-ui :is(button,input,select,textarea):disabled { opacity:.6; cursor:not-allowed; }
.gh-ui :is(a,button,input,select,summary,textarea):focus-visible { outline:3px solid var(--quality-focus,var(--portal-focus,#f2a900)); outline-offset:3px; }
.gh-ui table:not(.gh-semana) { border-collapse:collapse; font-size:.86rem; }
.gh-ui table:not(.gh-semana) :is(th,td) { padding:.7rem .75rem; border-bottom:1px solid var(--gh-ui-border); vertical-align:top; overflow-wrap:anywhere; }
.gh-ui table:not(.gh-semana) thead th { color:var(--gh-ui-muted); font-weight:700; background:var(--gh-ui-soft); text-align:left; }
.gh-ui table:not(.gh-semana) tbody tr:hover { background:var(--gh-ui-soft); }
.gh-ui .quality-code { font-size:.76rem; overflow-wrap:anywhere; }
.gh-ui .quality-empty { padding:1.5rem; margin:1rem 0; border:1px dashed #b9c8d3; border-radius:10px; text-align:center; color:var(--gh-ui-muted); background:var(--gh-ui-surface); }
.gh-ui :is(.quality-alert,.gh-ui-note) { padding:.8rem 1rem; background:#fcefdf; color:#7b3906; border:1px solid #e3ca90; border-radius:8px; margin:.8rem 0; }
.gh-ui .gh-ui-success { padding:.8rem 1rem; color:#0a5f3f; background:#e3f3ea; border:1px solid #aad5b8; border-radius:8px; }
.gh-ui .gh-ui-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(145px,1fr)); gap:.7rem; margin:1rem 0; }
.gh-ui .gh-ui-metric { background:var(--gh-ui-surface); border:1px solid var(--gh-ui-border); padding:.85rem 1rem; border-radius:10px; }
.gh-ui .gh-ui-metric strong { display:block; font-size:1.55rem; line-height:1.2; font-variant-numeric:tabular-nums; }
.gh-ui .gh-ui-metric small { color:var(--gh-ui-muted); }
.gh-ui .gh-ui-group { margin:.65rem .75rem; border:1px solid var(--gh-ui-border); border-radius:9px; overflow:visible; }
.gh-ui .gh-ui-group summary { cursor:pointer; padding:.8rem; background:var(--gh-ui-soft); font-weight:650; }
.gh-ui .gh-ui-group summary small { display:block; color:var(--gh-ui-muted); font-weight:400; margin:.2rem 0 0 1rem; }
.gh-ui .gh-ui-actions { display:flex; align-items:center; flex-wrap:wrap; gap:.45rem; margin:.5rem 0; }
.gh-ui .gh-ui-state { display:inline-block; padding:.12rem .45rem; border-radius:5px; background:#e8edf3; color:#364861; font-size:.74rem; font-weight:650; }
.gh-ui .gh-ui-state[data-estado="PROPUESTA"] { background:#f9eed2; color:#694609; }
.gh-ui .gh-ui-state[data-estado="CONFIRMADA"] { background:#e1f3e7; color:#145d38; }
.gh-ui .gh-ui-editor { border:1px solid #93b5a4; border-radius:9px; background:var(--gh-ui-soft); padding:.8rem; margin:.6rem 0; }
.gh-ui .gh-ui-cell { display:block; padding:.35rem .4rem; border-radius:6px; text-align:center; font-size:.75rem; }
.gh-ui .gh-ui-cell[data-registro="1"] { color:#135f3d; background:#e1f3e7; font-weight:700; }
.gh-ui .gh-ui-cell[data-registro="0"] { color:#526176; background:#f3f5f7; }
.gh-ui .gh-ui-cell[data-duplicado="1"] { color:#824514; background:#fce9ce; font-weight:700; }
.gh-ui .gh-semana article { background:#eaf4ed; color:#141a33; border-radius:10px; padding:.6rem; }
.gh-ui .gh-semana article[data-tipo="PRACTICA"] { background:#f9efdf; }
.gh-ui .gh-semana article[data-tipo="LABORATORIO"] { background:#e8eff9; }
.gh-ui .gh-ui-links { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr)); gap:.8rem; margin:.8rem 0; }
.gh-ui .gh-ui-links .gh-home__step-link { min-height:76px; background:var(--gh-ui-surface); border:1px solid var(--gh-ui-border); padding:1rem; border-radius:10px; }
.gh-ui .gh-ui-links .gh-home__step-link:hover { border-color:var(--gh-ui-accent); }
.gh-ui .gh-ui-links .gh-home__step-link::after { content:'›'; font-size:1.3rem; margin-left:auto; color:var(--gh-ui-accent); }
.gh-ui .gh-ui-disabled { padding:.85rem 1rem; border:1px dashed #bac7d1; background:#f4f6f8; border-radius:9px; color:#526176; }
.gh-ui .gh-ui-disabled strong,.gh-ui .gh-ui-disabled small { display:block; }
.gh-ui .gh-ui-context { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1fr); gap:1rem; background:var(--gh-ui-surface); padding:1rem; border:1px solid var(--gh-ui-border); border-radius:12px; margin:1rem 0; }
.gh-ui .gh-ui-context select { width:100%; }
@@media(max-width:700px) { .gh-ui .gh-ui-context { grid-template-columns:1fr; } .gh-ui .quality-panel-heading { padding:.8rem; } .gh-ui .gh-ui-group { margin:.45rem; } }
/* Dialogs rendered inside the module, including nested components. */
.gh-ui :is(dialog,[role="dialog"]) { color:var(--gh-ui-ink); background:var(--gh-ui-surface); border:1px solid var(--gh-ui-border); border-radius:16px; max-width:calc(100vw - 2rem); max-height:90vh; overflow:auto; }
.gh-ui dialog::backdrop { background:rgba(8,15,35,.58); }
.gh-ui .gh-catalogo-tabla { overflow-x:auto; }
.gh-ui .gh-catalogo-tabla table { width:100%; min-width:440px; }
.gh-ui { font-family: 'Fira Sans',sans-serif; }
.gh-ui .gh-horario-combo { flex:1 1 220px; }
.gh-ui .gh-horario-combo-menu { z-index:60; }
.gh-ui .gh-horario-combo { position:relative; min-width:0; }
.gh-ui .gh-horario-combo-label { display:block; margin-bottom:.35rem; font-weight:500; }
.gh-ui .gh-horario-combo-control { display:flex; align-items:stretch; border:1px solid var(--gh-ui-border,#cddbd2); border-radius:8px; background:var(--gh-ui-surface); }
.gh-ui .gh-horario-combo-control:focus-within { outline:3px solid var(--quality-focus,var(--portal-focus,#f2a900)); outline-offset:1px; }
.gh-ui .gh-horario-combo-control input { flex:1 1 0; width:100%; min-width:0; border:0; border-radius:8px 0 0 8px; padding:.7rem .85rem; outline:none; box-shadow:none; }
.gh-ui .gh-horario-combo-control input:focus-visible { outline:none; }
.gh-ui .gh-horario-combo-control button { flex:0 0 44px; border:0; border-left:1px solid var(--gh-ui-border,#cddbd2); border-radius:0 8px 8px 0; padding:0; box-shadow:none; }
.gh-ui .gh-horario-combo-menu { position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:40; max-height:18rem; overflow-y:auto; padding:5px; border:1px solid var(--gh-ui-border,#cddbd2); border-radius:8px; background:var(--gh-ui-surface); box-shadow:0 5px 14px #123c3020; }
.gh-ui .gh-horario-combo-option { padding:.7rem .8rem; border-radius:5px; cursor:pointer; overflow-wrap:anywhere; font-size:.88rem; }
.gh-ui .gh-horario-combo-option.is-active { background:var(--gh-ui-navy); color:#fff; }
.gh-ui .gh-horario-combo-empty { padding:.7rem .8rem; color:var(--gh-ui-muted,#526176); font-size:.85rem; }
.gh-ui .gh-horario-combo [x-cloak] { display:none!important; }

.gh-ui .gh-horario-combo-control input { border:0; border-radius:8px 0 0 8px; width:100%; box-shadow:none; }
.gh-ui .gh-horario-combo-control button { border:0; border-left:1px solid var(--gh-ui-border); border-radius:0 8px 8px 0; }
/* Shared administrative shell. Scoped to the module; public layouts are independent. */
.gh-ui.gh-inicio-admin { margin-top:0; }
.gh-ui .gh-admin-breadcrumb { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
.gh-ui .gh-admin-heading .quality-eyebrow { display:block; font-family:'Fira Code',monospace; font-size:.78rem; font-weight:650; letter-spacing:.08em; text-transform:uppercase; color:var(--gh-ui-accent); }
.gh-ui .gh-admin-heading p { color:var(--gh-ui-muted); }
.gh-ui .gh-admin-heading h2 { margin:.55rem 0; }

.gh-ui .quality-eyebrow,.gh-ui .quality-code {font-family:'Fira Code',monospace;}
.gh-ui .gh-lectura-page,.gh-ui.gh-lectura-page {max-width:1180px;width:100%;min-width:0;margin:auto;padding:1.5rem;box-sizing:border-box;}
.gh-ui .gh-lectura-cards {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:1rem;margin:1rem 0;}
.gh-ui .gh-lectura-card {padding:1.25rem;display:flex;flex-direction:column;align-items:flex-start;gap:.75rem;}
.gh-ui .gh-lectura-card p {flex:1;color:var(--gh-ui-muted);}
.gh-ui .gh-lectura-nav {display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0;}
.gh-ui .gh-lectura-nav a {display:inline-flex;align-items:center;min-height:40px;padding:.5rem .9rem;border:1px solid var(--gh-ui-border);border-radius:9px;color:var(--gh-ui-ink);background:var(--gh-ui-surface);font-size:.875rem;font-weight:600;text-decoration:none;}
.gh-ui .gh-lectura-nav a[aria-current="page"] {background:var(--gh-ui-navy);color:white;border-color:var(--gh-ui-navy);}
.gh-ui .gh-lectura-nav a:hover:not([aria-current="page"]) {border-color:var(--gh-ui-accent);color:var(--gh-ui-accent);}
.gh-ui .gh-descarga-publicada span {font-size:.8125rem;color:var(--gh-ui-muted);}
.gh-ui .gh-ui-context > * {min-width:0;}
html[data-theme="dark"] .gh-ui,html[data-contrast="alto"] .gh-ui {color:var(--gh-ui-ink);}
@@media(max-width:480px){.gh-ui .gh-lectura-nav a {flex:1 1 100%;}.gh-ui .gh-lectura-card .institutional-primary {width:100%;}.gh-ui .gh-ui-actions {align-items:stretch;}}

@@media(max-width:640px){
 .gh-ui {max-width:100%; min-width:0; box-sizing:border-box; padding:1rem !important;}
 .gh-ui .gh-ui-actions {flex-wrap:wrap; gap:.5rem;}
 .gh-ui .gh-ui-context {grid-template-columns:minmax(0,1fr);}
 .gh-ui .gh-ui-metrics {grid-template-columns:repeat(2,minmax(0,1fr));}
 .gh-ui .quality-panel, .gh-ui .quality-select {max-width:100%; min-width:0; box-sizing:border-box;}
 .gh-ui .quality-page-heading h1 {font-size:1.7rem; line-height:1.2; overflow-wrap:anywhere;}
 .gh-ui .gh-catalogo-tabla {overflow-x:auto;}
 .gh-ui .gh-ciclo-vida textarea {width:100%;}
}
</style>
