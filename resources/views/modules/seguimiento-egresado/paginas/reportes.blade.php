@extends('modules.seguimiento-egresado.layout')
@section('title', 'Reportes y formatos institucionales F1–F11')
@section('section_name', 'Reportes institucionales')

@section('content')
<section class="sg-heading" aria-labelledby="sg-reportes-title">
  <div class="sg-kicker">REPORTES Y MATRIZ OFICIAL DE ACREDITACIÓN · SINEACE / ICACIT / UNT</div>
  <h1 id="sg-reportes-title">Reportes y formatos institucionales F1–F11</h1>
  <p>Generación y descarga de reportes oficiales, correspondencia de indicadores I1 a I6 y matriz de trazabilidad F1 a F11.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <span class="sg-badge sg-badge-success">Módulo Oficial Activo</span>
    <span class="sg-badge">Exportaciones Consolidadas</span>
    <span class="sg-badge">Formatos F1–F11</span>
    <span class="sg-badge">Auditoría con Hash SHA-256</span>
  </div>
</section>

{{-- MENSAJES DE ESTADO / ALERTAS --}}
@if(session('success'))
  <div class="sg-alert" role="status" style="margin-bottom: var(--sg-space-4); border-left-color: var(--sg-success); background: #e7f7f0;">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Reporte generado:</strong>
      <p>{{ session('success') }}</p>
    </div>
  </div>
@endif

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Observaciones de generación de reportes:</strong>
      <ul style="margin: 4px 0 0; padding-left: 18px; font-size: 13px;">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

{{-- AVISO METODOLÓGICO Y MATRIZ INSTITUCIONAL --}}
<div class="sg-alert" role="note" style="margin-bottom: var(--sg-space-6);" aria-labelledby="sg-nota-reportes">
  @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
  <div>
    <strong id="sg-nota-reportes">Criterio de transparencia institucional:</strong>
    <p>
      Todos los reportes y formatos disponibles se generan directamente con los datos consolidados del sistema institucional.
      Los formatos que dependen de fuentes no universitarias o de módulos externos aún no conectados (como el Formato F7 de convenios centrales de la UNT)
      <strong>se reportan formalmente como pendientes por fuente, sin simular datos inventados</strong>.
    </p>
  </div>
</div>

{{-- MÉTRICAS RESUMEN --}}
<section class="sg-panel" aria-labelledby="sg-metricas-rep-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <h2 id="sg-metricas-rep-title">Disponibilidad de formatos y fuentes de información</h2>
      <p>Estado de cobertura de los formatos oficiales requeridos para acreditación.</p>
    </div>
  </div>

  <div class="sg-metric-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--sg-space-4); margin-top: var(--sg-space-3);">
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-navy);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Formatos mapeados</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-navy); margin-top: 4px;">11 Formatos</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">F1 al F11 completos</div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-success);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Formatos implementados</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-success); margin-top: 4px;">10 de 11</div>
      <div style="font-size: 12px; color: var(--sg-success); font-weight: 600; margin-top: 4px;">✓ Generación efectiva desde BD</div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-warning);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Pendientes por fuente externa</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-warning); margin-top: 4px;">1 Formato</div>
      <div style="font-size: 12px; color: var(--sg-warning); margin-top: 4px;">F7 (Convenios centrales UNT)</div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-accent);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Auditoría de reportes</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-accent); margin-top: 4px;">{{ count($reportesRecientes) }} registrados</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Con hash SHA-256 en BD</div>
    </div>
  </div>
</section>

{{-- ACCIONES DE EXPORTACIÓN RÁPIDA --}}
<section class="sg-panel" aria-labelledby="sg-exportaciones-rapidas-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <h2 id="sg-exportaciones-rapidas-title">Consolidados institucionales oficiales (CSV)</h2>
      <p>Descarga de reportes integrales para secretaría, dirección y evaluadores externos.</p>
    </div>
  </div>

  <div style="display: flex; gap: 16px; flex-wrap: wrap;">
    <a class="sg-button" href="{{ route('seguimiento-egresado.reportes.indicadores.csv', request()->query()) }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
      <span>Descargar Consolidado Oficial I1–I6 (CSV)</span>
    </a>

    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.campanias.csv', request()->query()) }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
      <span>Descargar Catálogo de Campañas y Cobertura (CSV)</span>
    </a>
  </div>
</section>

{{-- MATRIZ DE FORMATOS INSTITUCIONALES F1 A F11 --}}
<section class="sg-panel" aria-labelledby="sg-matriz-f1-f11-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">MATRIZ OFICIAL DE FORMATOS INSTITUCIONALES</span>
      <h2 id="sg-matriz-f1-f11-title">Matriz de correspondencia y disponibilidad F1–F11</h2>
      <p>Cotejo entre los formatos de acreditación de la Escuela de Informática y las fuentes oficiales del sistema.</p>
    </div>
    <span class="sg-badge sg-badge-success">11 Formatos Mapeados</span>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-matriz-f1-f11-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col" style="width: 75px;">Código</th>
          <th scope="col" style="min-width: 240px;">Denominación Oficial</th>
          <th scope="col">Normativa de Referencia</th>
          <th scope="col">Fuente en Base de Datos</th>
          <th scope="col">Estado</th>
          <th scope="col">Acción</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($matrizFormatos as $f)
          <tr>
            <td style="font-weight: 700; color: var(--sg-navy); font-size: 15px;">{{ $f['codigo'] }}</td>
            <td>
              <div style="font-weight: 600; color: var(--sg-navy);">{{ $f['nombre'] }}</div>
              <p style="font-size: 12px; color: var(--sg-muted); margin: 2px 0 0;">{{ $f['descripcion'] }}</p>
            </td>
            <td style="font-size: 13px;">{{ $f['normativa'] }}</td>
            <td style="font-size: 12px; font-family: monospace; color: var(--sg-muted);">
              {{ $f['fuente_bd'] }}
            </td>
            <td>
              <span class="sg-badge {{ $f['estado_badge'] }}">{{ $f['estado'] }}</span>
            </td>
            <td>
              @if($f['exportable'])
                <a class="sg-button" style="padding: 6px 10px; min-height: 32px; font-size: 12px;" href="{{ route('seguimiento-egresado.reportes.formato', $f['codigo']) }}">
                  @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
                  <span>Descargar CSV</span>
                </a>
              @else
                <button class="sg-button sg-button-secondary" style="padding: 6px 10px; min-height: 32px; font-size: 12px;" onclick="alert('FORMATO F7: Requiere integración con el módulo de convenios centrales de la UNT. Se documenta formalmente como pendiente por fuente en docs/matriz-formatos-f1-f11.md.');">
                  Ver justificación
                </button>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

{{-- TABLA DE AUDITORÍA Y TRAZABILIDAD (reporte_generado) --}}
@if($reportesRecientes->isNotEmpty())
<section class="sg-panel" aria-labelledby="sg-audit-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">AUDITORÍA Y TRAZABILIDAD OFICIAL</span>
      <h2 id="sg-audit-title">Historial de reportes generados con firma SHA-256</h2>
      <p>Registro inmutable en la tabla <code>reporte_generado</code> para verificar fechas de corte y usuarios.</p>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-audit-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col" style="width: 60px;">ID</th>
          <th scope="col">Tipo</th>
          <th scope="col">Formato / Código</th>
          <th scope="col">Fecha de Corte</th>
          <th scope="col">Generado En</th>
          <th scope="col">Usuario Responsable</th>
          <th scope="col">Hash SHA-256 de Integridad</th>
        </tr>
      </thead>
      <tbody>
        @foreach($reportesRecientes as $rep)
          <tr>
            <td style="font-family: monospace;">#{{ $rep->id }}</td>
            <td><span class="sg-badge">{{ $rep->tipo }}</span></td>
            <td><strong>{{ $rep->codigo_formato ?? 'GENERAL' }}</strong></td>
            <td style="font-size: 13px;">{{ $rep->fecha_corte ? date('d/m/Y H:i', strtotime($rep->fecha_corte)) : '—' }}</td>
            <td style="font-size: 13px;">{{ $rep->generado_en ? date('d/m/Y H:i', strtotime($rep->generado_en)) : '—' }}</td>
            <td style="font-size: 13px;">{{ $rep->usuario->nombre_completo ?? 'Dirección de Escuela' }}</td>
            <td style="font-family: monospace; font-size: 11px; color: var(--sg-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              {{ $rep->sha256_archivo ?? '—' }}
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif

@endsection
