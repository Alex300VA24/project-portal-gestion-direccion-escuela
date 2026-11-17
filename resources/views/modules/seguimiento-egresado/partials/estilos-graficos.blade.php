<style>
  .sg .sg-input:focus-visible, .sg .sg-select:focus-visible, .sg .sg-textarea:focus-visible { outline-offset: 0; border-color: var(--sg-focus); }
  .sg-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--sg-space-4); }
  .sg-kpi { display: flex; flex-direction: column; gap: var(--sg-space-1); min-width: 0; padding: var(--sg-space-4) var(--sg-space-5); border: var(--sg-stroke); border-top: 3px solid var(--sg-navy); border-radius: var(--sg-radius); background: var(--sg-surface); }
  .sg-kpi-head { display: flex; align-items: baseline; gap: var(--sg-space-2); margin: 0; font-size: 14px; font-weight: 600; line-height: 1.35; }
  .sg-kpi-code { flex: none; color: var(--sg-accent); font-family: var(--sg-font-code); font-size: 13px; font-weight: 700; }
  .sg-kpi-valor { margin: var(--sg-space-1) 0 0; color: var(--sg-navy); font-family: var(--sg-font-code); font-size: 32px; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }
  .sg-kpi-valor small { font-size: 15px; font-weight: 600; }
  .sg-kpi-sin { margin: var(--sg-space-1) 0 0; font-size: 18px; font-weight: 650; line-height: 1.35; color: var(--sg-muted); }
  .sg-kpi-pie { margin: 0; color: var(--sg-muted); font-size: var(--sg-text-sm); line-height: 1.4; }
  .sg-kpi-barra { height: 6px; margin-top: var(--sg-space-2); border-radius: 3px; background: var(--sg-hover); overflow: hidden; }
  .sg-kpi-barra span { display: block; height: 100%; background: var(--sg-success); }
  .sg-kpi-programa { border-top-color: var(--sg-gold); }
  .sg-kpi-meta { margin: auto 0 0; padding-top: var(--sg-space-2); color: var(--sg-muted); font-size: 12px; line-height: 1.45; }
  .sg-acciones-seccion { display: flex; flex-wrap: wrap; gap: var(--sg-space-2); }
  .sg-kpi-accion { margin: var(--sg-space-1) 0 0; font-size: var(--sg-text-sm); }
  .sg-kpi-accion a { display: inline-flex; align-items: center; min-height: 44px; }
  .sg-kpi-compacta { border-top-color: var(--sg-border); }
  .sg-kpi-compacta .sg-kpi-valor { font-size: 24px; }
  .sg-graficos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--sg-space-4); }
  .sg-graficos-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .sg-graf { display: flex; flex-direction: column; min-width: 0; }
  .sg-graf h3 { margin: 0; font-size: 16px; font-weight: 650; line-height: 1.35; }
  .sg-graf-nota { margin: 2px 0 var(--sg-space-3); color: var(--sg-muted); font-size: var(--sg-text-sm); }
  .sg-graf-lienzo { position: relative; flex: 1 1 auto; width: 100%; }
  .sg-graf-lienzo > canvas { position: absolute; inset: 0; }
  .sg-graf-ver { align-self: flex-start; }
  .sg-graf-lienzo[hidden] { display: none; }
  .sg-graf-ver { display: inline-flex; align-items: center; min-height: 44px; margin-top: var(--sg-space-2); padding: 0; border: 0; background: none; color: var(--sg-accent); font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .sg-graf-ver[hidden] { display: none; }
  .sg-graf-ver:hover { text-decoration: underline; }
  .sg-graf-ver:focus-visible { outline: 3px solid var(--sg-focus); outline-offset: 2px; }
  .sg-modal { width: min(860px, calc(100% - 32px)); max-height: calc(100dvh - 32px); padding: 0; border: var(--sg-stroke); border-radius: var(--sg-radius); background: var(--sg-surface); color: var(--sg-text); }
  .sg-modal[open] { display: flex; flex-direction: column; }
  .sg-modal:focus { outline: none; }
  .sg-modal::backdrop { background: color-mix(in srgb, var(--sg-navy) 55%, transparent); }
  .sg-modal-cabecera { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--sg-space-4); padding: var(--sg-space-4) var(--sg-space-5); border-bottom: var(--sg-stroke); }
  .sg-modal-cabecera h2 { margin: 0; color: var(--sg-navy); font-size: 18px; line-height: 1.3; }
  .sg-modal-cabecera p { margin: 4px 0 0; color: var(--sg-muted); font-size: var(--sg-text-sm); }
  .sg .sg-modal-cabecera .sg-button { flex: none; }
  .sg-modal-cuerpo { overflow-y: auto; padding: var(--sg-space-5); }
  .sg-modal-cuerpo > .sg-actions { justify-content: flex-end; margin-top: var(--sg-space-5); }
  .sg-modal .sg-table :is(th, td) { overflow-wrap: normal; word-break: normal; }
  .sg-modal .sg-table thead th { white-space: nowrap; }
  .sg-modal .sg-table tbody th { min-width: 170px; }
  #sg-modal-calculo .sg-table tbody th, #sg-modal-evidencias .sg-table tbody th { min-width: 0; }
  #sg-modal-evidencias .sg-table th[scope="row"] { white-space: nowrap; }
  body.sg:has(.sg-modal[open]) { overflow: hidden; }
  .sg-graf-vacio { display: grid; gap: 4px; place-content: center; min-height: 140px; padding: var(--sg-space-4); border: 1px dashed var(--sg-control-border); border-radius: var(--sg-radius); color: var(--sg-muted); font-size: 14px; text-align: center; }
  .sg-graf-vacio strong { color: var(--sg-text); font-size: 15px; }
  .sg-graf-oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
  .sg-bloque { margin-bottom: var(--sg-space-8); }
  .sg-criterio { display: flex; flex-wrap: wrap; align-items: center; gap: var(--sg-space-2); margin: 0 0 var(--sg-space-4); font-size: var(--sg-text-sm); color: var(--sg-muted); }
  @media (max-width: 1150px) { .sg-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .sg-graficos, .sg-graficos-3 { grid-template-columns: minmax(0, 1fr); } }
  @media (max-width: 540px) { .sg-kpis { grid-template-columns: minmax(0, 1fr); } .sg-kpi-valor { font-size: 28px; } .sg-modal { width: 100%; max-width: 100%; height: 100dvh; max-height: 100dvh; border: 0; border-radius: 0; } }
</style>
<noscript><style>.sg-modal { display: block; position: static; width: 100%; margin-top: var(--sg-space-3); }</style></noscript>
