@verbatim
<style>
/* Complementos locales de la vista de seguimiento profesional (prefijo sgs-).
   Todo lo demás usa el vocabulario compartido sg-* y las variables --sg-*. */
.sgs-scope .sg-alert{margin-bottom:var(--sg-space-4)}.sgs-scope .sg-alert:empty,.sgs-scope .sg-field-error:empty{display:none}
.sgs-scope .sg-panel{margin-bottom:var(--sg-space-5)}
.sgs-scope .sg-section-title{margin-bottom:var(--sg-space-4)}
.sgs-scope fieldset.sg-field{display:block;border:0;margin-inline:0;padding:0;min-width:0}.sgs-scope legend.sg-label{padding:0;margin-bottom:var(--sg-space-2)}
.sgs-scope fieldset .sg-field-error{margin-top:var(--sg-space-2)}
.sgs-req::after{content:" *";color:var(--sg-danger)}
.sgs-block{margin-bottom:var(--sg-space-8)}
.sgs-forms{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--sg-space-4)}
.sgs-forms .sg-panel{margin:0;display:flex;flex-direction:column;align-items:flex-start;gap:var(--sg-space-2)}
.sgs-forms h3{font-size:16px;font-weight:650;margin:0}.sgs-forms p{margin:0 0 var(--sg-space-3);color:var(--sg-muted);font-size:14px}
.sgs-person{display:grid;grid-template-columns:minmax(230px,320px) minmax(0,1fr);gap:var(--sg-space-6);align-items:start}
.sgs-person dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:var(--sg-space-4);margin:0}
.sgs-person dt,.sgs-ident dt{font-size:var(--sg-text-sm);color:var(--sg-muted);margin-bottom:4px}.sgs-person dd,.sgs-ident dd{margin:0;font-weight:600}
.sgs-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--sg-space-4);margin-bottom:var(--sg-space-6)}
.sgs-stat{background:var(--sg-surface);border:var(--sg-stroke);border-radius:var(--sg-radius);padding:var(--sg-space-4) var(--sg-space-5)}
.sgs-stat-title{font-size:var(--sg-text-sm);color:var(--sg-muted)}.sgs-stat-value{font-size:28px;font-weight:650;line-height:1.2;margin:6px 0 2px}.sgs-stat-foot{font-size:12px;color:var(--sg-muted)}
.sgs-tabs{display:flex;gap:var(--sg-space-2);overflow-x:auto;margin:0 0 var(--sg-space-4);padding-bottom:4px;border-bottom:var(--sg-stroke)}
.sgs-tabs button{flex:none;min-height:44px;border:1px solid transparent;border-bottom:0;background:transparent;color:var(--sg-accent);border-radius:var(--sg-radius-sm) var(--sg-radius-sm) 0 0;padding:10px 18px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap}
.sgs-tabs button:hover{background:var(--sg-hover)}.sgs-tabs button[aria-selected="true"]{background:var(--sg-surface);border-color:var(--sg-border);color:var(--sg-navy);box-shadow:inset 0 -3px 0 var(--sg-gold)}
.sgs-opts{display:flex;flex-wrap:wrap;gap:var(--sg-space-3)}
.sgs-opt{display:flex;align-items:center;gap:10px;min-height:44px;border:1px solid #9cabbc;border-radius:var(--sg-radius-sm);padding:10px 14px;cursor:pointer;background:var(--sg-surface);flex:1 1 150px}
.sgs-opt:hover{border-color:var(--sg-accent)}.sgs-opt:has(input:checked){border-color:var(--sg-accent);background:var(--sg-soft);font-weight:600}.sgs-opt input{accent-color:var(--sg-accent);margin:0}
.sgs-invalid .sgs-opt{border-color:var(--sg-danger)}.sgs-opt:focus-within{outline:3px solid var(--sg-focus);outline-offset:3px}
.sgs-scale{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:var(--sg-space-3)}.sgs-scale .sgs-opt{flex:none;font-size:var(--sg-text-sm)}
.sgs-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:var(--sg-space-3)}.sgs-checks .sgs-opt{flex:none;font-size:var(--sg-text-sm)}
.sgs-cond{border-left:3px solid var(--sg-accent);background:var(--sg-soft);border-radius:0 var(--sg-radius-sm) var(--sg-radius-sm) 0;padding:var(--sg-space-5);margin-top:var(--sg-space-4)}
.sgs-scope .sgs-cond > * + *{margin-top:var(--sg-space-4)}
.sgs-item{border:var(--sg-stroke);border-radius:var(--sg-radius);padding:var(--sg-space-4);margin-bottom:var(--sg-space-4);background:var(--sg-surface)}
.sgs-item-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sg-space-3)}
.sgs-linkbtn{background:none;border:0;padding:6px 4px;cursor:pointer;font-size:14px}
.sgs-camp{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;font-size:var(--sg-text-sm);color:var(--sg-muted)}
.sgs-wide{min-width:760px}.sgs-wide-sm{min-width:680px}.sgs-muted{color:var(--sg-muted)}
.sgs-detail td{background:var(--sg-soft)}.sgs-detail-in{position:sticky;left:16px}
.sgs-detail dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px 18px;margin:0}.sgs-detail dt{font-size:12px;color:var(--sg-muted)}.sgs-detail dd{margin:2px 0 0}
.sgs-toolbar{max-width:320px;margin-bottom:var(--sg-space-4)}
.sgs-h3{font-size:var(--sg-text-lg);font-weight:600;margin:0 0 var(--sg-space-4)}

/* Vista previa de los formularios que recibe el egresado (diálogo nativo, sin menú de administración) */
.sgs-dialog{width:min(880px,calc(100% - 32px));max-height:calc(100dvh - 32px);margin:auto;padding:0;border:var(--sg-stroke);border-radius:var(--sg-radius);color:var(--sg-text);background:var(--sg-surface);box-shadow:0 24px 60px #142e4c55;overflow:hidden}
.sgs-dialog[open]{display:flex;flex-direction:column}.sgs-dialog::backdrop{background:#142e4ccc}
.sgs-dlg-head{display:flex;justify-content:space-between;align-items:center;gap:var(--sg-space-4);padding:var(--sg-space-3) var(--sg-space-5);background:var(--sg-navy);color:#fff}
.sgs-dlg-brand{display:flex;gap:12px;align-items:center;min-width:0}.sgs-dlg-mark{width:36px;height:40px;flex:none;border:1px solid #63809c;border-radius:var(--sg-radius);display:grid;place-items:center;font-weight:650}
.sgs-dlg-brand strong{display:block;font-size:15px;line-height:1.3}.sgs-dlg-brand small{display:block;color:#bdcede;font-size:12px}
.sgs-dlg-body{overflow-y:auto;padding:clamp(18px,3vw,32px)}
.sgs-dlg-body h2{font-size:24px;line-height:1.25;font-weight:650;margin:12px 0 8px;letter-spacing:-.3px}
.sgs-intro{color:var(--sg-muted);max-width:68ch;margin:0 0 var(--sg-space-4)}
.sgs-ident{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:var(--sg-space-4);background:var(--sg-soft);border-radius:var(--sg-radius);padding:var(--sg-space-4);margin:0 0 var(--sg-space-5)}
.sgs-ident div{min-width:0}
.sgs-progress{margin:var(--sg-space-4) 0 var(--sg-space-5)}
.sgs-progress-top{display:flex;justify-content:space-between;gap:12px;font-size:var(--sg-text-sm);color:var(--sg-muted);margin-bottom:8px}.sgs-progress-top strong{color:var(--sg-text)}
.sgs-bar{height:6px;background:var(--sg-soft);border-radius:3px;overflow:hidden}.sgs-bar span{display:block;height:100%;width:0;background:var(--sg-accent);transition:width .2s}
.sgs-step > h3{font-size:var(--sg-text-lg);font-weight:650;margin:0 0 var(--sg-space-4)}.sgs-step > h3:focus{outline:none}
.sgs-scope .sgs-step > * + *{margin-top:var(--sg-space-5)}.sgs-scope .sgs-step > h3 + *{margin-top:0}
.sgs-navbtns{display:flex;justify-content:space-between;gap:12px;margin-top:var(--sg-space-6);padding-top:var(--sg-space-4);border-top:var(--sg-stroke)}
.sgs-navbtns .sg-actions{margin-left:auto}
.sgs-gracias{text-align:left;padding:var(--sg-space-4) 0}.sgs-gracias h3{font-size:20px;margin:0 0 8px}.sgs-gracias p{color:var(--sg-muted);margin:0 0 var(--sg-space-4)}
@media(max-width:1150px){.sgs-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:780px){.sgs-person,.sgs-forms{grid-template-columns:minmax(0,1fr)}.sgs-scale{grid-template-columns:minmax(0,1fr)}}
@media(max-width:640px){.sgs-dialog{width:100%;max-width:100%;height:100dvh;max-height:100dvh;border-radius:0;border:0}.sgs-dlg-head{padding:var(--sg-space-3) var(--sg-space-4)}.sgs-dlg-brand small{display:none}.sgs-dlg-body h2{font-size:21px}}
@media(max-width:540px){.sgs-stats{grid-template-columns:minmax(0,1fr)}.sgs-navbtns{flex-wrap:wrap}}
</style>
@endverbatim
