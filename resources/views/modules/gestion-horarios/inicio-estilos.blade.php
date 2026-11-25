<style>
/* Estilos exclusivos del inicio de Gestión de Horarios.
   Se toman los colores, fuentes y estados del diseño institucional. */
.gh-home {
    --gh-ink: var(--quality-ink, var(--portal-ink, #141a33));
    --gh-muted: var(--quality-muted, var(--portal-muted, #4a5170));
    --gh-surface: var(--quality-surface, var(--portal-surface, #fff));
    --gh-surface-alt: var(--quality-surface-alt, var(--portal-surface-alt, #eef0f3));
    --gh-border: var(--quality-border, var(--portal-border, #dfe3ea));
    --gh-green: var(--quality-green, var(--portal-green-dark, #09613f));
    width: min(100% - 32px, 1180px);
    margin: 0 auto;
    padding: 28px 0 64px;
    color: var(--gh-ink);
}
.gh-home a { text-decoration: none; }
.gh-home__breadcrumb { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; margin-bottom: 22px; color: var(--gh-muted); font-size: 13px; }
.gh-home__breadcrumb a { color: var(--gh-green); font-weight: 700; }
.gh-home__breadcrumb a:hover { text-decoration: underline; }
.gh-home__hero { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 28px; overflow: hidden; padding: 38px 42px; border-radius: 16px; color: #fff; background: var(--portal-navy, #17224d); box-shadow: var(--portal-shadow, 0 16px 44px rgba(23,34,77,.09)); }
.gh-home__hero-content { position: relative; z-index: 1; max-width: 730px; }
.gh-home__eyebrow { display: block; margin-bottom: 10px; color: #7de5ba; font: 600 12px 'Fira Code', monospace; text-transform: uppercase; letter-spacing: .06em; }
.gh-home__hero h1 { margin: 0 0 12px; color: #fff; font-size: clamp(2rem, 4vw, 2.9rem); font-weight: 700; line-height: 1.1; letter-spacing: -.035em; text-wrap: balance; }
.gh-home__hero p { max-width: 650px; margin: 0; color: #e0e5f3; font-size: 16px; line-height: 1.6; }
.gh-home__hero-illustration { width: 142px; height: 142px; flex: none; color: #7de5ba; opacity: .42; transform: rotate(-8deg); stroke-width: 2; }
.gh-home__section { margin-top: 42px; }
.gh-home__section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; margin-bottom: 18px; }
.gh-home__stage { display: inline-block; color: var(--gh-green); font: 600 12px 'Fira Code', monospace; text-transform: uppercase; letter-spacing: .06em; }
.gh-home__section-heading h2 { margin: 7px 0 6px; font-size: clamp(1.45rem, 3vw, 1.85rem); font-weight: 700; line-height: 1.2; }
.gh-home__section-heading p { margin: 0; color: var(--gh-muted); font-size: 14px; line-height: 1.6; }
/* Recorrido guiado: grupos visibles y opciones de baja carga cognitiva. */
.gh-home__step-links { display: grid; gap: 9px; }
.gh-home__step-link { display: flex; align-items: center; min-width: 0; gap: 13px; padding: 13px 14px; background: var(--gh-surface-alt); border: 1px solid transparent; border-radius: 12px; color: var(--gh-ink) !important; transition: border-color .16s ease, background .16s ease; }
.gh-home__step-link:hover { background: var(--gh-surface); border-color: var(--gh-green); }
.gh-home__step-link .gh-home__icon { width: 42px; height: 42px; background: var(--gh-surface); }
.gh-home__step-link .gh-home__icon svg { width: 22px; height: 22px; }
.gh-home__step-copy { display: grid; min-width: 0; gap: 3px; }
.gh-home__step-copy strong { font-size: 14px; line-height: 1.35; }
.gh-home__step-copy small { font-size: 12px; color: var(--gh-muted); line-height: 1.45; }
.gh-home a:focus-visible { outline: 3px solid #36a67c; outline-offset: 3px; }
.gh-home__action-icon, .gh-home__public-icon { display: grid; width: 48px; height: 48px; flex: none; place-items: center; border-radius: 12px; background: var(--gh-surface-alt); color: var(--gh-green); }
.gh-home__action-icon svg, .gh-home__public-icon svg { width: 24px; height: 24px; }
.gh-home__notice { display: flex; align-items: flex-start; gap: 10px; margin-top: 14px; padding: 14px 17px; border: 1px solid #e4b84f; border-radius: 10px; background: #fff4d6; color: #633d0c; }
.gh-home__notice svg { width: 20px; height: 20px; flex: none; }
.gh-home__notice p { margin: 0; font-size: 13px; line-height: 1.65; }
.gh-home__access { margin-top: 30px; padding: 28px; border: 1px solid var(--gh-border); border-radius: 14px; background: var(--gh-surface); }
.gh-home__access h2 { margin: 0 0 8px; font-size: 20px; }
.gh-home__access p { margin: 0 0 16px; color: var(--gh-muted); }
.gh-home__public { display: flex; align-items: center; justify-content: space-between; gap: 22px; margin-top: 34px; padding: 26px; border: 1px solid var(--gh-border); border-radius: 14px; background: var(--gh-surface); }
.gh-home__public-content { display: flex; align-items: center; gap: 16px; min-width: 0; }
.gh-home__public h2 { margin: 5px 0; font-size: 20px; }
.gh-home__public p { margin: 0; color: var(--gh-muted); font-size: 14px; line-height: 1.6; }
.gh-home__button { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; gap: 12px; flex: none; padding: 11px 16px; border-radius: 10px; background: var(--portal-green, #0d7a50); color: #fff !important; font-size: 14px; font-weight: 700; text-align: center; transition: background 160ms var(--portal-ease, ease); }
.gh-home__button:hover { background: var(--portal-green-dark, #09613f); }
.gh-home__button span { font-size: 19px; line-height: 1; }
@media (max-width: 760px) {
    .gh-home__hero { padding: 30px 26px; }
    .gh-home__hero-illustration { display: none; }
    .gh-home__public { flex-direction: column; align-items: stretch; }
    .gh-home__button { width: 100%; }
}
@media (max-width: 540px) {
    .gh-home { width: min(100% - 24px, 1180px); padding: 18px 0 42px; }
    .gh-home__hero { padding: 28px 22px; }
    .gh-home__section { margin-top: 34px; }
    .gh-home__section-heading { align-items: flex-start; flex-direction: column; }
    .gh-home__public { padding: 20px; }
    .gh-home__public-content { align-items: flex-start; }
    .gh-home__public-icon { width: 40px; height: 40px; }
}
@media (prefers-reduced-motion: reduce) {
    .gh-home__step-link { transition: none; }
    .gh-home__button { transition: none; }
}
</style>
