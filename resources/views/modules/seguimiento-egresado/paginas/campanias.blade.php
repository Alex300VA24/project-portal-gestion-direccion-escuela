@extends('modules.seguimiento-egresado.layout')
@section('title', 'Campañas e invitaciones')
@section('section_name', 'Campañas e invitaciones')

@push('styles')
<style>
  .sg-resumen-global { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: var(--sg-space-5); }
  .sg-resumen-global > div { background: var(--sg-surface); border: var(--sg-stroke); border-radius: var(--sg-radius); padding: 12px 16px; }
  .sg-resumen-global strong { display: block; font-size: 1.5rem; line-height: 1.2; }
  .sg-resumen-global span { font-size: 13px; color: var(--sg-muted); }
  .sg-tabla-campanias th, .sg-tabla-campanias td { white-space: nowrap; }
  .sg-barra-mini { height: 7px; border-radius: 4px; background: var(--sg-border); overflow: hidden; min-width: 80px; }
  .sg-barra-mini > div, .sg-barra-cob > div { height: 100%; background: var(--sg-success); }
  .sg-barra-cob { height: 10px; border-radius: 5px; background: var(--sg-border); overflow: hidden; margin-top: 6px; }
  /* Ventana de detalle */
  #dlg-detalle { width: min(98vw, 1400px); max-width: 98vw; max-height: 92vh; text-align: left; overflow: auto; }
  #dlg-detalle .sg-detalle-cuerpo { padding: 22px 22px 26px; }
  .sg-detalle-cabecera { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 14px; }
  .sg-resumen-badges { display: flex; gap: 6px; flex-wrap: wrap; }
  .sg-dialogo-cerrar { border: 0; background: transparent; font-size: 18px; cursor: pointer; color: var(--sg-muted); padding: 4px 8px; }
  .sg-cobertura { background: var(--sg-soft); border-radius: var(--sg-radius); padding: 12px 16px; margin-bottom: 14px; }
  .sg-acciones-detalle { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
  .sg-acciones-detalle form { margin: 0; }
  .sg-enlaces { background: #fffdf5; border: 1px solid var(--sg-border); border-left: 4px solid var(--sg-warning); border-radius: var(--sg-radius); padding: 12px 16px; margin: 12px 0; }
  .sg-tabla-detalle td, .sg-tabla-detalle th { white-space: nowrap; padding-left: 10px; padding-right: 10px; }
</style>
@endpush

@section('content')
<section class="sg-heading" aria-labelledby="sg-campanias-title">
  <h1 id="sg-campanias-title">Campañas e invitaciones</h1>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <button type="button" class="sg-button" onclick="abrirNuevaCampania()">+ Nueva campaña</button>
    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.campanias.csv', request()->query()) }}">Descargar lista (CSV)</a>
  </div>
</section>

<div class="sg-resumen-global" aria-label="Resumen de las campañas">
  <div><strong>{{ $resumenGlobal['campanias'] }}</strong><span>{{ $resumenGlobal['campanias'] === 1 ? 'campaña' : 'campañas' }}</span></div>
  <div><strong>{{ $resumenGlobal['convocados'] }}</strong><span>egresados convocados</span></div>
  <div><strong>{{ $resumenGlobal['respondieron'] }}</strong><span>respondieron</span></div>
  <div><strong>{{ number_format($resumenGlobal['cobertura'], 1) }}%</strong><span>de respuesta</span></div>
</div>

<section class="sg-panel" aria-labelledby="sg-filtro-campanias-title" style="margin-bottom: var(--sg-space-5);">
  <h2 id="sg-filtro-campanias-title" class="sg-sr-only">Buscar campañas</h2>
  <form method="GET" action="{{ route('seguimiento-egresado.campanias') }}" class="sg-filters" role="search">
    <div class="sg-field">
      <label class="sg-label" for="filtro-q">Buscar</label>
      <input class="sg-input" type="search" id="filtro-q" name="q" value="{{ $filtros['q'] }}" placeholder="Nombre o periodo">
    </div>
    <div class="sg-field">
      <label class="sg-label" for="filtro-cohorte">Promoción</label>
      <select class="sg-select" id="filtro-cohorte" name="cohorte">
        <option value="todas">Todas</option>
        @foreach($promociones as $promocion)
          <option value="{{ $promocion['id'] }}" @selected((string) $filtros['cohorte'] === (string) $promocion['id'])>Promoción {{ $promocion['anio'] }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="filtro-estado">Estado</label>
      <select class="sg-select" id="filtro-estado" name="estado">
        <option value="todos">Todos</option>
        <option value="ABIERTA" @selected(strtoupper((string) $filtros['estado']) === 'ABIERTA')>Abierta</option>
        <option value="PROGRAMADA" @selected(strtoupper((string) $filtros['estado']) === 'PROGRAMADA')>Programada</option>
        <option value="CERRADA" @selected(strtoupper((string) $filtros['estado']) === 'CERRADA')>Cerrada</option>
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="filtro-periodo">Periodo</label>
      <select class="sg-select" id="filtro-periodo" name="periodo">
        <option value="todos">Todos</option>
        @foreach($periodos as $periodo)
          <option value="{{ $periodo }}" @selected($filtros['periodo'] === $periodo)>{{ $periodo }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field"><button type="submit" class="sg-button sg-button-secondary">Filtrar</button></div>
  </form>
</section>

<section class="sg-panel" aria-labelledby="sg-tabla-campanias-title">
  <h2 id="sg-tabla-campanias-title" class="sg-section-title">Campañas ({{ $campanias->count() }})</h2>
  @if($campanias->isEmpty())
    <p class="sg-help" style="margin-bottom: var(--sg-space-3);">No hay campañas con esos filtros. Crea la primera con «+ Nueva campaña».</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-tabla-campanias">
        <caption class="sg-sr-only">Campañas registradas</caption>
        <thead>
          <tr>
            <th scope="col">Campaña</th>
            <th scope="col">Periodo</th>
            <th scope="col">Fechas</th>
            <th scope="col">Promociones</th>
            <th scope="col">Estado</th>
            <th scope="col">Respondieron</th>
            <th scope="col"><span class="sg-sr-only">Acción</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($campanias as $c)
            @php
              $estados = ['ABIERTA' => ['Abierta', 'sg-badge-success'], 'PROGRAMADA' => ['Programada', 'sg-badge-warning'], 'CERRADA' => ['Cerrada', '']];
              [$estadoTexto, $estadoClase] = $estados[$c['estado']] ?? [ucfirst(strtolower($c['estado'])), ''];
            @endphp
            <tr>
              <td><strong>{{ $c['nombre'] }}</strong><br><span class="sg-help">{{ $c['tipo'] === 'EMPRESAS' ? 'Para empleadores' : 'Para egresados' }}</span></td>
              <td>{{ $c['periodo'] }}</td>
              <td>{{ \Illuminate\Support\Carbon::parse($c['fecha_inicio'])->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($c['fecha_fin'])->format('d/m/Y') }}</td>
              <td>@foreach($c['promociones'] as $promocion)<span class="sg-badge">{{ $promocion->anio_ingreso }}</span> @endforeach</td>
              <td><span class="sg-badge {{ $estadoClase }}">{{ $estadoTexto }}</span></td>
              <td style="min-width: 150px;">
                @if($c['tipo'] === 'EMPRESAS')
                  <span class="sg-help">En Encuesta empresarial</span>
                @else
                  @if($c['convocados'] === 0)
                    <span class="sg-help">Aún sin invitaciones{{ ($c['por_convocar'] ?? 0) > 0 ? ' · '.$c['por_convocar'].' por convocar' : '' }}</span>
                  @else
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <div class="sg-barra-mini" role="img" aria-label="{{ $c['cobertura'] }}% respondió"><div style="width: {{ min(100, $c['cobertura']) }}%; height: 100%; background: var(--sg-success);"></div></div>
                      <span style="font-size: 12px; font-variant-numeric: tabular-nums;">{{ $c['respondieron'] }} de {{ $c['convocados'] }}</span>
                    </div>
                    @if(($c['por_convocar'] ?? 0) > 0)
                      <span class="sg-help" style="display: block; margin-top: 2px; color: var(--sg-warning);">{{ $c['por_convocar'] }} sin invitar</span>
                    @endif
                  @endif
                @endif
              </td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="verDetalleCampania({{ $c['id'] }})">Ver detalle</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

{{-- Detalle de la campaña, encima de la lista --}}
<dialog class="sg-dialogo" id="dlg-detalle" aria-labelledby="dlg-detalle-titulo">
  <div class="sg-detalle-cuerpo" id="detalle-contenido">
    @if($detalle)
      @include('modules.seguimiento-egresado.partials.campania-detalle', $detalle)
    @endif
  </div>
</dialog>

@include('modules.seguimiento-egresado.partials.campania-formulario', ['promociones' => $promociones, 'periodos' => $periodos])

<script>
  (() => {
    const dlg = document.getElementById('dlg-detalle');
    const contenido = document.getElementById('detalle-contenido');
    dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });

    window.verDetalleCampania = async (id) => {
      contenido.innerHTML = '<p class="sg-help">Cargando…</p>';
      dlg.showModal();
      try {
        const r = await fetch('{{ url('seguimiento-egresado/campanias') }}/' + id + '/detalle', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error();
        contenido.innerHTML = await r.text();
      } catch (_) {
        contenido.innerHTML = '<p class="sg-help">No se pudo cargar el detalle. Cierra esta ventana e inténtalo de nuevo.</p>';
      }
    };

    @if($detalle)
      dlg.showModal();
    @endif
    @if($errors->any())
      if (window.sgDialogo) window.sgDialogo({ tipo: 'error', mensaje: @json($errors->all()) .join('\n') });
    @endif
  })();
</script>
@endsection
