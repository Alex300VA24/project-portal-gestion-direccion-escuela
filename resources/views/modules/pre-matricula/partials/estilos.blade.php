<style>
    .pm-flash { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 18px; padding: 13px 16px; border: 1px solid; border-radius: 12px; font-size: 14px; }
    .pm-flash svg { width: 20px; flex: none; }
    .pm-flash ul { margin: 0; padding-left: 18px; }
    .pm-flash.is-success { color: #0a5f3f; background: #e3f3ea; border-color: #9fd3b8; }
    .pm-flash.is-danger { color: #8e1a11; background: #fde9e7; border-color: #f1b0a8; }
    .pm-flash.is-warning { color: #6b3205; background: #fff4d6; border-color: #e4b84f; }
    .pm-flash.is-neutral { color: var(--quality-ink); background: var(--quality-surface-alt); border-color: var(--quality-border); }
    .pm-flash strong { display: block; }
    .pm-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(300px, 1fr); gap: 18px; align-items: start; }
    .pm-stack { display: grid; gap: 18px; }
    .pm-body { padding: 18px 22px; }
    .pm-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 0 0 18px; }
    .pm-facts div { padding: 14px 16px; background: var(--quality-surface); border: 1px solid var(--quality-border); border-radius: 12px; }
    .pm-facts dt { color: var(--quality-muted); font-size: 12px; font-weight: 700; }
    .pm-facts dd { margin: 4px 0 0; font: 600 22px 'Fira Code', monospace; }
    .pm-facts dd small { color: var(--quality-muted); font: 500 12px 'Fira Sans', sans-serif; }
    .pm-table-wrap { overflow-x: auto; }
    .pm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .pm-table th { padding: 10px 14px; color: var(--quality-muted); background: var(--quality-surface-alt); font-size: 11px; font-weight: 700; letter-spacing: .05em; text-align: left; text-transform: uppercase; white-space: nowrap; }
    .pm-table td { padding: 12px 14px; border-top: 1px solid var(--quality-border); vertical-align: top; }
    .pm-table td.pm-num, .pm-table th.pm-num { text-align: right; font-family: 'Fira Code', monospace; }
    .pm-table small { display: block; color: var(--quality-muted); font-size: 12px; }
    .pm-code { font: 600 12px 'Fira Code', monospace; color: var(--quality-green); }
    .quality-body .pm-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; padding: 0 16px; color: #fff; background: var(--quality-navy, #17224d); border: 1px solid transparent; border-radius: 10px; font-weight: 700; cursor: pointer; text-decoration: none; white-space: nowrap; }
    html[data-theme="dark"] .quality-body .pm-button { color: #101724; }
    .quality-body .pm-button:hover { filter: brightness(1.1); }
    .quality-body .pm-button:disabled { opacity: .5; cursor: not-allowed; }
    .quality-body .pm-button.is-secondary { color: var(--quality-ink); background: var(--quality-surface); border-color: var(--quality-border); }
    .quality-body .pm-button.is-secondary:hover { border-color: var(--quality-green); color: var(--quality-green); }
    .quality-body .pm-button.is-danger { color: #8e1a11; background: transparent; border-color: #f1b0a8; }
    .quality-body .pm-button.is-small { min-height: 44px; padding: 0 12px; font-size: 13px; }
    .quality-body .pm-button svg { width: 18px; }
    .pm-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .pm-form { display: grid; gap: 14px; }
    .pm-form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; align-items: start; }
    .pm-field { display: grid; gap: 6px; }
    .pm-check { display: flex; gap: 10px; align-items: flex-start; color: var(--quality-ink); font-size: 14px; }
    .pm-check input { width: 18px; height: 18px; margin-top: 2px; }
    .pm-field > span { color: var(--quality-muted); font-size: 13px; font-weight: 700; }
    .pm-field input, .pm-field select, .pm-field textarea { width: 100%; min-height: 44px; padding: 9px 12px; color: var(--quality-ink); background: var(--quality-surface); border: 1px solid var(--quality-border); border-radius: 9px; }
    .pm-field textarea { min-height: 80px; resize: vertical; }
    .pm-field small { color: var(--quality-muted); font-size: 12px; }
    .pm-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin-bottom: 18px; padding: 14px; background: var(--quality-surface); border: 1px solid var(--quality-border); border-radius: 12px; }
    .pm-filters .pm-field { flex: 1 1 180px; }
    .pm-course { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px 14px; align-items: center; padding: 14px 18px; border-top: 1px solid var(--quality-border); }
    .pm-course:first-of-type { border-top: 0; }
    .pm-course strong { display: block; }
    .pm-course small { display: block; margin-top: 2px; color: var(--quality-muted); font-size: 12px; }
    .pm-course .pm-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .pm-chip { display: inline-flex; align-items: center; min-height: 24px; padding: 2px 8px; color: var(--quality-muted); background: var(--quality-surface-alt); border-radius: 999px; font-size: 12px; font-weight: 600; }
    .pm-meter { height: 10px; overflow: hidden; background: var(--quality-surface-alt); border-radius: 999px; }
    .pm-meter i { display: block; height: 100%; background: var(--quality-green); border-radius: inherit; }
    .pm-meter.is-over i { background: #b42318; }
    .pm-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .pm-tabs button, .pm-tabs a { min-height: 44px; padding: 0 14px; color: var(--quality-muted); background: var(--quality-surface); border: 1px solid var(--quality-border); border-radius: 9px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .pm-tabs [aria-pressed="true"], .pm-tabs [aria-current="page"] { color: #fff; background: #17224d; border-color: #17224d; }
    .pm-timeline { margin: 0; padding: 0; list-style: none; }
    .pm-timeline li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 14px 18px; border-top: 1px solid var(--quality-border); }
    .pm-timeline li:first-child { border-top: 0; }
    .pm-timeline strong, .pm-timeline small { display: block; }
    .pm-timeline small { color: var(--quality-muted); font-size: 12px; }
    .pm-pagination { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 18px; border-top: 1px solid var(--quality-border); font-size: 13px; color: var(--quality-muted); }
    .pm-muted { color: var(--quality-muted); }
    .pm-mt { margin-top: 18px; }
    :is(.pm-button, .pm-field input, .pm-field select, .pm-field textarea, .pm-tabs button, .pm-tabs a):focus-visible { outline: 3px solid var(--quality-focus); outline-offset: 2px; }
    @media (max-width: 960px) { .pm-grid { grid-template-columns: 1fr; } }
    @media (max-width: 760px) { .quality-page-heading > div:first-child { flex-basis: auto; } }
    @media (max-width: 640px) { .pm-course { grid-template-columns: 1fr; } .pm-timeline li { grid-template-columns: 1fr; } }
</style>
