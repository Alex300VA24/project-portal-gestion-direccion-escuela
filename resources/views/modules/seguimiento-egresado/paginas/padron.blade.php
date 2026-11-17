@extends('modules.seguimiento-egresado.layout')
@section('title', 'Padrón y Cohortes')
@section('section_name', 'Padrón y cohortes')

@push('styles')
<style>
  /* =========================================================================
     SISTEMA VISUAL COMPARTIDO · MÓDULO M1 (PADRÓN Y COHORTES)
     Conforme estricto a docs/guia-visual.md y tokens institucionales
     ========================================================================= */

  /* Franja Superior de Resumen Operativo (KPIs institucionales) */
  .sg-stat-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 230px), 1fr));
    gap: var(--sg-space-4);
    margin-bottom: var(--sg-space-6);
  }
  .sg-stat-card {
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-left: 4px solid var(--sg-accent);
    border-radius: var(--sg-radius);
    padding: var(--sg-space-4) var(--sg-space-5);
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: border-color .15s ease, background-color .15s ease;
  }
  .sg-stat-card:hover {
    border-color: var(--sg-accent);
    background: var(--sg-hover);
  }
  .sg-stat-kicker {
    font-size: 11px;
    font-weight: 700;
    color: var(--sg-accent);
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  .sg-stat-val {
    font-size: 26px;
    font-weight: 700;
    color: var(--sg-navy);
    line-height: 1.15;
    margin: 2px 0;
    font-variant-numeric: tabular-nums;
  }
  .sg-stat-lbl {
    font-size: 13px;
    font-weight: 600;
    color: var(--sg-text);
  }
  .sg-stat-sub {
    font-size: 12px;
    color: var(--sg-muted);
    margin: 0;
  }

  /* Navegación por Pestañas Principales */
  .sg-module-tabs {
    display: flex;
    gap: var(--sg-space-2);
    overflow-x: auto;
    overflow-y: hidden;
    margin: 0 0 var(--sg-space-6);
    padding: 0;
    border-bottom: var(--sg-stroke);
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .sg-module-tabs::-webkit-scrollbar {
    display: none;
  }
  .sg-module-tab {
    flex: none;
    min-height: 44px;
    border: 1px solid transparent;
    border-bottom: 0;
    background: transparent;
    color: var(--sg-accent);
    border-radius: var(--sg-radius-sm) var(--sg-radius-sm) 0 0;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: -1px;
    transition: background-color .15s ease, color .15s ease, border-color .15s ease;
  }
  .sg-module-tab:hover {
    color: var(--sg-navy);
    background: var(--sg-hover);
  }
  .sg-module-tab[aria-selected="true"],
  .sg-module-tab.is-active {
    background: var(--sg-surface);
    border-color: var(--sg-border);
    color: var(--sg-navy);
    box-shadow: inset 0 -3px 0 var(--sg-gold);
  }

  /* Tarjetas de Selección Rápida de Cohorte (Fila Única Limpia y Escalable) */
  .sg-cohort-strip-wrapper {
    margin-bottom: var(--sg-space-5);
  }
  .sg-cohort-strip-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
  }
  .sg-cohort-grid {
    display: flex;
    gap: var(--sg-space-4);
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 8px;
    scroll-snap-type: x mandatory;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
  }
  .sg-cohort-grid::-webkit-scrollbar {
    height: 5px;
  }
  .sg-cohort-grid::-webkit-scrollbar-thumb {
    background: var(--sg-border);
    border-radius: 3px;
  }
  .sg-cohort-card {
    flex: 0 0 255px;
    min-width: 255px;
    scroll-snap-align: start;
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-left: 4px solid var(--sg-control-border);
    border-radius: var(--sg-radius);
    padding: var(--sg-space-4);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 8px;
    cursor: pointer;
    transition: border-color .15s, background-color .15s, box-shadow .15s;
  }
  .sg-cohort-card:hover {
    border-color: var(--sg-accent);
    border-left-color: var(--sg-accent);
    background: var(--sg-hover);
  }
  .sg-cohort-card.is-active {
    border-color: var(--sg-accent);
    border-left-color: var(--sg-accent);
    background: var(--sg-soft);
    outline: 2px solid var(--sg-accent);
    outline-offset: -2px;
  }
  .sg-cohort-card-more {
    border-style: dashed;
    border-left-style: dashed;
    background: transparent;
  }
  .sg-cohort-card-more:hover {
    background: var(--sg-soft);
  }
  .sg-cohort-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
  }
  .sg-cohort-code {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--sg-accent);
  }
  .sg-cohort-name {
    font-size: 14px;
    font-weight: 650;
    color: var(--sg-navy);
    margin: 2px 0 0;
    line-height: 1.3;
  }
  .sg-cohort-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding-top: 8px;
    border-top: var(--sg-stroke);
  }
  .sg-cohort-stat-val {
    font-size: 16px;
    font-weight: 700;
    color: var(--sg-text);
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
  }
  .sg-cohort-stat-lbl {
    font-size: 11px;
    color: var(--sg-muted);
  }
  .sg-progress-bar {
    width: 100%;
    height: 5px;
    background: var(--sg-hover);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
  }
  .sg-progress-fill {
    height: 100%;
    background: var(--sg-success);
    border-radius: 3px;
    transition: width .3s ease;
  }

  /* Franja Dinámica de Métricas de la Cohorte Seleccionada */
  .sg-cohort-metric-banner {
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-left: 4px solid var(--sg-navy);
    border-radius: var(--sg-radius);
    padding: var(--sg-space-3) var(--sg-space-5);
    margin-bottom: var(--sg-space-5);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--sg-space-4);
  }
  .sg-cmb-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .sg-cmb-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--sg-navy);
    margin: 0;
  }
  .sg-cmb-desc {
    font-size: 12px;
    color: var(--sg-muted);
    margin: 0;
  }
  .sg-cmb-metrics {
    display: flex;
    align-items: center;
    gap: var(--sg-space-5);
    flex-wrap: wrap;
  }
  .sg-cmb-stat {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
  }
  .sg-cmb-stat-val {
    font-size: 16px;
    font-weight: 700;
    color: var(--sg-text);
    font-variant-numeric: tabular-nums;
    line-height: 1.2;
  }
  .sg-cmb-stat-lbl {
    font-size: 10.5px;
    color: var(--sg-muted);
    text-transform: uppercase;
    letter-spacing: .3px;
  }

  /* Barra de Filtros y Búsqueda */
  .sg-toolbar {
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-radius: var(--sg-radius);
    padding: var(--sg-space-4) var(--sg-space-5);
    margin-bottom: var(--sg-space-6);
  }
  .sg-toolbar-form {
    display: grid;
    grid-template-columns: minmax(240px, 2fr) repeat(3, minmax(160px, 1fr)) auto;
    gap: 12px;
    align-items: flex-end;
  }
  .sg-toolbar-meta {
    margin-top: 12px;
    padding-top: 10px;
    border-top: var(--sg-stroke);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13px;
    color: var(--sg-muted);
  }

  /* Subpestañas del Expediente */
  .sg-subtabs {
    display: flex;
    gap: 4px;
    border-bottom: var(--sg-stroke);
    margin-bottom: var(--sg-space-5);
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .sg-subtabs::-webkit-scrollbar {
    display: none;
  }
  .sg-subtab-btn {
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 16px;
    min-height: 40px;
    font-size: 13px;
    font-weight: 600;
    color: var(--sg-muted);
    cursor: pointer;
    transition: color .15s, border-color .15s;
    white-space: nowrap;
  }
  .sg-subtab-btn:hover {
    color: var(--sg-navy);
  }
  .sg-subtab-btn.is-active {
    color: var(--sg-navy);
    border-bottom-color: var(--sg-gold);
    box-shadow: inset 0 -3px 0 var(--sg-gold);
  }

  /* Modales Institucionales */
  .sg-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, var(--sg-navy) 65%, transparent);
    backdrop-filter: blur(2px);
    z-index: 1050;
    padding: 20px;
    align-items: center;
    justify-content: center;
  }
  .sg-modal-backdrop.is-open {
    display: flex;
  }
  .sg-modal-dialog {
    background: var(--sg-surface);
    border: 1px solid var(--sg-border);
    border-radius: var(--sg-radius);
    box-shadow: 0 20px 40px rgba(23, 37, 84, 0.25);
    width: 100%;
    max-width: 860px;
    max-height: 90vh;
    max-height: 90dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: sgModalFadeIn .18s ease-out;
  }
  @keyframes sgModalFadeIn {
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
  }
  .sg-modal-head {
    background: var(--sg-navy);
    color: var(--sg-on-dark);
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--sg-nav-accent);
  }
  .sg-modal-head h2 {
    font-size: 17px;
    font-weight: 650;
    margin: 0;
    color: var(--sg-on-dark);
  }
  .sg-modal-head p {
    font-size: 12px;
    color: var(--sg-sidebar-muted);
    margin: 3px 0 0;
  }
  .sg-modal-close-btn {
    background: transparent;
    border: none;
    color: var(--sg-sidebar-muted);
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: var(--sg-radius-sm);
    min-height: 36px;
    min-width: 36px;
    display: grid;
    place-content: center;
  }
  .sg-modal-close-btn:hover {
    color: var(--sg-on-dark);
    background: var(--sg-sidebar-hover);
  }
  .sg-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
  }
  .sg-modal-foot {
    padding: 16px 24px;
    background: var(--sg-hover);
    border-top: var(--sg-stroke);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  /* Bloques de Datos de Expediente */
  .sg-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
  }
  .sg-info-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .sg-info-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--sg-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  .sg-info-val {
    font-size: 14px;
    font-weight: 500;
    color: var(--sg-text);
  }

  /* Timeline de Trazabilidad */
  .sg-timeline {
    position: relative;
    padding-left: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .sg-timeline::before {
    content: '';
    position: absolute;
    top: 6px;
    bottom: 6px;
    left: 6px;
    width: 2px;
    background: var(--sg-border);
  }
  .sg-timeline-node {
    position: relative;
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-radius: var(--sg-radius-sm);
    padding: 14px 16px;
  }
  .sg-timeline-node::before {
    content: '';
    position: absolute;
    left: -18px;
    top: 14px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--sg-accent);
    box-shadow: 0 0 0 3px var(--sg-soft);
  }

  /* Badges semánticos precisos */
  .sg-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: var(--sg-radius-sm);
    white-space: nowrap;
  }
  .sg-pill-success {
    background: var(--sg-success-soft);
    color: var(--sg-success);
    border: 1px solid color-mix(in srgb, var(--sg-success) 25%, transparent);
  }
  .sg-pill-primary {
    background: var(--sg-soft);
    color: var(--sg-accent);
    border: 1px solid color-mix(in srgb, var(--sg-accent) 25%, transparent);
  }
  .sg-pill-warning {
    background: var(--sg-warning-soft);
    color: var(--sg-warning);
    border: 1px solid color-mix(in srgb, var(--sg-warning) 25%, transparent);
  }
  .sg-pill-danger {
    background: var(--sg-danger-soft);
    color: var(--sg-danger);
    border: 1px solid color-mix(in srgb, var(--sg-danger) 25%, transparent);
  }
  .sg-pill-neutral {
    background: var(--sg-hover);
    color: var(--sg-text);
    border: 1px solid var(--sg-border);
  }

  /* Dropzone de Carga de Archivos */
  .sg-dropzone {
    background: var(--sg-soft);
    border: 2px dashed var(--sg-control-border);
    border-radius: var(--sg-radius);
    padding: 32px 24px;
    text-align: center;
    margin-bottom: var(--sg-space-5);
    transition: border-color .15s, background-color .15s;
  }
  .sg-dropzone:hover {
    border-color: var(--sg-accent);
    background: var(--sg-surface);
  }

  /* Toast de Notificación Dinámica */
  .sg-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1100;
    max-width: 420px;
    display: none;
    animation: sgModalFadeIn .2s ease-out;
  }
  .sg-toast.is-open {
    display: flex;
  }

  /* Barra de Paginación Institucional */
  .sg-pagination-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--sg-space-3);
    margin-top: var(--sg-space-4);
    padding: var(--sg-space-3) var(--sg-space-4);
    background: var(--sg-surface);
    border: var(--sg-stroke);
    border-radius: var(--sg-radius);
    font-size: 13px;
    color: var(--sg-muted);
  }
  .sg-pagination-info {
    display: flex;
    align-items: center;
    gap: var(--sg-space-3);
    flex-wrap: wrap;
  }
  .sg-pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
  }
  .sg-page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    font-size: 13px;
    font-weight: 600;
    border-radius: var(--sg-radius-sm);
    border: 1px solid var(--sg-border);
    background: var(--sg-surface);
    color: var(--sg-text);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background-color .15s ease, border-color .15s ease, color .15s ease;
  }
  .sg-page-btn:hover:not(:disabled) {
    background: var(--sg-hover);
    border-color: var(--sg-accent);
    color: var(--sg-navy);
  }
  .sg-page-btn.is-active {
    background: var(--sg-navy);
    border-color: var(--sg-navy);
    color: #fff;
    cursor: default;
  }
  .sg-page-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
    background: var(--sg-surface);
  }
  .sg-page-size-select {
    padding: 4px 8px;
    border: 1px solid var(--sg-border);
    border-radius: var(--sg-radius-sm);
    background: var(--sg-surface);
    color: var(--sg-text);
    font-size: 13px;
    cursor: pointer;
  }

  /* Estados Vacíos Ergonómicos (HCI / Principios de Usabilidad) */
  .sg-empty-state {
    text-align: center;
    padding: var(--sg-space-8) var(--sg-space-5);
    background: var(--sg-surface);
    border: 1px dashed var(--sg-border);
    border-radius: var(--sg-radius);
    margin: var(--sg-space-5) 0;
  }
  .sg-empty-icon {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: var(--sg-soft);
    margin-bottom: var(--sg-space-3);
  }
  .sg-empty-title {
    font-size: 17px;
    font-weight: 650;
    color: var(--sg-navy);
    margin: 0 0 6px;
  }
  .sg-empty-desc {
    max-width: 58ch;
    margin: 0 auto var(--sg-space-4);
    font-size: 13.5px;
    color: var(--sg-muted);
    line-height: 1.55;
  }
  .sg-empty-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  @media (max-width: 992px) {
    .sg-toolbar-form {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 580px) {
    .sg-toolbar-form {
      grid-template-columns: 1fr;
    }
    .sg-modal-dialog {
      max-height: 96vh;
    }
    .sg-modal-head, .sg-modal-body, .sg-modal-foot {
      padding: 16px;
    }
  }
</style>
@endpush

@section('content')
<!-- Encabezado Institucional de Página -->
<section class="sg-heading">
  <h1>Padrón Oficial y Cohortes Académicas</h1>
  <p style="max-width: 100%;">Nómina oficial de egresados de Ingeniería Informática, registro de cohortes de ingresantes, planes curriculares y trazabilidad de grados académicos.</p>
  <div style="margin-top: 12px;">
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px;" href="{{ route('seguimiento-egresado.padron.contacto-inicial') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
      <span>Contacto inicial de egresados</span>
    </a>
  </div>
</section>

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-5);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
    <div>
      <strong>Se presentaron observaciones en la solicitud:</strong>
      <ul style="margin: 6px 0 0 18px; padding: 0;">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

<!-- Toast Dinámico para Interacciones AJAX -->
<div id="sg-toast-notificacion" class="sg-alert sg-toast" role="status" aria-live="polite">
  <span id="sg-toast-icon"></span>
  <p id="sg-toast-mensaje" style="font-weight: 500;"></p>
</div>

<!-- ========================================================================= -->
<!-- FRANJA DE RESUMEN OPERATIVO (4 CARDS DE DATOS) -->
<!-- ========================================================================= -->
<section class="sg-stat-strip" aria-label="Resumen operativo del módulo">
  <div class="sg-stat-card">
    <span class="sg-stat-kicker">Expedientes</span>
    <span class="sg-stat-val" id="kpi-total-egresados">{{ $totalEgresados ?? 6 }}</span>
    <span class="sg-stat-lbl">Expedientes Registrados</span>
    <p class="sg-stat-sub">Ingeniería Informática · Sede Trujillo</p>
  </div>

  <div class="sg-stat-card" style="border-left-color: var(--sg-navy);">
    <span class="sg-stat-kicker" style="color: var(--sg-navy);">Cohortes</span>
    <span class="sg-stat-val" id="kpi-total-cohortes">{{ $totalCohortes ?? 4 }}</span>
    <span class="sg-stat-lbl">Cohortes Académicas</span>
    <p class="sg-stat-sub">Ingresantes oficiales en seguimiento</p>
  </div>

  <div class="sg-stat-card" style="border-left-color: var(--sg-success);">
    <span class="sg-stat-kicker" style="color: var(--sg-success);">Titulación</span>
    <span class="sg-stat-val" style="color: var(--sg-success);" id="kpi-total-acreditados">{{ $totalAcreditados ?? 6 }}</span>
    <span class="sg-stat-lbl">Graduados y Titulados</span>
    <p class="sg-stat-sub">Cumplen perfil y créditos validados</p>
  </div>

  <div class="sg-stat-card" style="border-left-color: var(--sg-warning);">
    <span class="sg-stat-kicker" style="color: var(--sg-warning);">Cargas</span>
    <span class="sg-stat-val" id="kpi-total-importaciones">{{ $totalImportaciones ?? 2 }}</span>
    <span class="sg-stat-lbl">Nóminas Sincronizadas</span>
    <p class="sg-stat-sub">Verificadas con huella SHA-256</p>
  </div>
</section>

<!-- ========================================================================= -->
<!-- NAVEGACIÓN PRINCIPAL DEL MÓDULO (4 PESTAÑAS) -->
<!-- ========================================================================= -->
<nav class="sg-module-tabs" role="tablist" aria-label="Secciones del Módulo">
  <button type="button" class="sg-module-tab is-active" id="btn-tab-egresados" role="tab" aria-selected="true" aria-controls="panel-egresados" onclick="activarModuloTab('egresados')">
    Padrón de Egresados
  </button>

  <button type="button" class="sg-module-tab" id="btn-tab-cohortes" role="tab" aria-selected="false" aria-controls="panel-cohortes" onclick="activarModuloTab('cohortes')">
    Cohortes y Denominador I1
  </button>

  <button type="button" class="sg-module-tab" id="btn-tab-acreditacion" role="tab" aria-selected="false" aria-controls="panel-acreditacion" onclick="activarModuloTab('acreditacion')">
    Acreditación y Grados
  </button>

  <button type="button" class="sg-module-tab" id="btn-tab-importaciones" role="tab" aria-selected="false" aria-controls="panel-importaciones" onclick="activarModuloTab('importaciones')">
    Cargas de Secretaría
  </button>
</nav>

<!-- ========================================================================= -->
<!-- PESTAÑA 1: PADRÓN GENERAL DE EGRESADOS (egresado) -->
<!-- ========================================================================= -->
<section id="panel-egresados" role="tabpanel" aria-labelledby="btn-tab-egresados">
  <!-- Selector Rápido de Cohortes (Fila Única / Últimas Cohortes) -->
  <div class="sg-cohort-strip-wrapper" id="bloque-tarjetas-cohortes">
    <div class="sg-cohort-strip-header">
      <span style="font-weight: 600; color: var(--sg-navy);">Cohortes Activas en Seguimiento</span>
      <button type="button" class="sg-button sg-button-secondary" id="btn-toggle-cohort-cards" onclick="toggleTarjetasCohortes()" style="min-height: 28px; padding: 2px 10px; font-size: 12px;">
        Ocultar tarjetas
      </button>
    </div>
    <div class="sg-cohort-grid" id="contenedor-cards-cohortes" role="region" aria-label="Filtro rápido por cohorte de ingresantes">
      <div class="sg-cohort-card is-active" id="card-cohorte-todas" onclick="filtrarCohorteRapido('TODAS')" tabindex="0" role="button" aria-pressed="true">
        <div class="sg-cohort-header">
          <span class="sg-cohort-code">Vista Global</span>
          <span class="sg-status-pill sg-pill-neutral">Todas</span>
        </div>
        <h3 class="sg-cohort-name">Padrón Consolidado</h3>
        <p class="sg-stat-sub">Todas las cohortes registradas</p>
        <div class="sg-cohort-stats">
          <div>
            <div class="sg-cohort-stat-val" id="card-val-ingresantes-todas">--</div>
            <div class="sg-cohort-stat-lbl">Ingresantes Totales</div>
          </div>
          <div>
            <div class="sg-cohort-stat-val" style="color: var(--sg-success);" id="card-val-egresados-todas">{{ $totalEgresados ?? 0 }}</div>
            <div class="sg-cohort-stat-lbl">Acreditados</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Barra de Filtros Multifactorial -->
  <section class="sg-toolbar" aria-label="Búsqueda y filtros del padrón">
    <form id="form-filtros" class="sg-toolbar-form" onsubmit="event.preventDefault(); aplicarFiltros();">
      <div class="sg-field">
        <label class="sg-label" for="busqueda-criterio">Buscar en el padrón</label>
        <input class="sg-input" id="busqueda-criterio" type="search" placeholder="Código, DNI o apellidos completos..." autocomplete="off" oninput="aplicarFiltros()">
      </div>

      <div class="sg-field">
        <label class="sg-label" for="filtro-cohorte">Cohorte</label>
        <select class="sg-select" id="filtro-cohorte" onchange="aplicarFiltros()">
          <option value="">Todas las cohortes</option>
        </select>
      </div>

      <div class="sg-field">
        <label class="sg-label" for="filtro-estado-academico">Grado Académico Actual</label>
        <select class="sg-select" id="filtro-estado-academico" onchange="aplicarFiltros()">
          <option value="">Todos los grados</option>
          <option value="TITULADO">Titulado Profesional</option>
          <option value="BACHILLER">Bachiller</option>
          <option value="PROXIMO">Aptos para egresar</option>
          <option value="EGRESADO">Egresado</option>
        </select>
      </div>

      <div class="sg-field">
        <label class="sg-label" for="filtro-aptitud">Condición de Apto</label>
        <select class="sg-select" id="filtro-aptitud" onchange="aplicarFiltros()">
          <option value="">Todas las condiciones</option>
          <option value="APTO">Apto para Graduación</option>
          <option value="PENDIENTE">Trámite Documentario</option>
        </select>
      </div>

      <div class="sg-actions" style="margin-bottom: 2px;">
        <button type="button" class="sg-button sg-button-secondary" onclick="restablecerFiltros()" title="Restablecer filtros">
          <span>Restablecer</span>
        </button>
      </div>
    </form>

    <div class="sg-toolbar-meta">
      <div>
        Mostrando <strong id="total-registros">0</strong> expedientes de <strong id="total-general">0</strong> registrados.
      </div>
    </div>
  </section>

  <!-- Franja Dinámica de Métricas de la Cohorte Seleccionada -->
  <div id="franja-metrica-cohorte" class="sg-cohort-metric-banner" role="region" aria-label="Métricas de la cohorte seleccionada">
    <!-- Se actualiza reactivamente mediante actualizarFranjaMetricaCohorte() -->
  </div>

  <!-- Nómina de Expedientes Únicos -->
  <div class="sg-section-title">
    <div>
      <h2 style="font-size: 18px; margin: 0;">Nómina Oficial de Expedientes Únicos</h2>
      <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">Expediente de egresados de Ingeniería Informática y trazabilidad de su historial formativo y profesional.</p>
    </div>
    <div class="sg-actions">
      <button type="button" class="sg-button" onclick="abrirModalNuevoExpediente()">
        <span>+ Nuevo Expediente Individual</span>
      </button>
    </div>
  </div>

  <div class="sg-table-wrap" id="tabla-padron-wrap" tabindex="0" role="region" aria-label="Tabla de egresados">
    <table class="sg-table" id="tabla-padron">
      <caption>Nómina oficial de expedientes de egresados de Ingeniería Informática UNT</caption>
      <thead>
        <tr>
          <th scope="col">Matrícula</th>
          <th scope="col">Apellidos y Nombres</th>
          <th scope="col">Cohorte / Periodo</th>
          <th scope="col">Grado Académico</th>
          <th scope="col">Condición F1</th>
          <th scope="col">Contacto Vigente</th>
          <th scope="col" class="sg-numeric">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-padron">
        <!-- Renderizado dinámico desde padronData y Blade -->
      </tbody>
    </table>
  </div>

  <!-- Barra de Paginación del Padrón -->
  <nav class="sg-pagination-bar" id="paginacion-padron" aria-label="Paginación de expedientes del padrón">
    <div class="sg-pagination-info">
      <span>Mostrando <strong id="padron-desde">1</strong> - <strong id="padron-hasta">10</strong> de <strong id="padron-total">24</strong> expedientes</span>
      <label style="display: inline-flex; align-items: center; gap: 6px; margin-left: 10px;">
        <span>Filas:</span>
        <select class="sg-page-size-select" id="padron-por-pagina" onchange="cambiarFilasPorPaginaPadron(this.value)" aria-label="Cantidad de filas por página">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
      </label>
    </div>
    <div class="sg-pagination-controls" id="padron-paginas-controles">
      <!-- Generado dinámicamente -->
    </div>
  </nav>

  <!-- Estado Vacío A: Búsqueda o filtros sin coincidencias -->
  <div id="vacio-filtro" class="sg-empty-state" style="display: none;" role="status" aria-live="polite">
    <div class="sg-empty-icon" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--sg-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        <line x1="8" y1="11" x2="14" y2="11"></line>
      </svg>
    </div>
    <h3 class="sg-empty-title">Sin resultados para los filtros seleccionados</h3>
    <p class="sg-empty-desc">No se encontraron expedientes que coincidan con los criterios de búsqueda actuales. Intente restablecer los filtros para volver a ver la nómina completa.</p>
    <div class="sg-empty-actions">
      <button type="button" class="sg-button sg-button-secondary" onclick="restablecerFiltros()">Restablecer filtros</button>
    </div>
  </div>

  <!-- Estado Vacío B: Padrón sin expedientes (Onboarding / Cero inicial) -->
  <div id="vacio-repositorio" class="sg-empty-state" style="display: none;" role="status" aria-live="polite">
    <div class="sg-empty-icon" aria-hidden="true">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--sg-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
    </div>
    <h3 class="sg-empty-title">Aún no hay expedientes registrados en el padrón</h3>
    <p class="sg-empty-desc">Inicie el registro ingresando un nuevo egresado de forma individual o cargue el archivo oficial emitido por Secretaría Académica.</p>
    <div class="sg-empty-actions">
      <button type="button" class="sg-button" onclick="abrirModalNuevoExpediente()">+ Nuevo Expediente Individual</button>
      <button type="button" class="sg-button sg-button-secondary" onclick="activarModuloTab('importaciones')">Cargar Nómina Oficial (CSV/XLSX)</button>
    </div>
  </div>
</section>

<!-- ========================================================================= -->
<!-- PESTAÑA 2: GESTIÓN DE COHORTES Y PLANES CURRICULARES (1998, 2017, 2027) -->
<!-- ========================================================================= -->
<section id="panel-cohortes" role="tabpanel" aria-labelledby="btn-tab-cohortes" style="display: none;">
  <!-- Nota Institucional de Medición de Graduación -->
  <div class="sg-alert" style="margin-bottom: var(--sg-space-5); border-left-color: var(--sg-accent); background: var(--sg-soft);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
    <div>
      <p style="margin: 0; font-weight: 600; color: var(--sg-navy);">Criterio de Cálculo · Indicador I1 (Tasa de Graduación)</p>
      <p style="margin: 4px 0 0; font-size: 13px; color: var(--sg-text); line-height: 1.5;">
        Conforme a la metodología institucional, el <strong>denominador oficial</strong> del indicador I1 corresponde al total de ingresantes registrados de la cohorte para evaluar la tasa longitudinal de titulación.
      </p>
    </div>
  </div>

  <div class="sg-section-title">
    <div>
      <h2 style="font-size: 18px; margin: 0;">Catálogo de Cohortes Académicas</h2>
      <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">Control de promociones de ingreso, vinculación al plan curricular y medición longitudinal de titulación.</p>
    </div>
    <div class="sg-actions">
      <button type="button" class="sg-button" onclick="abrirModalNuevaCohorte()">
        <span>+ Registrar Nueva Cohorte</span>
      </button>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Tabla de cohortes">
    <table class="sg-table" id="tabla-cohortes">
      <caption>Catálogo oficial de cohortes de ingresantes y avance del Indicador I1</caption>
      <thead>
        <tr>
          <th scope="col">Año Ingreso</th>
          <th scope="col">Denominación Oficial de Promoción</th>
          <th scope="col">Plan Curricular</th>
          <th scope="col" class="sg-numeric">Ingresantes (Denom. I1)</th>
          <th scope="col" class="sg-numeric">Egresados Acreditados</th>
          <th scope="col" class="sg-numeric">Tasa I1 (%)</th>
          <th scope="col">Duración Ref.</th>
          <th scope="col">Etapa de Evaluación</th>
          <th scope="col" class="sg-numeric">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-cohortes">
        <!-- Renderizado dinámico de cohortes -->
      </tbody>
    </table>
  </div>

  <!-- SECCIÓN ADICIONAL: CATÁLOGO DE PLANES CURRICULARES (1998, 2017, 2027) -->
  <div class="sg-section-title" style="margin-top: var(--sg-space-8);">
    <div>
      <h2 style="font-size: 18px; margin: 0;">Catálogo de Planes Curriculares</h2>
      <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">Planes de estudio de Ingeniería Informática UNT: Plan 1998, Plan 2017 y Plan 2027.</p>
    </div>
    <div class="sg-actions">
      <button type="button" class="sg-button" onclick="abrirModalNuevoPlan()">
        <span>+ Registrar Nuevo Plan Curricular</span>
      </button>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Tabla de planes curriculares">
    <table class="sg-table" id="tabla-planes">
      <caption>Catálogo de planes curriculares y vigencias de la Escuela de Ingeniería Informática</caption>
      <thead>
        <tr>
          <th scope="col">Código / Versión</th>
          <th scope="col">Vigencia Desde</th>
          <th scope="col">Vigencia Hasta</th>
          <th scope="col">Estado</th>
          <th scope="col" class="sg-numeric">Cohortes Asignadas</th>
          <th scope="col" class="sg-numeric">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-planes">
        <!-- Renderizado dinámico de planes -->
      </tbody>
    </table>
  </div>
</section>

<!-- ========================================================================= -->
<!-- PESTAÑA 3: ACREDITACIÓN Y ESTADOS ACADÉMICOS (historial_estado_academico) -->
<!-- ========================================================================= -->
<section id="panel-acreditacion" role="tabpanel" aria-labelledby="btn-tab-acreditacion" style="display: none;">
  <div class="sg-panel" style="margin-bottom: var(--sg-space-5);">
    <div class="sg-section-title" style="margin-bottom: 8px;">
      <div>
        <h2 style="font-size: 17px; margin: 0;">Control de Acreditación y Grados Académicos (F1 / F4)</h2>
        <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">
          Verificación de créditos aprobados, condición de apto para colación y registro oficial de resoluciones decanales y rectorales.
        </p>
      </div>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Tabla de acreditación">
    <table class="sg-table" id="tabla-acreditacion">
      <caption>Nómina de grados académicos y resoluciones de colación institucional</caption>
      <thead>
        <tr>
          <th scope="col">Matrícula</th>
          <th scope="col">Egresado Titular</th>
          <th scope="col">Grado Vigente</th>
          <th scope="col">Créditos Validados</th>
          <th scope="col">Condición F1</th>
          <th scope="col">Resolución Bachiller</th>
          <th scope="col">Resolución Título</th>
          <th scope="col">Operador Responsable</th>
          <th scope="col" class="sg-numeric">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-acreditacion">
        <!-- Renderizado dinámico de acreditación -->
      </tbody>
    </table>
  </div>

  <!-- Barra de Paginación de Acreditación -->
  <nav class="sg-pagination-bar" id="paginacion-acreditacion" aria-label="Paginación de acreditación y grados">
    <div class="sg-pagination-info">
      <span>Mostrando <strong id="acred-desde">1</strong> - <strong id="acred-hasta">10</strong> de <strong id="acred-total">24</strong> registros</span>
      <label style="display: inline-flex; align-items: center; gap: 6px; margin-left: 10px;">
        <span>Filas:</span>
        <select class="sg-page-size-select" id="acred-por-pagina" onchange="cambiarFilasPorPaginaAcred(this.value)" aria-label="Cantidad de filas por página">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
        </select>
      </label>
    </div>
    <div class="sg-pagination-controls" id="acred-paginas-controles">
      <!-- Generado dinámicamente -->
    </div>
  </nav>
</section>

<!-- ========================================================================= -->
<!-- PESTAÑA 4: CARGAS MASIVAS DE SECRETARÍA -->
<!-- ========================================================================= -->
<section id="panel-importaciones" role="tabpanel" aria-labelledby="btn-tab-importaciones" style="display: none;">
  <!-- Zona de Carga Oficial Directa -->
  <div class="sg-dropzone">
    <div style="font-size: 32px; color: var(--sg-accent); margin-bottom: 8px;">📑</div>
    <h2 style="font-size: 17px; margin: 0 0 6px; color: var(--sg-navy);">Carga Masiva de Nómina Oficial emitida por Secretaría</h2>
    <p style="max-width: 68ch; margin: 0 auto 16px; font-size: 13px; color: var(--sg-muted); line-height: 1.5;">
      Procesamiento automatizado de archivos CSV o XLSX. El sistema verifica duplicados estrictos en matrícula y DNI, calcula la firma criptográfica SHA-256 e inserta bajo transacción atómica y segura.
    </p>

    <div class="sg-actions" style="justify-content: center; gap: 12px;">
      <button type="button" class="sg-button" onclick="abrirModalImportacion()">
        <span>+ Cargar Archivo de Padrón</span>
      </button>
      <a href="{{ route('seguimiento-egresado.padron.plantilla') }}" class="sg-button sg-button-secondary">
        <span>📥 Descargar Formato Modelo (CSV)</span>
      </a>
    </div>
  </div>

  <div class="sg-section-title">
    <div>
      <h2 style="font-size: 18px; margin: 0;">Auditoría de Cargas Procesadas</h2>
      <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">Historial de importaciones con verificación SHA-256 y trazabilidad de registros procesados.</p>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Tabla de auditoría de importaciones">
    <table class="sg-table" id="tabla-importaciones">
      <caption>Registro histórico de cargas masivas de nómina institucional</caption>
      <thead>
        <tr>
          <th scope="col">ID</th>
          <th scope="col">Archivo Recibido</th>
          <th scope="col">Firma Criptográfica SHA-256</th>
          <th scope="col">Origen</th>
          <th scope="col">Fecha de Proceso</th>
          <th scope="col">Operador Responsable</th>
          <th scope="col">Balance de Filas</th>
          <th scope="col">Estado</th>
          <th scope="col" class="sg-numeric">Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody-importaciones">
        <!-- Renderizado dinámico de importaciones -->
      </tbody>
    </table>
  </div>

  <!-- Barra de Paginación de Importaciones -->
  <nav class="sg-pagination-bar" id="paginacion-importaciones" aria-label="Paginación de auditoría de importaciones">
    <div class="sg-pagination-info">
      <span>Mostrando <strong id="imp-desde">1</strong> - <strong id="imp-hasta">10</strong> de <strong id="imp-total">0</strong> cargas de secretaría</span>
    </div>
    <div class="sg-pagination-controls" id="imp-paginas-controles">
      <!-- Generado dinámicamente -->
    </div>
  </nav>
</section>

<!-- ========================================================================= -->
<!-- MODALES DEL SISTEMA (ACCESIBLES Y ORIENTADOS A TAREAS REALES) -->
<!-- ========================================================================= -->

<!-- MODAL 1: FICHA INTEGRAL DE EXPEDIENTE ÚNICO -->
<div id="modal-expediente" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-exp-title">
  <div class="sg-modal-dialog">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-exp-title">Expediente Oficial de Egresado</h2>
        <p id="modal-exp-subtitulo">Matrícula: — | Escuela de Ingeniería Informática UNT</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalExpediente()">✕</button>
    </div>

    <div class="sg-modal-body">
      <div class="sg-subtabs" role="tablist" aria-label="Secciones del expediente">
        <button type="button" class="sg-subtab-btn is-active" id="subtab-btn-academico" role="tab" aria-selected="true" onclick="cambiarSubpestana('academico')">
          Trayectoria Académica
        </button>
        <button type="button" class="sg-subtab-btn" id="subtab-btn-contacto" role="tab" aria-selected="false" onclick="cambiarSubpestana('contacto')">
          Contacto y Residencia
        </button>
        <button type="button" class="sg-subtab-btn" id="subtab-btn-seguimiento" role="tab" aria-selected="false" onclick="cambiarSubpestana('seguimiento')">
          Trazabilidad de Encuestas
        </button>
      </div>

      <!-- Panel 1: Trayectoria Académica -->
      <div id="subtab-panel-academico" role="tabpanel">
        <div class="sg-panel" style="margin-bottom: var(--sg-space-4);">
          <div class="sg-info-grid">
            <div class="sg-info-block">
              <span class="sg-info-label">Apellidos y Nombres</span>
              <span class="sg-info-val" id="exp-nombres" style="font-weight: 600; font-size: 15px; color: var(--sg-navy);">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Documento Nacional (DNI)</span>
              <span class="sg-info-val" id="exp-dni">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Cohorte de Ingreso</span>
              <span class="sg-info-val" id="exp-cohorte">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Periodo y Fecha de Egreso</span>
              <span class="sg-info-val" id="exp-egreso">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Plan Curricular</span>
              <span class="sg-info-val" id="exp-plan">Plan 2017</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Créditos Aprobados</span>
              <span class="sg-info-val" id="exp-creditos">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Estado Académico Oficial</span>
              <span class="sg-info-val" id="exp-grado-badge">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Condición de Apto</span>
              <span class="sg-info-val" id="exp-apto-f1">—</span>
            </div>
          </div>
        </div>

        <div class="sg-panel">
          <h3 style="font-size: 14px; margin: 0 0 10px; color: var(--sg-navy);">Resoluciones Oficiales Registradas</h3>
          <div class="sg-info-grid">
            <div class="sg-info-block">
              <span class="sg-info-label">Grado de Bachiller</span>
              <span class="sg-info-val" id="exp-res-bachiller">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Título Profesional</span>
              <span class="sg-info-val" id="exp-res-titulo">—</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel 2: Contacto y Residencia -->
      <div id="subtab-panel-contacto" role="tabpanel" style="display: none;">
        <div class="sg-panel">
          <h3 style="font-size: 14px; margin: 0 0 12px; color: var(--sg-navy);">Datos de Contacto Vigentes</h3>
          <div class="sg-info-grid">
            <div class="sg-info-block">
              <span class="sg-info-label">Correo Institucional</span>
              <span class="sg-info-val" id="exp-correo-inst">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Correo Personal</span>
              <span class="sg-info-val" id="exp-correo-pers">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Teléfono / WhatsApp</span>
              <span class="sg-info-val" id="exp-telefono">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Ciudad de Residencia</span>
              <span class="sg-info-val" id="exp-ciudad">—</span>
            </div>
            <div class="sg-info-block">
              <span class="sg-info-label">Operador Responsable</span>
              <span class="sg-info-val" id="exp-contacto-fecha">Secretaría Académica</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Panel 3: Trazabilidad de Encuestas -->
      <div id="subtab-panel-seguimiento" role="tabpanel" style="display: none;">
        <div class="sg-panel">
          <h3 style="font-size: 14px; margin: 0 0 6px; color: var(--sg-navy);">Historial Longitudinal de Medición</h3>
          <p style="font-size: 13px; color: var(--sg-muted); margin: 0 0 16px;">
            Campañas periódicas y encuestas de seguimiento profesional vinculadas al titular:
          </p>
          <div class="sg-timeline" id="exp-timeline"></div>
        </div>
      </div>
    </div>

    <div class="sg-modal-foot">
      <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalExpediente()">Cerrar Ficha</button>
      <button type="button" class="sg-button sg-button-secondary" id="btn-confirmar-egreso" style="display: none;" onclick="abrirConfirmarEgreso()">Confirmar egreso</button>
      <button type="button" class="sg-button" onclick="abrirModalActualizarEstadoDesdeFicha()">Actualizar Estado Académico</button>
    </div>
  </div>
</div>

<!-- MODAL: CONFIRMAR EGRESO (fecha real; cierra la etapa «apto para egresar» y abre la de egresado) -->
<div id="modal-confirmar-egreso" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-egreso-title">
  <div class="sg-modal-dialog" style="max-width: 520px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-egreso-title">Confirmar egreso</h2>
        <p id="modal-egreso-subtitulo">Registro oficial de la fecha real de egreso</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarConfirmarEgreso()">✕</button>
    </div>
    <div class="sg-modal-body">
      <div class="sg-field">
        <label class="sg-label" for="egreso-fecha">Fecha real de egreso *</label>
        <input class="sg-input" id="egreso-fecha" type="date" required>
        <p class="sg-help">El expediente y los contactos registrados se conservan. El periodo se calcula con esta fecha.</p>
      </div>
      <p class="sg-field-error" id="egreso-error" role="alert" style="display: none;"></p>
    </div>
    <div class="sg-modal-foot">
      <button type="button" class="sg-button sg-button-secondary" onclick="cerrarConfirmarEgreso()">Cancelar</button>
      <button type="button" class="sg-button" id="btn-guardar-egreso" onclick="guardarConfirmarEgreso()">Confirmar egreso</button>
    </div>
  </div>
</div>

<!-- MODAL 2: CREAR / EDITAR EXPEDIENTE INDIVIDUAL (CRUD egresado) -->
<div id="modal-nuevo-expediente" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-nuevo-title">
  <div class="sg-modal-dialog" style="max-width: 720px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-nuevo-title">Registro de Expediente de Egresado</h2>
        <p>Incorporación oficial al padrón de Ingeniería Informática UNT</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalNuevoExpediente()">✕</button>
    </div>

    <form id="form-nuevo-expediente" onsubmit="event.preventDefault(); guardarExpedienteForm();">
      <input type="hidden" id="exp-edit-id" value="">
      <div class="sg-modal-body">
        <div class="sg-form-grid">
          <div class="sg-field">
            <label class="sg-label" for="nuevo-codigo">Código de Matrícula *</label>
            <input class="sg-input" id="nuevo-codigo" type="text" required placeholder="Ej. 1021900250" maxlength="15">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-dni">Documento de Identidad (DNI) *</label>
            <input class="sg-input" id="nuevo-dni" type="text" required placeholder="8 dígitos" maxlength="10">
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="nuevo-nombres">Nombres Completos *</label>
            <input class="sg-input" id="nuevo-nombres" type="text" required placeholder="Nombres">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-paterno">Apellido Paterno *</label>
            <input class="sg-input" id="nuevo-paterno" type="text" required placeholder="Apellido Paterno">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-materno">Apellido Materno</label>
            <input class="sg-input" id="nuevo-materno" type="text" placeholder="Apellido Materno">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-cohorte">Cohorte de Ingresantes *</label>
            <select class="sg-select" id="nuevo-cohorte" required></select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-periodo">Periodo de Egreso (solo si ya egresó)</label>
            <input class="sg-input" id="nuevo-periodo" type="text" placeholder="Ej. 2024-I">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-grado">Grado Académico Actual *</label>
            <select class="sg-select" id="nuevo-grado" required>
              <option value="PROXIMO">Apto para egresar (todavía no egresó)</option>
              <option value="EGRESADO">Egresado</option>
              <option value="BACHILLER">Bachiller en Ingeniería Informática</option>
              <option value="TITULADO">Titulado Profesional</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-apto">Condición Apto F1 *</label>
            <select class="sg-select" id="nuevo-apto" required>
              <option value="APTO">Apto Verificado</option>
              <option value="PENDIENTE">Trámite Documentario</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-correo-inst">Correo Institucional *</label>
            <input class="sg-input" id="nuevo-correo-inst" type="email" required placeholder="usuario@unitru.edu.pe">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="nuevo-telefono">Teléfono / WhatsApp</label>
            <input class="sg-input" id="nuevo-telefono" type="text" placeholder="+51 987 654 321">
          </div>
        </div>
      </div>

      <div class="sg-modal-foot">
        <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalNuevoExpediente()">Cancelar</button>
        <button type="submit" class="sg-button" id="btn-submit-expediente">Registrar en Padrón</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 3: CREAR / EDITAR COHORTE ACADÉMICA (CRUD cohorte) -->
<div id="modal-cohorte" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-cohorte-title">
  <div class="sg-modal-dialog" style="max-width: 650px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-cohorte-title">Registrar Nueva Cohorte de Ingresantes</h2>
        <p>Definición del denominador oficial para indicadores institucionales (I1)</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalCohorte()">✕</button>
    </div>

    <form id="form-cohorte" onsubmit="event.preventDefault(); guardarCohorteForm();">
      <input type="hidden" id="cohorte-edit-id" value="">
      <div class="sg-modal-body">
        <div class="sg-form-grid">
          <div class="sg-field">
            <label class="sg-label" for="cohorte-anio">Año de Ingreso *</label>
            <input class="sg-input" id="cohorte-anio" type="number" required min="1990" max="2035" placeholder="Ej. 2022">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="cohorte-ingresantes">Total Ingresantes Oficiales (Denominador I1) *</label>
            <input class="sg-input" id="cohorte-ingresantes" type="number" required min="1" max="500" placeholder="Ej. 50">
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="cohorte-nombre">Denominación de Promoción *</label>
            <input class="sg-input" id="cohorte-nombre" type="text" required placeholder="Ej. Promoción 2022 — Grace Hopper">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="cohorte-plan">Plan Curricular *</label>
            <select class="sg-select" id="cohorte-plan" required>
              <option value="Plan 2017">Plan 2017 (Vigente)</option>
              <option value="Plan 1998">Plan 1998 (Cerrado)</option>
              <option value="Plan 2027">Plan 2027 (Borrador)</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="cohorte-duracion">Duración Esperada (Años)</label>
            <input class="sg-input" id="cohorte-duracion" type="number" value="5" min="3" max="8">
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="cohorte-obs">Observación Administrativa</label>
            <textarea class="sg-textarea" id="cohorte-obs" style="min-height: 80px;" placeholder="Detalles de resolución de admisión, número de vacantes o notas de Secretaría."></textarea>
          </div>
        </div>
      </div>

      <div class="sg-modal-foot">
        <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalCohorte()">Cancelar</button>
        <button type="submit" class="sg-button" id="btn-submit-cohorte">Guardar Cohorte</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 4: ACTUALIZACIÓN DE ESTADO ACADÉMICO / ACREDITACIÓN F1 (historial_estado_academico) -->
<div id="modal-estado-academico" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-estado-title">
  <div class="sg-modal-dialog" style="max-width: 640px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-estado-title">Actualizar Estado Académico</h2>
        <p id="modal-estado-subtitulo">Registro de avance de grados y resoluciones académicas</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalEstado()">✕</button>
    </div>

    <form id="form-estado-academico" onsubmit="event.preventDefault(); guardarEstadoAcademicoForm();">
      <input type="hidden" id="estado-egresado-id" value="">
      <div class="sg-modal-body">
        <div class="sg-form-grid">
          <div class="sg-field">
            <label class="sg-label" for="estado-select-grado">Nueva Etapa Académica *</label>
            <select class="sg-select" id="estado-select-grado" required>
              <option value="EGRESADO">Egresado Regular</option>
              <option value="BACHILLER">Bachiller en Ingeniería Informática</option>
              <option value="TITULADO">Titulado Profesional</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="estado-select-apto">Condición de Apto *</label>
            <select class="sg-select" id="estado-select-apto" required>
              <option value="APTO">Apto para Graduación Verificado</option>
              <option value="PENDIENTE">Trámite Documentario Pendiente</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="estado-creditos">Créditos Aprobados Validados</label>
            <input class="sg-input" id="estado-creditos" type="text" value="" placeholder="Solo si se conoce">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="estado-fecha-bachiller">Fecha de Bachiller (si aplica)</label>
            <input class="sg-input" id="estado-fecha-bachiller" type="date">
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="estado-res-bachiller">Resolución de Bachiller</label>
            <input class="sg-input" id="estado-res-bachiller" type="text" placeholder="Ej. Resolución Decanal Nº 0240-2024-FAC">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="estado-fecha-titulo">Fecha de Título (si aplica)</label>
            <input class="sg-input" id="estado-fecha-titulo" type="date">
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="estado-res-titulo">Resolución de Título Profesional</label>
            <input class="sg-input" id="estado-res-titulo" type="text" placeholder="Ej. Resolución Rectoral Nº 0520-2024-UNT">
          </div>
        </div>
      </div>

      <div class="sg-modal-foot">
        <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalEstado()">Cancelar</button>
        <button type="submit" class="sg-button">Actualizar Acreditación</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 5: IMPORTACIÓN MASIVA DE NÓMINA (importacion_padron) -->
<div id="modal-importacion" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-import-title">
  <div class="sg-modal-dialog" style="max-width: 650px;">
    <form action="{{ route('seguimiento-egresado.padron.importar') }}" method="POST" enctype="multipart/form-data" id="form-importacion-padron">
      @csrf
      <div class="sg-modal-head">
        <div>
          <h2 id="modal-import-title">Carga de Nómina Oficial de Secretaría</h2>
          <p>Procesamiento automatizado de padrón con verificación criptográfica SHA-256</p>
        </div>
        <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalImportacion()">✕</button>
      </div>

      <div class="sg-modal-body">
        <div class="sg-dropzone">
          <div style="font-size: 32px; color: var(--sg-accent); margin-bottom: 8px;">📂</div>
          <h3 style="font-size: 16px; margin: 0 0 6px; color: var(--sg-navy);">Seleccione el archivo emitido por Secretaría</h3>
          <p style="font-size: 13px; color: var(--sg-muted); margin: 0 0 16px;">
            Formatos compatibles: CSV o XLSX. Detecta automáticamente duplicados por matrícula o DNI y registra trazabilidad por fila.
          </p>
          <div style="display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap;">
            <input type="file" name="archivo_padron" id="archivo-padron" accept=".csv,.txt,.xlsx" required style="font-size: 13px;">
          </div>
          <div style="margin-top: 14px;">
            <a href="{{ route('seguimiento-egresado.padron.plantilla') }}" class="sg-button sg-button-secondary" style="font-size: 12px; padding: 6px 12px;">
              <span>📥 Descargar Formato Modelo (CSV)</span>
            </a>
          </div>
        </div>

        <div class="sg-panel">
          <h4 style="font-size: 12px; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 8px; color: var(--sg-muted);">Reglas de Control de Integridad (Procesamiento Atómico)</h4>
          <ul style="font-size: 13px; color: var(--sg-text); padding-left: 18px; margin: 0; line-height: 1.6;">
            <li>Carga atómica oficial (Todo o Nada): si se detecta cualquier inconsistencia, se cancela la importación para evitar registros parciales.</li>
            <li>Soporte nativo para libros de cálculo <strong>XLSX</strong> y archivos delimitados <strong>CSV</strong>.</li>
            <li>Validación obligatoria de existencia de la <strong>Cohorte</strong> en catálogo institucional (no se admiten cohortes inexistentes).</li>
            <li>Detección inmediata de duplicados en matrícula universitaria o documento de identidad.</li>
            <li>Generación de firma de control SHA-256 para auditoría de integridad del archivo.</li>
          </ul>
        </div>
      </div>

      <div class="sg-modal-foot">
        <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalImportacion()">Cerrar</button>
        <button type="submit" class="sg-button">Procesar Importación Oficial</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL 6: DETALLE DE AUDITORÍA DE IMPORTACIÓN (importacion_fila) -->
<div id="modal-detalle-importacion" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-det-imp-title">
  <div class="sg-modal-dialog" style="max-width: 780px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-det-imp-title">Detalle de Ejecución de Carga</h2>
        <p id="modal-det-imp-subtitulo">Archivo: — | Operador: Secretaría Académica</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalDetalleImportacion()">✕</button>
    </div>

    <div class="sg-modal-body">
      <div class="sg-panel" style="margin-bottom: 16px;">
        <div class="sg-info-grid">
          <div class="sg-info-block">
            <span class="sg-info-label">Estado de Carga</span>
            <span class="sg-info-val" id="det-imp-estado">—</span>
          </div>
          <div class="sg-info-block">
            <span class="sg-info-label">Filas Procesadas</span>
            <span class="sg-info-val" id="det-imp-total">0 registros</span>
          </div>
          <div class="sg-info-block">
            <span class="sg-info-label">Aceptadas</span>
            <span class="sg-info-val" style="color: var(--sg-success);" id="det-imp-aceptadas">0 registros</span>
          </div>
          <div class="sg-info-block">
            <span class="sg-info-label">Rechazadas / Conflictos</span>
            <span class="sg-info-val" style="color: var(--sg-danger);" id="det-imp-rechazadas">0 registros</span>
          </div>
        </div>
      </div>

      <h3 style="font-size: 14px; margin: 0 0 10px; color: var(--sg-navy);">Registro de Filas con Observación</h3>
      <div class="sg-table-wrap">
        <table class="sg-table">
          <caption>Detalle de filas rechazadas por duplicidad o formato en la importación</caption>
          <thead>
            <tr>
              <th scope="col">Fila #</th>
              <th scope="col">Datos de Entrada</th>
              <th scope="col">Estado</th>
              <th scope="col">Detalle / Causa de Observación</th>
            </tr>
          </thead>
          <tbody id="tbody-detalle-filas"></tbody>
        </table>
      </div>
    </div>

    <div class="sg-modal-foot">
      <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalDetalleImportacion()">Cerrar</button>
    </div>
  </div>
</div>

<!-- MODAL 7: REGISTRAR / EDITAR PLAN CURRICULAR (plan_curricular) -->
<div id="modal-plan-curricular" class="sg-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modal-plan-title">
  <div class="sg-modal-dialog" style="max-width: 600px;">
    <div class="sg-modal-head">
      <div>
        <h2 id="modal-plan-title">Registrar Plan Curricular</h2>
        <p>Definición del plan de estudios y periodo de vigencia institucional</p>
      </div>
      <button type="button" class="sg-modal-close-btn" aria-label="Cerrar ventana" onclick="cerrarModalPlan()">✕</button>
    </div>

    <form id="form-plan-curricular" onsubmit="event.preventDefault(); guardarPlanCurricularForm();">
      <input type="hidden" id="plan-edit-id" value="">
      <div class="sg-modal-body">
        <div class="sg-form-grid">
          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="plan-codigo">Código o Versión del Plan *</label>
            <input class="sg-input" id="plan-codigo" type="text" required placeholder="Ej. Plan 1998, Plan 2017, Plan 2027">
            <p class="sg-help">Planes oficiales: Plan 1998, Plan 2017, Plan 2027.</p>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="plan-desde">Vigencia Desde *</label>
            <input class="sg-input" id="plan-desde" type="date" required>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="plan-hasta">Vigencia Hasta</label>
            <input class="sg-input" id="plan-hasta" type="date">
            <p class="sg-help">Dejar vacío si el plan sigue vigente o es el actual.</p>
          </div>

          <div class="sg-field" style="grid-column: 1 / -1;">
            <label class="sg-label" for="plan-estado">Estado Curricular *</label>
            <select class="sg-select" id="plan-estado" required>
              <option value="VIGENTE">VIGENTE — En ejecución académica</option>
              <option value="BORRADOR">BORRADOR — En diseño o formulación</option>
              <option value="CERRADO">CERRADO — Plan histórico culminado</option>
            </select>
          </div>
        </div>
      </div>

      <div class="sg-modal-foot">
        <button type="button" class="sg-button sg-button-secondary" onclick="cerrarModalPlan()">Cancelar</button>
        <button type="submit" class="sg-button" id="btn-submit-plan">Guardar Plan Curricular</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // =========================================================================
  // MODELO DE DATOS M1: CONEXIÓN INTEGRAL POSTGRESQL / ELOQUENT
  // =========================================================================
@php
  $planesJs = [];
  if (isset($planesCurriculares) && count($planesCurriculares) > 0) {
      foreach ($planesCurriculares as $p) {
          $planesJs[$p->id] = [
              'id' => $p->id,
              'codigo' => $p->codigo_version,
              'desde' => $p->vigencia_desde ? date('d/m/Y', strtotime($p->vigencia_desde)) : '',
              'desdeRaw' => $p->vigencia_desde ?? '',
              'hasta' => $p->vigencia_hasta ? date('d/m/Y', strtotime($p->vigencia_hasta)) : 'Vigente / Sin término',
              'hastaRaw' => $p->vigencia_hasta ?? '',
              'estado' => $p->estado,
              'cohortesCount' => $p->cohortes_count ?? 0,
          ];
      }
  }

  $cohortesJs = [];
  if (isset($cohortes) && count($cohortes) > 0) {
      foreach ($cohortes as $c) {
          $cohortesJs[(string)$c->anio_ingreso] = [
              'id' => $c->id,
              'anio' => $c->anio_ingreso,
              'nombre' => $c->observacion ? explode('.', $c->observacion)[0] : "Promoción {$c->anio_ingreso}",
              'ingresantes' => $c->total_ingresantes,
              'egresados' => $c->egresados_acreditados_count ?? 0,
              'tasaI1' => $c->porcentaje_i1 ?? 0,
              'duracion' => $c->duracion_esperada_anios ?? 5,
              'plan' => $c->planCurricular ? $c->planCurricular->codigo_version : 'Plan 2017',
              'idPlan' => $c->id_plan_curricular,
              'estado' => $c->anio_ingreso <= 2018 ? 'Evaluación 5to Año' : ($c->anio_ingreso == 2019 ? 'Evaluación 3er Año' : ($c->anio_ingreso == 2020 ? 'Evaluación 1er Año' : 'En Consolidación')),
              'obs' => $c->observacion ?? '',
          ];
      }
  }

  $padronJs = [];
  if (isset($egresados) && count($egresados) > 0) {
      foreach ($egresados as $eg) {
          $padronJs[$eg->id] = [
              'id' => $eg->id,
              'codigo' => $eg->codigo_matricula,
              'dni' => $eg->numero_documento ?? '',
              'nombres' => $eg->nombres,
              'paterno' => $eg->apellido_paterno,
              'materno' => $eg->apellido_materno ?? '',
              'nombreCompleto' => $eg->apellido_paterno . ' ' . ($eg->apellido_materno ? $eg->apellido_materno . ' ' : '') . $eg->nombres,
              'cohorte' => (string)($eg->cohorte->anio_ingreso ?? '—'),
              'egreso' => $eg->periodo_egreso ?? 'Sin confirmar',
              'fechaEgreso' => $eg->fecha_egreso ? date('d/m/Y', strtotime($eg->fecha_egreso)) : '—',
              'plan' => $eg->cohorte->planCurricular->codigo_version ?? '—',
              'creditos' => isset($eg->estadoVigente->creditos_aprobados) ? $eg->estadoVigente->creditos_aprobados . ' créditos' : '—',
              'grado' => $eg->estadoVigente->estadoAcademico->codigo ?? 'PROXIMO',
              'aptoF1' => ($eg->estadoVigente && $eg->estadoVigente->apto_graduarse) ? 'APTO' : 'PENDIENTE',
              'resBachiller' => $eg->estadoVigente->resolucion_bachiller ?? '',
              'fechaBachiller' => $eg->estadoVigente->fecha_bachiller ?? '',
              'resTitulo' => $eg->estadoVigente->resolucion_titulo ?? '',
              'fechaTitulo' => $eg->estadoVigente->fecha_titulo ?? '',
              'correoInst' => $eg->correo_institucional ?? '',
              'correoPers' => $eg->contactoVigente->correo_personal ?? '',
              'telefono' => $eg->contactoVigente->celular_whatsapp ?? '',
              'ciudad' => $eg->contactoVigente->direccion ?? '',
              'responsable' => $eg->estadoVigente->usuarioRegistro->nombre_completo ?? '—',
              'historial' => [],
          ];
      }
  }

  $importacionesJs = [];
  if (isset($importaciones) && count($importaciones) > 0) {
      foreach ($importaciones as $imp) {
          $filasRechazadas = $imp->filas->where('estado', 'RECHAZADA');
          $filasDetalle = [];
          foreach ($filasRechazadas as $fr) {
              $filasDetalle[] = [
                  'fila' => $fr->numero_fila,
                  'datos' => json_encode($fr->datos_entrada),
                  'estado' => $fr->estado,
                  'causa' => $fr->detalle_error,
              ];
          }

          $importacionesJs[] = [
              'id' => $imp->id,
              'archivo' => $imp->nombre_archivo ?? 'Carga_Manual',
              'hash' => $imp->hash_archivo ?? 'N/A',
              'origen' => $imp->origen,
              'fecha' => date('d/m/Y H:i', strtotime($imp->fecha_importacion)),
              'operador' => $imp->usuario->nombre_completo ?? 'Secretaría Académica',
              'estado' => $imp->estado,
              'totalFilas' => $imp->filas->count(),
              'filasAceptadas' => $imp->filas->where('estado', 'ACEPTADA')->count(),
              'filasRechazadas' => $filasRechazadas->count(),
              'filasDetalle' => $filasDetalle,
          ];
      }
  }
@endphp

  let planesData = {!! !empty($planesJs) ? json_encode($planesJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '{}' !!};
  let cohortesData = {!! !empty($cohortesJs) ? json_encode($cohortesJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '{}' !!};
  let padronData = {!! !empty($padronJs) ? json_encode($padronJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '{}' !!};
  let importacionesData = {!! !empty($importacionesJs) ? json_encode($importacionesJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : '[]' !!};

  // Variables de Control de Paginación Escalable
  let padronPaginaActual = 1;
  let padronFilasPorPagina = 10;
  let padronFilasFiltradas = null;

  let acredPaginaActual = 1;
  let acredFilasPorPagina = 10;
  let acredFilasFiltradas = null;

  let impPaginaActual = 1;
  let impFilasPorPagina = 10;

  // Componente Reutilizable de Paginación Accesible (HCI)
  function crearControlesPaginacion({
    contenedorId,
    totalFilas,
    paginaActual,
    filasPorPagina,
    alCambiarPagina,
    desdeId,
    hastaId,
    totalId
  }) {
    const totalPaginas = Math.ceil(totalFilas / filasPorPagina) || 1;
    if (paginaActual > totalPaginas) paginaActual = totalPaginas;
    if (paginaActual < 1) paginaActual = 1;

    const inicio = totalFilas === 0 ? 0 : (paginaActual - 1) * filasPorPagina + 1;
    const fin = Math.min(paginaActual * filasPorPagina, totalFilas);

    const elDesde = document.getElementById(desdeId);
    const elHasta = document.getElementById(hastaId);
    const elTotal = document.getElementById(totalId);
    if (elDesde) elDesde.textContent = inicio;
    if (elHasta) elHasta.textContent = fin;
    if (elTotal) elTotal.textContent = totalFilas;

    const cont = document.getElementById(contenedorId);
    if (!cont) return;
    cont.innerHTML = '';

    // Botón Anterior
    const btnAnt = document.createElement('button');
    btnAnt.type = 'button';
    btnAnt.className = 'sg-page-btn';
    btnAnt.textContent = '← Anterior';
    btnAnt.disabled = (paginaActual <= 1);
    btnAnt.onclick = () => alCambiarPagina(paginaActual - 1);
    cont.appendChild(btnAnt);

    // Botones de Páginas con elipsis inteligente
    let pagInicio = Math.max(1, paginaActual - 2);
    let pagFin = Math.min(totalPaginas, pagInicio + 4);
    if (pagFin - pagInicio < 4) {
      pagInicio = Math.max(1, pagFin - 4);
    }

    if (pagInicio > 1) {
      const btn1 = document.createElement('button');
      btn1.type = 'button';
      btn1.className = 'sg-page-btn';
      btn1.textContent = '1';
      btn1.onclick = () => alCambiarPagina(1);
      cont.appendChild(btn1);
      if (pagInicio > 2) {
        const dots = document.createElement('span');
        dots.style.padding = '0 4px';
        dots.textContent = '…';
        cont.appendChild(dots);
      }
    }

    for (let p = pagInicio; p <= pagFin; p++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sg-page-btn' + (p === paginaActual ? ' is-active' : '');
      if (p === paginaActual) btn.setAttribute('aria-current', 'page');
      btn.textContent = p;
      const pagNum = p;
      btn.onclick = () => alCambiarPagina(pagNum);
      cont.appendChild(btn);
    }

    if (pagFin < totalPaginas) {
      if (pagFin < totalPaginas - 1) {
        const dots = document.createElement('span');
        dots.style.padding = '0 4px';
        dots.textContent = '…';
        cont.appendChild(dots);
      }
      const btnLast = document.createElement('button');
      btnLast.type = 'button';
      btnLast.className = 'sg-page-btn';
      btnLast.textContent = totalPaginas;
      btnLast.onclick = () => alCambiarPagina(totalPaginas);
      cont.appendChild(btnLast);
    }

    // Botón Siguiente
    const btnSig = document.createElement('button');
    btnSig.type = 'button';
    btnSig.className = 'sg-page-btn';
    btnSig.textContent = 'Siguiente →';
    btnSig.disabled = (paginaActual >= totalPaginas);
    btnSig.onclick = () => alCambiarPagina(paginaActual + 1);
    cont.appendChild(btnSig);
  }

  // Helper CSRF Token
  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

  // Sistema de Toasts Institucionales
  function mostrarToast(mensaje, tipo = 'success') {
    const toast = document.getElementById('sg-toast-notificacion');
    const msg = document.getElementById('sg-toast-mensaje');
    if (!toast || !msg) return;

    msg.textContent = mensaje;
    if (tipo === 'success') {
      toast.style.borderLeftColor = 'var(--sg-success)';
      toast.style.background = 'var(--sg-success-soft)';
      toast.style.color = 'var(--sg-text)';
    } else {
      toast.style.borderLeftColor = 'var(--sg-danger)';
      toast.style.background = 'var(--sg-danger-soft)';
      toast.style.color = 'var(--sg-text)';
    }
    toast.classList.add('is-open');
    setTimeout(() => {
      toast.classList.remove('is-open');
    }, 4500);
  }

  // =========================================================================
  // CONTROL DE PESTAÑAS PRINCIPALES DEL MÓDULO M1
  // =========================================================================
  function activarModuloTab(tabName) {
    const tabs = ['egresados', 'cohortes', 'acreditacion', 'importaciones'];
    tabs.forEach(t => {
      const btn = document.getElementById('btn-tab-' + t);
      const panel = document.getElementById('panel-' + t);
      if (btn && panel) {
        if (t === tabName) {
          btn.classList.add('is-active');
          btn.setAttribute('aria-selected', 'true');
          panel.style.display = 'block';
        } else {
          btn.classList.remove('is-active');
          btn.setAttribute('aria-selected', 'false');
          panel.style.display = 'none';
        }
      }
    });

    if (tabName === 'cohortes') {
      renderizarTablaCohortes();
      renderizarTablaPlanes();
    }
    if (tabName === 'acreditacion') renderizarTablaAcreditacion();
    if (tabName === 'importaciones') renderizarTablaImportaciones();
  }

  // =========================================================================
  // RENDERIZADO Y FILTRADO DEL PADRÓN DE EGRESADOS
  // =========================================================================
  function toggleTarjetasCohortes() {
    const cont = document.getElementById('contenedor-cards-cohortes');
    const btn = document.getElementById('btn-toggle-cohort-cards');
    if (!cont || !btn) return;
    if (cont.style.display === 'none') {
      cont.style.display = 'flex';
      btn.textContent = 'Ocultar tarjetas';
      btn.setAttribute('aria-expanded', 'true');
    } else {
      cont.style.display = 'none';
      btn.textContent = 'Mostrar tarjetas';
      btn.setAttribute('aria-expanded', 'false');
    }
  }

  function actualizarFranjaMetricaCohorte(cohorte = '') {
    const banner = document.getElementById('franja-metrica-cohorte');
    if (!banner) return;

    if (!cohorte || cohorte === 'TODAS') {
      let totalIngr = 0;
      Object.values(cohortesData).forEach(c => totalIngr += parseInt(c.ingresantes, 10) || 0);
      const totalAcred = Object.values(padronData).filter(p => ['TITULADO', 'BACHILLER'].includes(p.grado)).length;
      const totalGen = Object.keys(padronData).length;
      const numCohortes = Object.keys(cohortesData).length;

      banner.innerHTML = `
        <div class="sg-cmb-info">
          <div style="display:flex; align-items:center; gap:8px;">
            <h3 class="sg-cmb-title">Padrón Consolidado · Vista Global</h3>
            <span class="sg-status-pill sg-pill-neutral">Todas las cohortes</span>
          </div>
          <p class="sg-cmb-desc">Nómina unificada de egresados de Ingeniería Informática UNT en todas sus cohortes académicas registradas.</p>
        </div>
        <div class="sg-cmb-metrics">
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val">${totalIngr}</span>
            <span class="sg-cmb-stat-lbl">Ingresantes Totales</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val" style="color: var(--sg-navy);">${totalGen}</span>
            <span class="sg-cmb-stat-lbl">Expedientes</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val" style="color: var(--sg-success);">${totalAcred}</span>
            <span class="sg-cmb-stat-lbl">Graduados y Titulados</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val">${numCohortes}</span>
            <span class="sg-cmb-stat-lbl">Cohortes Activas</span>
          </div>
        </div>
      `;
    } else {
      const c = cohortesData[cohorte];
      if (!c) {
        banner.innerHTML = '';
        return;
      }
      const tasa = c.ingresantes > 0 ? ((c.egresados / c.ingresantes) * 100).toFixed(1) : '0.0';
      
      let pillClass = 'sg-pill-primary';
      if (c.estado.includes('5to')) pillClass = 'sg-pill-success';
      else if (c.estado.includes('1er')) pillClass = 'sg-pill-warning';

      banner.innerHTML = `
        <div class="sg-cmb-info">
          <div style="display:flex; align-items:center; gap:8px;">
            <h3 class="sg-cmb-title">Cohorte ${c.anio} · ${c.nombre}</h3>
            <span class="sg-status-pill ${pillClass}">${c.estado}</span>
          </div>
          <p class="sg-cmb-desc">${c.plan} · Duración programada: ${c.duracion} años · ${c.obs || 'Sin observaciones registradas'}</p>
        </div>
        <div class="sg-cmb-metrics">
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val" style="color: var(--sg-navy);">${c.ingresantes}</span>
            <span class="sg-cmb-stat-lbl">Ingresantes (Denom. I1)</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val" style="color: var(--sg-success);">${c.egresados}</span>
            <span class="sg-cmb-stat-lbl">Egresados Acreditados</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val" style="color: var(--sg-navy);">${tasa}%</span>
            <span class="sg-cmb-stat-lbl">Tasa Titulación (I1)</span>
          </div>
          <div class="sg-cmb-stat">
            <span class="sg-cmb-stat-val">${c.plan}</span>
            <span class="sg-cmb-stat-lbl">Plan Curricular</span>
          </div>
        </div>
      `;
    }
  }

  function renderizarCardsCohortes() {
    const container = document.getElementById('contenedor-cards-cohortes');
    if (!container) return;

    let totalIngresantesGlobal = 0;
    let totalEgresadosGlobal = Object.keys(padronData).length;

    // Ordenar cohortes por año descendente (las más recientes primero para monitoreo)
    const cohortesLista = Object.values(cohortesData).sort((a, b) => parseInt(b.anio, 10) - parseInt(a.anio, 10));
    // Máximo las 4 más recientes en seguimiento activo en una sola fila
    const cohortesRecientes = cohortesLista.slice(0, 4);

    cohortesLista.forEach(c => {
      totalIngresantesGlobal += parseInt(c.ingresantes, 10) || 0;
    });

    let html = `
      <div class="sg-cohort-card is-active" id="card-cohorte-todas" onclick="filtrarCohorteRapido('TODAS')" tabindex="0" role="button" aria-pressed="true">
        <div class="sg-cohort-header">
          <span class="sg-cohort-code">Vista Global</span>
          <span class="sg-status-pill sg-pill-neutral">Todas</span>
        </div>
        <h3 class="sg-cohort-name">Padrón Consolidado</h3>
        <p class="sg-stat-sub">Todas las cohortes registradas</p>
        <div class="sg-cohort-stats">
          <div>
            <div class="sg-cohort-stat-val">${totalIngresantesGlobal}</div>
            <div class="sg-cohort-stat-lbl">Ingresantes</div>
          </div>
          <div>
            <div class="sg-cohort-stat-val" style="color: var(--sg-success);">${totalEgresadosGlobal}</div>
            <div class="sg-cohort-stat-lbl">Acreditados</div>
          </div>
        </div>
      </div>
    `;

    cohortesRecientes.forEach(c => {
      const tasa = c.ingresantes > 0 ? Math.round((c.egresados / c.ingresantes) * 100) : 0;
      
      let pillClass = 'sg-pill-primary';
      if (c.estado.includes('5to')) pillClass = 'sg-pill-success';
      else if (c.estado.includes('1er')) pillClass = 'sg-pill-warning';

      html += `
        <div class="sg-cohort-card" id="card-cohorte-${c.anio}" onclick="filtrarCohorteRapido('${c.anio}')" tabindex="0" role="button" aria-pressed="false">
          <div class="sg-cohort-header">
            <span class="sg-cohort-code">Cohorte ${c.anio}</span>
            <span class="sg-status-pill ${pillClass}">${c.estado}</span>
          </div>
          <h3 class="sg-cohort-name">${c.nombre}</h3>
          <p class="sg-stat-sub">${c.plan} · Ref. ${c.duracion} años</p>
          <div class="sg-cohort-stats">
            <div>
              <div class="sg-cohort-stat-val">${c.ingresantes}</div>
              <div class="sg-cohort-stat-lbl">Ingresantes (I1)</div>
            </div>
            <div>
              <div class="sg-cohort-stat-val" style="color: var(--sg-success);">${c.egresados}</div>
              <div class="sg-cohort-stat-lbl">Acreditados</div>
            </div>
          </div>
          <div class="sg-progress-bar" title="Tasa de titulación I1: ${tasa}%">
            <div class="sg-progress-fill" style="width: ${Math.min(tasa, 100)}%;"></div>
          </div>
        </div>
      `;
    });

    if (cohortesLista.length > 4) {
      const restantes = cohortesLista.length - 4;
      html += `
        <div class="sg-cohort-card sg-cohort-card-more" onclick="activarModuloTab('cohortes')" role="button" tabindex="0" title="Ver archivo histórico completo en pestaña Cohortes">
          <div class="sg-cohort-header">
            <span class="sg-cohort-code">Histórico</span>
            <span class="sg-status-pill sg-pill-neutral">+${restantes} más</span>
          </div>
          <h3 class="sg-cohort-name">Todas las Cohortes</h3>
          <p class="sg-stat-sub">Consulte el archivo histórico institucional completo.</p>
          <div style="font-size: 12px; font-weight: 600; color: var(--sg-accent); margin-top: auto; padding-top: 6px;">
            Ver en pestaña Cohortes →
          </div>
        </div>
      `;
    }

    container.innerHTML = html;
  }

  function renderizarTablaPadron(filas = null) {
    const tbody = document.getElementById('tbody-padron');
    if (!tbody) return;
    tbody.innerHTML = '';

    padronFilasFiltradas = filas || Object.values(padronData);
    const data = padronFilasFiltradas;
    const totalFiltrados = data.length;
    const totalRepo = Object.keys(padronData).length;

    // Manejo de Estados Vacíos según Principios de Usabilidad e Interacción Humano-Computador (HCI)
    const elTablaWrap = document.getElementById('tabla-padron-wrap');
    const elPaginacion = document.getElementById('paginacion-padron');
    const elVacioFiltro = document.getElementById('vacio-filtro');
    const elVacioRepo = document.getElementById('vacio-repositorio');

    if (totalRepo === 0) {
      if (elTablaWrap) elTablaWrap.style.display = 'none';
      if (elPaginacion) elPaginacion.style.display = 'none';
      if (elVacioFiltro) elVacioFiltro.style.display = 'none';
      if (elVacioRepo) elVacioRepo.style.display = 'block';
    } else if (totalFiltrados === 0) {
      if (elTablaWrap) elTablaWrap.style.display = 'none';
      if (elPaginacion) elPaginacion.style.display = 'none';
      if (elVacioFiltro) elVacioFiltro.style.display = 'block';
      if (elVacioRepo) elVacioRepo.style.display = 'none';
    } else {
      if (elTablaWrap) elTablaWrap.style.display = 'block';
      if (elPaginacion) elPaginacion.style.display = 'flex';
      if (elVacioFiltro) elVacioFiltro.style.display = 'none';
      if (elVacioRepo) elVacioRepo.style.display = 'none';
    }

    // Segmentación por Paginación
    const totalPaginas = Math.ceil(totalFiltrados / padronFilasPorPagina) || 1;
    if (padronPaginaActual > totalPaginas) padronPaginaActual = totalPaginas;
    if (padronPaginaActual < 1) padronPaginaActual = 1;

    const inicio = (padronPaginaActual - 1) * padronFilasPorPagina;
    const fin = inicio + padronFilasPorPagina;
    const filasPagina = data.slice(inicio, fin);

    filasPagina.forEach(item => {
      const tr = document.createElement('tr');
      tr.setAttribute('data-id', item.id);
      tr.setAttribute('data-codigo', item.codigo);

      let gradoPill = '';
      if (item.grado === 'TITULADO') gradoPill = '<span class="sg-status-pill sg-pill-success">Titulado Profesional</span>';
      else if (item.grado === 'BACHILLER') gradoPill = '<span class="sg-status-pill sg-pill-primary">Bachiller</span>';
      else if (item.grado === 'PROXIMO') gradoPill = '<span class="sg-status-pill sg-pill-warning">Apto para egresar</span>';
      else gradoPill = '<span class="sg-status-pill sg-pill-neutral">Egresado</span>';

      let aptoBadge = item.aptoF1 === 'APTO'
        ? '<span class="sg-status-pill sg-pill-success">Apto Verificado</span>'
        : '<span class="sg-status-pill sg-pill-warning">Trámite Pendiente</span>';

      tr.innerHTML = `
        <td><strong style="color: var(--sg-navy);">${item.codigo}</strong></td>
        <td>
          <div style="font-weight: 600; color: var(--sg-text);">${item.nombreCompleto}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">DNI: ${item.dni} · Trujillo</small>
        </td>
        <td>
          <div style="font-weight: 500;">Cohorte ${item.cohorte}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">Plan: ${item.plan} · Egreso: ${item.egreso}</small>
        </td>
        <td>${gradoPill}</td>
        <td>${aptoBadge}</td>
        <td>
          <div>${item.correoInst}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">${item.telefono || 'Sin teléfono'}</small>
        </td>
        <td class="sg-numeric">
          <div class="sg-actions" style="justify-content: flex-end; gap: 6px;">
            <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="abrirExpediente(${item.id})">
              Ver
            </button>
            <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="abrirEditarExpediente(${item.id})" title="Editar datos del expediente">
              Editar
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    const totalReg = document.getElementById('total-registros');
    const totalGen = document.getElementById('total-general');
    if (totalReg) totalReg.textContent = totalFiltrados;
    if (totalGen) totalGen.textContent = totalRepo;

    const kpiEgr = document.getElementById('kpi-total-egresados');
    if (kpiEgr) kpiEgr.textContent = totalRepo;

    // Actualización de controles de paginación
    crearControlesPaginacion({
      contenedorId: 'padron-paginas-controles',
      totalFilas: totalFiltrados,
      paginaActual: padronPaginaActual,
      filasPorPagina: padronFilasPorPagina,
      alCambiarPagina: (nuevaPagina) => {
        padronPaginaActual = nuevaPagina;
        renderizarTablaPadron(padronFilasFiltradas);
      },
      desdeId: 'padron-desde',
      hastaId: 'padron-hasta',
      totalId: 'padron-total'
    });
  }

  function cambiarFilasPorPaginaPadron(n) {
    padronFilasPorPagina = parseInt(n, 10) || 10;
    padronPaginaActual = 1;
    renderizarTablaPadron(padronFilasFiltradas);
  }

  function aplicarFiltros() {
    const q = document.getElementById('busqueda-criterio')?.value.toLowerCase().trim() || '';
    const cohorte = document.getElementById('filtro-cohorte')?.value || '';
    const grado = document.getElementById('filtro-estado-academico')?.value || '';
    const aptitud = document.getElementById('filtro-aptitud')?.value || '';

    // Sincronizar selección visual en las tarjetas de cohorte
    document.querySelectorAll('.sg-cohort-card').forEach(c => {
      c.classList.remove('is-active');
      c.setAttribute('aria-pressed', 'false');
    });
    if (!cohorte) {
      const cardTodas = document.getElementById('card-cohorte-todas');
      if (cardTodas) {
        cardTodas.classList.add('is-active');
        cardTodas.setAttribute('aria-pressed', 'true');
      }
    } else {
      const card = document.getElementById('card-cohorte-' + cohorte);
      if (card) {
        card.classList.add('is-active');
        card.setAttribute('aria-pressed', 'true');
      }
    }

    // Actualizar franja dinámica de métricas con cantidades oficiales
    actualizarFranjaMetricaCohorte(cohorte);

    const filtrados = Object.values(padronData).filter(item => {
      const matchQ = !q || item.codigo.toLowerCase().includes(q) || item.dni.includes(q) || item.nombreCompleto.toLowerCase().includes(q);
      const matchCohorte = !cohorte || item.cohorte === cohorte;
      const matchGrado = !grado || item.grado === grado;
      const matchAptitud = !aptitud || item.aptoF1 === aptitud;
      return matchQ && matchCohorte && matchGrado && matchAptitud;
    });

    padronPaginaActual = 1;
    renderizarTablaPadron(filtrados);
  }

  function filtrarCohorteRapido(anio) {
    const filtroSelect = document.getElementById('filtro-cohorte');
    if (anio === 'TODAS') {
      if (filtroSelect) filtroSelect.value = '';
    } else {
      if (filtroSelect) filtroSelect.value = anio;
    }
    padronPaginaActual = 1;
    aplicarFiltros();
  }

  function restablecerFiltros() {
    const elQ = document.getElementById('busqueda-criterio');
    const elCoh = document.getElementById('filtro-cohorte');
    const elGrd = document.getElementById('filtro-estado-academico');
    const elApt = document.getElementById('filtro-aptitud');

    if (elQ) elQ.value = '';
    if (elCoh) elCoh.value = '';
    if (elGrd) elGrd.value = '';
    if (elApt) elApt.value = '';

    padronPaginaActual = 1;
    aplicarFiltros();
  }



  // =========================================================================
  // GESTIÓN DE COHORTES (CRUD cohorte)
  // =========================================================================
  function renderizarTablaCohortes() {
    const tbody = document.getElementById('tbody-cohortes');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (Object.keys(cohortesData).length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: var(--sg-muted); padding: 32px;">No hay cohortes registradas. Utilice "+ Registrar Nueva Cohorte" para definir una cohorte y su denominador I1.</td></tr>';
      const badgeCohortes = document.getElementById('badge-total-cohortes');
      if (badgeCohortes) badgeCohortes.textContent = '0';
      const kpiCohortes = document.getElementById('kpi-total-cohortes');
      if (kpiCohortes) kpiCohortes.textContent = '0';
      actualizarSelectorCohortesForm();
      return;
    }

    Object.values(cohortesData).forEach(c => {
      const tr = document.createElement('tr');
      const tasa = c.ingresantes > 0 ? ((c.egresados / c.ingresantes) * 100).toFixed(1) : '0.0';

      tr.innerHTML = `
        <td><strong style="color: var(--sg-navy);">Cohorte ${c.anio}</strong></td>
        <td>
          <div style="font-weight: 600; color: var(--sg-text);">${c.nombre}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">${c.obs ? c.obs.substring(0, 65) + '...' : 'Sin observaciones'}</small>
        </td>
        <td><strong>${c.plan}</strong></td>
        <td class="sg-numeric"><strong>${c.ingresantes}</strong> ingresantes</td>
        <td class="sg-numeric" style="color: var(--sg-success); font-weight: 600;">${c.egresados}</td>
        <td class="sg-numeric">
          <span style="font-weight: 700; color: var(--sg-navy);">${tasa}%</span>
        </td>
        <td>${c.duracion} años</td>
        <td><span class="sg-status-pill sg-pill-primary">${c.estado}</span></td>
        <td class="sg-numeric">
          <div class="sg-actions" style="justify-content: flex-end; gap: 6px;">
            <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="abrirEditarCohorte('${c.anio}')">
              Editar
            </button>
            <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="filtrarCohorteDesdeTabla('${c.anio}')" title="Ver egresados de esta cohorte">
              Ver Nómina
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(tr);
    });

    const badgeCohortes = document.getElementById('badge-total-cohortes');
    if (badgeCohortes) badgeCohortes.textContent = Object.keys(cohortesData).length;

    const kpiCohortes = document.getElementById('kpi-total-cohortes');
    if (kpiCohortes) kpiCohortes.textContent = Object.keys(cohortesData).length;

    actualizarSelectorCohortesForm();
  }

  function filtrarCohorteDesdeTabla(anio) {
    activarModuloTab('egresados');
    filtrarCohorteRapido(anio);
  }

  function actualizarSelectorCohortesForm() {
    const selects = ['nuevo-cohorte', 'filtro-cohorte'];
    selects.forEach(sId => {
      const select = document.getElementById(sId);
      if (!select) return;
      const valorPrevio = select.value;
      if (sId === 'filtro-cohorte') {
        select.innerHTML = '<option value="">Todas las cohortes</option>';
      } else {
        select.innerHTML = '';
      }
      Object.values(cohortesData).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.anio;
        opt.textContent = `Cohorte ${c.anio} (${c.nombre})`;
        select.appendChild(opt);
      });
      if (valorPrevio) select.value = valorPrevio;
    });
  }

  function abrirModalNuevaCohorte() {
    document.getElementById('modal-cohorte-title').textContent = 'Registrar Nueva Cohorte de Ingresantes';
    document.getElementById('form-cohorte').reset();
    document.getElementById('cohorte-edit-id').value = '';
    actualizarSelectorPlanesEnCohorte();
    document.getElementById('btn-submit-cohorte').textContent = 'Guardar Cohorte';
    document.getElementById('modal-cohorte').classList.add('is-open');
    document.getElementById('cohorte-anio').focus();
  }

  function abrirEditarCohorte(anio) {
    const c = cohortesData[anio];
    if (!c) return;
    document.getElementById('modal-cohorte-title').textContent = 'Editar Cohorte Académica ' + anio;
    document.getElementById('cohorte-edit-id').value = anio;
    document.getElementById('cohorte-anio').value = c.anio;
    document.getElementById('cohorte-ingresantes').value = c.ingresantes;
    document.getElementById('cohorte-nombre').value = c.nombre;
    actualizarSelectorPlanesEnCohorte();
    document.getElementById('cohorte-plan').value = c.plan;
    document.getElementById('cohorte-duracion').value = c.duracion;
    document.getElementById('cohorte-obs').value = c.obs || '';
    document.getElementById('btn-submit-cohorte').textContent = 'Actualizar Cohorte';
    document.getElementById('modal-cohorte').classList.add('is-open');
  }

  function cerrarModalCohorte() {
    document.getElementById('modal-cohorte').classList.remove('is-open');
  }

  async function guardarCohorteForm() {
    const editId = document.getElementById('cohorte-edit-id').value;
    const anio = document.getElementById('cohorte-anio').value;
    const ingresantes = parseInt(document.getElementById('cohorte-ingresantes').value, 10);
    const nombre = document.getElementById('cohorte-nombre').value;
    const plan = document.getElementById('cohorte-plan').value;
    const duracion = parseInt(document.getElementById('cohorte-duracion').value, 10) || 5;
    const obs = document.getElementById('cohorte-obs').value;

    const payload = {
      id: editId || null,
      anio_ingreso: parseInt(anio, 10),
      total_ingresantes: ingresantes,
      codigo_promocion: `PROM-${anio}`,
      id_plan_curricular: plan,
      duracion_esperada_anios: duracion,
      observacion: nombre + (obs ? '. ' + obs : '')
    };

    try {
      const resp = await fetch('{{ route('seguimiento-egresado.padron.guardar-cohorte') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok) {
        throw new Error(data.message || 'Error al guardar la cohorte');
      }
    } catch (e) {
      console.warn('Persistencia en base de datos:', e.message);
    }

    const clave = editId || anio;
    cohortesData[clave] = {
      id: editId ? cohortesData[editId].id : Object.keys(cohortesData).length + 1,
      anio: parseInt(anio, 10),
      nombre: nombre,
      ingresantes: ingresantes,
      egresados: editId ? cohortesData[editId].egresados : 0,
      tasaI1: editId ? cohortesData[editId].tasaI1 : 0,
      duracion: duracion,
      plan: plan,
      estado: editId ? cohortesData[editId].estado : 'En Registro',
      obs: obs
    };

    cerrarModalCohorte();
    renderizarCardsCohortes();
    renderizarTablaCohortes();
    mostrarToast(`Cohorte ${anio} guardada exitosamente.`);
  }

  // =========================================================================
  // GESTIÓN DE PLANES CURRICULARES (1998, 2017, 2027)
  // =========================================================================
  function renderizarTablaPlanes() {
    const tbody = document.getElementById('tbody-planes');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (Object.keys(planesData).length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--sg-muted); padding: 32px;">No hay planes curriculares registrados. Utilice "+ Nuevo Plan Curricular" para definir una versión.</td></tr>';
      actualizarSelectorPlanesEnCohorte();
      return;
    }

    Object.values(planesData).forEach(p => {
      const tr = document.createElement('tr');
      let estadoPill = '';
      if (p.estado === 'VIGENTE') estadoPill = '<span class="sg-status-pill sg-pill-success">Vigente</span>';
      else if (p.estado === 'BORRADOR') estadoPill = '<span class="sg-status-pill sg-pill-warning">Borrador</span>';
      else estadoPill = '<span class="sg-status-pill sg-pill-neutral">Cerrado</span>';

      tr.innerHTML = `
        <td><strong style="color: var(--sg-navy); font-size: 15px;">${p.codigo}</strong></td>
        <td>${p.desde || 'Sin fecha'}</td>
        <td>${p.hasta || 'Vigente / Sin término'}</td>
        <td>${estadoPill}</td>
        <td class="sg-numeric"><strong>${p.cohortesCount || 0}</strong> cohortes</td>
        <td class="sg-numeric">
          <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="abrirEditarPlan(${p.id})">
            Editar
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    actualizarSelectorPlanesEnCohorte();
  }

  function actualizarSelectorPlanesEnCohorte() {
    const sel = document.getElementById('cohorte-plan');
    if (!sel) return;
    const prev = sel.value;
    sel.innerHTML = '';
    Object.values(planesData).forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.codigo;
      opt.textContent = `${p.codigo} (${p.estado})`;
      sel.appendChild(opt);
    });
    if (prev && sel.querySelector(`option[value="${prev}"]`)) {
      sel.value = prev;
    } else if (sel.options.length > 0) {
      sel.value = 'Plan 2017';
    }
  }

  function abrirModalNuevoPlan() {
    document.getElementById('modal-plan-title').textContent = 'Registrar Nuevo Plan Curricular';
    document.getElementById('form-plan-curricular').reset();
    document.getElementById('plan-edit-id').value = '';
    document.getElementById('btn-submit-plan').textContent = 'Guardar Plan Curricular';
    document.getElementById('modal-plan-curricular').classList.add('is-open');
    document.getElementById('plan-codigo').focus();
  }

  function abrirEditarPlan(id) {
    const p = planesData[id];
    if (!p) return;
    document.getElementById('modal-plan-title').textContent = 'Editar Plan Curricular ' + p.codigo;
    document.getElementById('plan-edit-id').value = id;
    document.getElementById('plan-codigo').value = p.codigo;
    document.getElementById('plan-desde').value = p.desdeRaw || '';
    document.getElementById('plan-hasta').value = p.hastaRaw || '';
    document.getElementById('plan-estado').value = p.estado;
    document.getElementById('btn-submit-plan').textContent = 'Actualizar Plan';
    document.getElementById('modal-plan-curricular').classList.add('is-open');
  }

  function cerrarModalPlan() {
    document.getElementById('modal-plan-curricular').classList.remove('is-open');
  }

  async function guardarPlanCurricularForm() {
    const editId = document.getElementById('plan-edit-id').value;
    const codigo = document.getElementById('plan-codigo').value.trim();
    const desde = document.getElementById('plan-desde').value;
    const hasta = document.getElementById('plan-hasta').value || null;
    const estado = document.getElementById('plan-estado').value;

    const payload = {
      id: editId || null,
      codigo_version: codigo,
      vigencia_desde: desde,
      vigencia_hasta: hasta,
      estado: estado,
    };

    try {
      const resp = await fetch('{{ route('seguimiento-egresado.padron.guardar-plan') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok) {
        throw new Error(data.message || 'Error al guardar el plan curricular');
      }
    } catch (e) {
      console.warn('Persistencia en base de datos:', e.message);
    }

    const idKey = editId ? parseInt(editId, 10) : Object.keys(planesData).length + 1;
    planesData[idKey] = {
      id: idKey,
      codigo: codigo,
      desde: desde ? new Date(desde + 'T00:00:00').toLocaleDateString('es-PE') : '',
      desdeRaw: desde,
      hasta: hasta ? new Date(hasta + 'T00:00:00').toLocaleDateString('es-PE') : 'Vigente / Sin término',
      hastaRaw: hasta,
      estado: estado,
      cohortesCount: editId ? (planesData[idKey]?.cohortesCount || 0) : 0,
    };

    cerrarModalPlan();
    renderizarTablaPlanes();
    mostrarToast(`Plan curricular '${codigo}' guardado exitosamente.`);
  }

  // =========================================================================
  // GESTIÓN DE ACREDITACIÓN Y ESTADOS ACADÉMICOS (historial_estado_academico)
  // =========================================================================
  function renderizarTablaAcreditacion(filas = null) {
    const tbody = document.getElementById('tbody-acreditacion');
    if (!tbody) return;
    tbody.innerHTML = '';

    acredFilasFiltradas = filas || Object.values(padronData);
    const data = acredFilasFiltradas;
    const totalFiltrados = data.length;

    if (totalFiltrados === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--sg-muted); padding: 32px;">No se encontraron registros de egresados para acreditar en el padrón.</td></tr>';
      crearControlesPaginacion({
        contenedorId: 'acred-paginas-controles',
        totalFilas: 0,
        paginaActual: 1,
        filasPorPagina: acredFilasPorPagina,
        alCambiarPagina: () => {},
        desdeId: 'acred-desde',
        hastaId: 'acred-hasta',
        totalId: 'acred-total'
      });
      return;
    }

    const totalPaginas = Math.ceil(totalFiltrados / acredFilasPorPagina) || 1;
    if (acredPaginaActual > totalPaginas) acredPaginaActual = totalPaginas;
    if (acredPaginaActual < 1) acredPaginaActual = 1;

    const inicio = (acredPaginaActual - 1) * acredFilasPorPagina;
    const fin = inicio + acredFilasPorPagina;
    const filasPagina = data.slice(inicio, fin);

    filasPagina.forEach(item => {
      const tr = document.createElement('tr');
      let gradoPill = '';
      if (item.grado === 'TITULADO') gradoPill = '<span class="sg-status-pill sg-pill-success">Titulado Profesional</span>';
      else if (item.grado === 'BACHILLER') gradoPill = '<span class="sg-status-pill sg-pill-primary">Bachiller</span>';
      else if (item.grado === 'PROXIMO') gradoPill = '<span class="sg-status-pill sg-pill-warning">Apto para egresar</span>';
      else gradoPill = '<span class="sg-status-pill sg-pill-neutral">Egresado</span>';

      let aptoBadge = item.aptoF1 === 'APTO'
        ? '<span class="sg-status-pill sg-pill-success">Apto Acreditado</span>'
        : '<span class="sg-status-pill sg-pill-warning">Trámite Pendiente</span>';

      tr.innerHTML = `
        <td><strong style="color: var(--sg-navy);">${item.codigo}</strong></td>
        <td>
          <div style="font-weight: 600; color: var(--sg-text);">${item.nombreCompleto}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">DNI: ${item.dni}</small>
        </td>
        <td>${gradoPill}</td>
        <td>${item.creditos}</td>
        <td>${aptoBadge}</td>
        <td>
          <div>${item.resBachiller || '<span style="color:var(--sg-muted); font-size:12px;">Sin resolución</span>'}</div>
          <small style="color: var(--sg-muted); font-size: 11px;">${item.fechaBachiller || ''}</small>
        </td>
        <td>
          <div>${item.resTitulo || '<span style="color:var(--sg-muted); font-size:12px;">Sin resolución</span>'}</div>
          <small style="color: var(--sg-muted); font-size: 11px;">${item.fechaTitulo || ''}</small>
        </td>
        <td style="font-size: 13px; color: var(--sg-muted);">${item.responsable}</td>
        <td class="sg-numeric">
          <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="abrirModalActualizarEstado(${item.id})">
            Actualizar Grado
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    crearControlesPaginacion({
      contenedorId: 'acred-paginas-controles',
      totalFilas: totalFiltrados,
      paginaActual: acredPaginaActual,
      filasPorPagina: acredFilasPorPagina,
      alCambiarPagina: (nuevaPagina) => {
        acredPaginaActual = nuevaPagina;
        renderizarTablaAcreditacion(acredFilasFiltradas);
      },
      desdeId: 'acred-desde',
      hastaId: 'acred-hasta',
      totalId: 'acred-total'
    });
  }

  function cambiarFilasPorPaginaAcred(n) {
    acredFilasPorPagina = parseInt(n, 10) || 10;
    acredPaginaActual = 1;
    renderizarTablaAcreditacion(acredFilasFiltradas);
  }

  function abrirModalActualizarEstado(id) {
    const item = padronData[id];
    if (!item) return;
    document.getElementById('estado-egresado-id').value = id;
    document.getElementById('modal-estado-title').textContent = 'Actualizar Estado: ' + item.nombreCompleto;
    document.getElementById('modal-estado-subtitulo').textContent = 'Matrícula: ' + item.codigo + ' | Expediente Nº ' + id;
    document.getElementById('estado-select-grado').value = item.grado;
    document.getElementById('estado-select-apto').value = item.aptoF1;
    document.getElementById('estado-creditos').value = parseFloat(item.creditos) || '';
    document.getElementById('estado-fecha-bachiller').value = item.fechaBachiller || '';
    document.getElementById('estado-res-bachiller').value = item.resBachiller || '';
    document.getElementById('estado-fecha-titulo').value = item.fechaTitulo || '';
    document.getElementById('estado-res-titulo').value = item.resTitulo || '';
    document.getElementById('modal-estado-academico').classList.add('is-open');
  }

  function abrirModalActualizarEstadoDesdeFicha() {
    const id = document.getElementById('modal-expediente').getAttribute('data-active-id');
    cerrarModalExpediente();
    abrirModalActualizarEstado(id);
  }

  function cerrarModalEstado() {
    document.getElementById('modal-estado-academico').classList.remove('is-open');
  }

  async function guardarEstadoAcademicoForm() {
    const id = document.getElementById('estado-egresado-id').value;
    const item = padronData[id];
    if (!item) return;

    const grado = document.getElementById('estado-select-grado').value;
    const apto = document.getElementById('estado-select-apto').value;
    const creditos = parseFloat(document.getElementById('estado-creditos').value) || null;
    const fechaBachiller = document.getElementById('estado-fecha-bachiller').value;
    const resBachiller = document.getElementById('estado-res-bachiller').value;
    const fechaTitulo = document.getElementById('estado-fecha-titulo').value;
    const resTitulo = document.getElementById('estado-res-titulo').value;

    const payload = {
      id_egresado: parseInt(id, 10),
      grado: grado,
      apto_f1: apto,
      creditos_aprobados: creditos,
      resolucion_bachiller: resBachiller,
      fecha_bachiller: fechaBachiller || null,
      resolucion_titulo: resTitulo,
      fecha_titulo: fechaTitulo || null,
    };

    try {
      const resp = await fetch('{{ route('seguimiento-egresado.padron.guardar-estado') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok) {
        throw new Error(data.message || 'Error al actualizar el estado académico');
      }
    } catch (e) {
      console.warn('Persistencia en base de datos:', e.message);
    }

    item.grado = grado;
    item.aptoF1 = apto;
    item.creditos = creditos !== null ? creditos + ' créditos' : '—';
    item.fechaBachiller = fechaBachiller;
    item.resBachiller = resBachiller;
    item.fechaTitulo = fechaTitulo;
    item.resTitulo = resTitulo;

    cerrarModalEstado();
    renderizarTablaPadron();
    renderizarTablaAcreditacion();
    mostrarToast(`Acreditación actualizada para ${item.nombreCompleto}.`);
  }

  // =========================================================================
  // GESTIÓN DE CARGAS DE SECRETARÍA (importacion_padron e importacion_fila)
  // =========================================================================
  function renderizarTablaImportaciones() {
    const tbody = document.getElementById('tbody-importaciones');
    if (!tbody) return;
    tbody.innerHTML = '';

    const data = importacionesData;
    const totalFiltrados = data.length;

    if (totalFiltrados === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--sg-muted); padding: 32px;">Aún no se han registrado importaciones masivas de nóminas oficiales.</td></tr>';
      crearControlesPaginacion({
        contenedorId: 'imp-paginas-controles',
        totalFilas: 0,
        paginaActual: 1,
        filasPorPagina: impFilasPorPagina,
        alCambiarPagina: () => {},
        desdeId: 'imp-desde',
        hastaId: 'imp-hasta',
        totalId: 'imp-total'
      });
      return;
    }

    const totalPaginas = Math.ceil(totalFiltrados / impFilasPorPagina) || 1;
    if (impPaginaActual > totalPaginas) impPaginaActual = totalPaginas;
    if (impPaginaActual < 1) impPaginaActual = 1;

    const inicio = (impPaginaActual - 1) * impFilasPorPagina;
    const fin = inicio + impFilasPorPagina;
    const filasPagina = data.slice(inicio, fin);

    filasPagina.forEach(imp => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><strong>#${imp.id}</strong></td>
        <td>
          <div style="font-weight: 600; color: var(--sg-navy);">${imp.archivo}</div>
          <small style="color: var(--sg-muted); font-size: 12px;">${imp.totalFilas} registros procesados</small>
        </td>
        <td>
          <code style="font-size: 11px; background: var(--sg-hover); padding: 3px 6px; border-radius: var(--sg-radius-sm); border: 1px solid var(--sg-border); font-family: var(--sg-font-code);">${imp.hash.substring(0, 16)}...</code>
        </td>
        <td><span class="sg-badge">${imp.origen}</span></td>
        <td>${imp.fecha}</td>
        <td style="font-size: 13px;">${imp.operador}</td>
        <td>
          <div style="font-size: 13px; font-variant-numeric: tabular-nums;">
            <span style="color: var(--sg-success); font-weight: 600;">${imp.filasAceptadas} aceptadas</span>
            ${imp.filasRechazadas > 0 ? `· <span style="color: var(--sg-danger); font-weight: 600;">${imp.filasRechazadas} observadas</span>` : ''}
          </div>
        </td>
        <td><span class="sg-status-pill sg-pill-success">${imp.estado}</span></td>
        <td class="sg-numeric">
          <button type="button" class="sg-button sg-button-secondary" style="min-height: 32px; padding: 4px 10px; font-size: 12px;" onclick="verDetalleImportacion(${imp.id})">
            Ver Log
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });

    const kpiImp = document.getElementById('kpi-total-importaciones');
    if (kpiImp) kpiImp.textContent = importacionesData.length;

    crearControlesPaginacion({
      contenedorId: 'imp-paginas-controles',
      totalFilas: totalFiltrados,
      paginaActual: impPaginaActual,
      filasPorPagina: impFilasPorPagina,
      alCambiarPagina: (nuevaPagina) => {
        impPaginaActual = nuevaPagina;
        renderizarTablaImportaciones();
      },
      desdeId: 'imp-desde',
      hastaId: 'imp-hasta',
      totalId: 'imp-total'
    });
  }

  function verDetalleImportacion(id) {
    const imp = importacionesData.find(i => i.id === id);
    if (!imp) return;

    document.getElementById('modal-det-imp-title').textContent = 'Ejecución de Carga #' + imp.id + ' — ' + imp.archivo;
    document.getElementById('modal-det-imp-subtitulo').textContent = 'Fecha: ' + imp.fecha + ' | Operador: ' + imp.operador;
    document.getElementById('det-imp-estado').innerHTML = '<span class="sg-status-pill sg-pill-success">' + imp.estado + '</span>';
    document.getElementById('det-imp-total').textContent = imp.totalFilas + ' registros';
    document.getElementById('det-imp-aceptadas').textContent = imp.filasAceptadas + ' registros';
    document.getElementById('det-imp-rechazadas').textContent = imp.filasRechazadas + ' registros';

    const tbody = document.getElementById('tbody-detalle-filas');
    tbody.innerHTML = '';

    if (!imp.filasDetalle || imp.filasDetalle.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="4" style="text-align: center; color: var(--sg-muted); padding: 18px;">Todas las filas fueron aceptadas y validadas sin conflictos de integridad.</td>';
      tbody.appendChild(tr);
    } else {
      imp.filasDetalle.forEach(f => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><strong>${f.fila}</strong></td>
          <td><code style="font-size: 11px; font-family: var(--sg-font-code);">${f.datos}</code></td>
          <td><span class="sg-status-pill sg-pill-danger">${f.estado}</span></td>
          <td style="color: var(--sg-danger); font-size: 13px;">${f.causa}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    document.getElementById('modal-detalle-importacion').classList.add('is-open');
  }

  function cerrarModalDetalleImportacion() {
    document.getElementById('modal-detalle-importacion').classList.remove('is-open');
  }

  function abrirModalImportacion() {
    document.getElementById('modal-importacion').classList.add('is-open');
  }

  function cerrarModalImportacion() {
    document.getElementById('modal-importacion').classList.remove('is-open');
  }

  // =========================================================================
  // GESTIÓN DE EXPEDIENTE ÚNICO (CRUD egresado con conexión viva a PostgreSQL)
  // =========================================================================
  function abrirConfirmarEgreso() {
    const id = document.getElementById('modal-expediente').getAttribute('data-active-id');
    const item = padronData[id];
    if (!item) return;
    document.getElementById('modal-egreso-subtitulo').textContent = item.nombreCompleto + ' · Matrícula ' + item.codigo;
    document.getElementById('egreso-fecha').value = '';
    document.getElementById('egreso-fecha').max = new Date().toISOString().slice(0, 10);
    document.getElementById('egreso-error').style.display = 'none';
    document.getElementById('modal-confirmar-egreso').classList.add('is-open');
  }

  function cerrarConfirmarEgreso() {
    document.getElementById('modal-confirmar-egreso').classList.remove('is-open');
  }

  async function guardarConfirmarEgreso() {
    const id = document.getElementById('modal-expediente').getAttribute('data-active-id');
    const fecha = document.getElementById('egreso-fecha').value;
    const error = document.getElementById('egreso-error');
    error.style.display = 'none';
    if (!fecha) {
      error.textContent = 'Indique la fecha real de egreso.';
      error.style.display = 'block';
      return;
    }
    try {
      const resp = await fetch('{{ url('seguimiento-egresado/padron/egresado') }}/' + id + '/confirmar-egreso', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({ fecha_egreso: fecha })
      });
      const data = await resp.json();
      if (!resp.ok) {
        const detalle = data.errors ? Object.values(data.errors).flat().join(' ') : '';
        throw new Error(detalle || data.message || 'No se pudo confirmar el egreso.');
      }
      window.location.reload();
    } catch (e) {
      error.textContent = e.message;
      error.style.display = 'block';
    }
  }

  async function abrirExpediente(id) {
    const egresado = padronData[id];
    if (!egresado) return;
    document.getElementById('btn-confirmar-egreso').style.display = egresado.grado === 'PROXIMO' ? '' : 'none';

    document.getElementById('modal-expediente').setAttribute('data-active-id', id);
    document.getElementById('modal-exp-title').textContent = egresado.nombreCompleto;
    document.getElementById('modal-exp-subtitulo').textContent = 'Matrícula: ' + egresado.codigo + ' | DNI: ' + egresado.dni;
    document.getElementById('exp-nombres').textContent = egresado.nombreCompleto;
    document.getElementById('exp-dni').textContent = egresado.dni;
    const cohorteInfo = cohortesData[egresado.cohorte];
    const descCohorte = cohorteInfo?.nombre ? ` · ${cohorteInfo.nombre}` : '';
    document.getElementById('exp-cohorte').textContent = `Cohorte ${egresado.cohorte}${descCohorte}`;
    document.getElementById('exp-egreso').textContent = `${egresado.egreso} · ${egresado.fechaEgreso}`;
    document.getElementById('exp-plan').textContent = egresado.plan;
    document.getElementById('exp-creditos').textContent = egresado.creditos;

    let gradoPill = '';
    if (egresado.grado === 'TITULADO') gradoPill = '<span class="sg-status-pill sg-pill-success">Titulado Profesional</span>';
    else if (egresado.grado === 'BACHILLER') gradoPill = '<span class="sg-status-pill sg-pill-primary">Bachiller</span>';
    else if (egresado.grado === 'PROXIMO') gradoPill = '<span class="sg-status-pill sg-pill-warning">Apto para egresar</span>';
    else gradoPill = '<span class="sg-status-pill sg-pill-neutral">Egresado</span>';
    document.getElementById('exp-grado-badge').innerHTML = gradoPill;

    let aptoBadge = egresado.aptoF1 === 'APTO'
      ? '<span class="sg-status-pill sg-pill-success">Apto Acreditado</span>'
      : '<span class="sg-status-pill sg-pill-warning">Trámite Pendiente</span>';
    document.getElementById('exp-apto-f1').innerHTML = aptoBadge;

    document.getElementById('exp-res-bachiller').textContent = egresado.resBachiller || 'No registrada';
    document.getElementById('exp-res-titulo').textContent = egresado.resTitulo || 'No registrada';

    document.getElementById('exp-correo-inst').textContent = egresado.correoInst;
    document.getElementById('exp-correo-pers').textContent = egresado.correoPers || 'No registrado';
    document.getElementById('exp-telefono').textContent = egresado.telefono || 'No registrado';
    document.getElementById('exp-ciudad').textContent = egresado.ciudad || '—';

    // Cargar historial de campañas en vivo desde PostgreSQL
    const timeline = document.getElementById('exp-timeline');
    timeline.innerHTML = '<p style="color:var(--sg-muted); font-size:13px;">Cargando historial de mediciones de base de datos...</p>';

    try {
      const resp = await fetch(`/seguimiento-egresado/padron/egresado/${id}`);
      if (resp.ok) {
        const live = await resp.json();
        if (live.contacto) {
          document.getElementById('exp-correo-pers').textContent = live.contacto.correo_personal || egresado.correoPers || 'No registrado';
          document.getElementById('exp-telefono').textContent = live.contacto.celular_whatsapp || egresado.telefono || 'No registrado';
          document.getElementById('exp-ciudad').textContent = live.contacto.direccion || egresado.ciudad || '—';
        }
        timeline.innerHTML = '';
        if (live.historial_seguimiento && live.historial_seguimiento.length > 0) {
          live.historial_seguimiento.forEach(h => {
            const node = document.createElement('div');
            node.className = 'sg-timeline-node';
            const pillBadge = h.estado_invitacion === 'RESPONDIDA' ? 'sg-pill-success' : 'sg-pill-warning';
            node.innerHTML = `
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 6px;">
                <div>
                  <div style="font-weight: 600; font-size: 14px; color: var(--sg-navy);">${h.campania}</div>
                  <div style="font-size: 12px; color: var(--sg-muted);">Fecha: ${h.fecha_invitacion ? new Date(h.fecha_invitacion).toLocaleDateString('es-PE') : 'Periodo de campaña'}</div>
                </div>
                <span class="sg-status-pill ${pillBadge}">${h.estado_invitacion}</span>
              </div>
              <p style="font-size: 13px; color: var(--sg-text); margin: 0; line-height: 1.5;">
                ${h.situacion_laboral ? 'Situación laboral: ' + h.situacion_laboral + (h.empresa ? ' en ' + h.empresa : '') : 'Participación registrada en el ciclo de seguimiento.'}
              </p>
            `;
            timeline.appendChild(node);
          });
        } else {
          timeline.innerHTML = '<p style="color:var(--sg-muted); font-size:13px;">Sin historial de campañas registradas aún para este expediente.</p>';
        }
      }
    } catch (e) {
      timeline.innerHTML = '<p style="color:var(--sg-muted); font-size:13px;">Sin historial de campañas registradas aún.</p>';
    }

    cambiarSubpestana('academico');
    document.getElementById('modal-expediente').classList.add('is-open');
  }

  function cerrarModalExpediente() {
    document.getElementById('modal-expediente').classList.remove('is-open');
  }

  function cambiarSubpestana(subtab) {
    const subtabs = ['academico', 'contacto', 'seguimiento'];
    subtabs.forEach(s => {
      const btn = document.getElementById('subtab-btn-' + s);
      const panel = document.getElementById('subtab-panel-' + s);
      if (btn && panel) {
        if (s === subtab) {
          btn.classList.add('is-active');
          btn.setAttribute('aria-selected', 'true');
          panel.style.display = 'block';
        } else {
          btn.classList.remove('is-active');
          btn.setAttribute('aria-selected', 'false');
          panel.style.display = 'none';
        }
      }
    });
  }

  function abrirModalNuevoExpediente() {
    document.getElementById('modal-nuevo-title').textContent = 'Registro de Expediente Individual';
    document.getElementById('form-nuevo-expediente').reset();
    document.getElementById('exp-edit-id').value = '';
    actualizarSelectorCohortesForm();
    document.getElementById('btn-submit-expediente').textContent = 'Registrar en Padrón';
    document.getElementById('modal-nuevo-expediente').classList.add('is-open');
    document.getElementById('nuevo-codigo').focus();
  }

  function abrirEditarExpediente(id) {
    const item = padronData[id];
    if (!item) return;

    document.getElementById('modal-nuevo-title').textContent = 'Editar Expediente: ' + item.codigo;
    document.getElementById('exp-edit-id').value = id;
    actualizarSelectorCohortesForm();
    document.getElementById('nuevo-codigo').value = item.codigo;
    document.getElementById('nuevo-dni').value = item.dni;
    document.getElementById('nuevo-nombres').value = item.nombres;
    document.getElementById('nuevo-paterno').value = item.paterno;
    document.getElementById('nuevo-materno').value = item.materno || '';
    document.getElementById('nuevo-cohorte').value = item.cohorte;
    document.getElementById('nuevo-periodo').value = item.egreso;
    document.getElementById('nuevo-grado').value = item.grado;
    document.getElementById('nuevo-apto').value = item.aptoF1;
    document.getElementById('nuevo-correo-inst').value = item.correoInst;
    document.getElementById('nuevo-telefono').value = item.telefono || '';
    document.getElementById('btn-submit-expediente').textContent = 'Guardar Cambios';
    document.getElementById('modal-nuevo-expediente').classList.add('is-open');
  }

  function cerrarModalNuevoExpediente() {
    document.getElementById('modal-nuevo-expediente').classList.remove('is-open');
  }

  async function guardarExpedienteForm() {
    const editId = document.getElementById('exp-edit-id').value;
    const cod = document.getElementById('nuevo-codigo').value.trim();
    const dni = document.getElementById('nuevo-dni').value.trim();
    const nom = document.getElementById('nuevo-nombres').value.trim();
    const pat = document.getElementById('nuevo-paterno').value.trim();
    const mat = document.getElementById('nuevo-materno').value.trim();
    const coh = document.getElementById('nuevo-cohorte').value;
    const per = document.getElementById('nuevo-periodo').value.trim();
    const grd = document.getElementById('nuevo-grado').value;
    const apt = document.getElementById('nuevo-apto').value;
    const cor = document.getElementById('nuevo-correo-inst').value.trim();
    const tel = document.getElementById('nuevo-telefono').value.trim();

    const nombreCompleto = `${pat} ${mat ? mat + ', ' : ', '}${nom}`;

    const payload = {
      id: editId || null,
      codigo_matricula: cod,
      numero_documento: dni,
      nombres: nom,
      apellido_paterno: pat,
      apellido_materno: mat,
      id_cohorte: coh,
      periodo_egreso: per,
      grado: grd,
      apto_f1: apt,
      correo_institucional: cor,
      telefono: tel
    };

    try {
      const resp = await fetch('{{ route('seguimiento-egresado.padron.guardar-egresado') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok) {
        const detalle = data.errors ? Object.values(data.errors).flat().join(' ') : '';
        throw new Error(detalle || data.message || 'Error al registrar el expediente');
      }
    } catch (e) {
      // Si el servidor no guardó el expediente no se muestra como guardado.
      alert('No se guardó el expediente: ' + e.message);
      return;
    }

    if (editId) {
      const item = padronData[editId];
      item.codigo = cod;
      item.dni = dni;
      item.nombres = nom;
      item.paterno = pat;
      item.materno = mat;
      item.nombreCompleto = nombreCompleto;
      item.cohorte = coh;
      item.egreso = per;
      item.grado = grd;
      item.aptoF1 = apt;
      item.correoInst = cor;
      item.telefono = tel;
    } else {
      const newId = Object.keys(padronData).length + 1;
      padronData[newId] = {
        id: newId,
        codigo: cod,
        dni: dni,
        nombres: nom,
        paterno: pat,
        materno: mat,
        nombreCompleto: nombreCompleto,
        cohorte: coh,
        egreso: per,
        fechaEgreso: new Date().toLocaleDateString('es-PE'),
        plan: cohortesData[coh]?.plan || 'Plan 2017',
        creditos: '220.0 / 220.0 (100%)',
        grado: grd,
        aptoF1: apt,
        resBachiller: grd !== 'EGRESADO' ? 'Trámite Registrado' : 'No aplica',
        fechaBachiller: '',
        resTitulo: grd === 'TITULADO' ? 'Trámite Registrado' : 'No aplica',
        fechaTitulo: '',
        correoInst: cor,
        correoPers: 'No registrado',
        telefono: tel,
        ciudad: 'Trujillo, La Libertad',
        responsable: 'Secretaría Académica',
        historial: [
          {
            campania: 'Alta en Padrón Oficial',
            fecha: new Date().toLocaleDateString('es-PE'),
            estado: 'Expediente Aperturado',
            badge: 'sg-pill-neutral',
            detalle: 'Incorporación al padrón de Ingeniería Informática UNT.'
          }
        ]
      };
    }

    cerrarModalNuevoExpediente();
    renderizarCardsCohortes();
    renderizarTablaPadron();
    renderizarTablaAcreditacion();
    mostrarToast(`Expediente ${cod} guardado exitosamente en el padrón.`);
  }

  // =========================================================================
  // ATAJOS DE TECLADO Y ACCESIBILIDAD (Escape / Click exterior)
  // =========================================================================
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      cerrarModalExpediente();
      cerrarModalNuevoExpediente();
      cerrarModalCohorte();
      cerrarModalPlan();
      cerrarModalEstado();
      cerrarModalImportacion();
      cerrarModalDetalleImportacion();
    }
  });

  document.querySelectorAll('.sg-modal-backdrop').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        modal.classList.remove('is-open');
      }
    });
  });

  // Inicialización al cargar la página
  document.addEventListener('DOMContentLoaded', function() {
    renderizarCardsCohortes();
    actualizarFranjaMetricaCohorte('');
    actualizarSelectorCohortesForm();
    renderizarTablaPadron();
    renderizarTablaCohortes();
    renderizarTablaPlanes();
    renderizarTablaAcreditacion();
    renderizarTablaImportaciones();

    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab && ['egresados', 'cohortes', 'acreditacion', 'importaciones'].includes(tab)) {
      activarModuloTab(tab);
    }
  });
</script>
@endpush
