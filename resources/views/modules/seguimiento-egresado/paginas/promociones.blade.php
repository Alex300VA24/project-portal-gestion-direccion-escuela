@extends('modules.seguimiento-egresado.layout')
@section('title', 'Padrón y promociones')
@section('section_name', 'Padrón y promociones')

@push('styles')
<style>
  .sg-tabla-promociones th, .sg-tabla-promociones td { white-space: nowrap; }
  .sg-pasos { display: flex; gap: 6px; align-items: center; }
  .sg-paso-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--sg-hover); color: var(--sg-muted); white-space: nowrap; }
  .sg-paso-chip.hecho { background: var(--sg-success-soft); color: var(--sg-success); }
  .sg-paso-chip.toca { background: var(--sg-soft); color: var(--sg-accent); box-shadow: inset 0 0 0 1.5px var(--sg-accent); }
  .sg-barra { height: 8px; border-radius: 4px; background: var(--sg-border); overflow: hidden; min-width: 90px; }
  .sg-barra > div { height: 100%; background: var(--sg-success); }
</style>
@endpush

@section('content')
<section class="sg-heading" aria-labelledby="sg-promociones-title">
  <div class="sg-kicker">PADRÓN Y PROMOCIONES</div>
  <h1 id="sg-promociones-title">Padrón y promociones</h1>
  <p>Cada promoción avanza por cuatro pasos: <strong>registrarla, cargar a sus alumnos, enviarles el enlace de contacto y ver quién lo completó</strong>. Elige una para continuar donde la dejaste.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <button type="button" class="sg-button" onclick="abrirNuevaPromocion()">+ Nueva promoción</button>
    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.padron.consulta') }}">Consultar alumnos y grados</a>
  </div>
</section>

@if($avances->isEmpty())
  <section class="sg-panel" aria-labelledby="sg-sin-promociones">
    <h2 class="sg-section-title" id="sg-sin-promociones">Aún no hay promociones</h2>
    <p class="sg-help" style="margin-bottom: var(--sg-space-4);">Empieza registrando la primera: su año de ingreso y su total de ingresantes. Después cargarás a sus alumnos.</p>
    <button type="button" class="sg-button" onclick="abrirNuevaPromocion()">+ Nueva promoción</button>
  </section>
@else
  <section class="sg-panel" aria-labelledby="sg-lista-promociones">
    <h2 class="sg-section-title" id="sg-lista-promociones">Promociones</h2>
    <div class="sg-table-wrap">
      <table class="sg-table sg-tabla-promociones">
        <caption class="sg-sr-only">Promociones y el paso en el que va cada una</caption>
        <thead>
          <tr>
            <th scope="col">Promoción</th>
            <th scope="col">Plan de estudios</th>
            <th scope="col" class="sg-numeric">Ingresantes</th>
            <th scope="col" class="sg-numeric">Alumnos cargados</th>
            <th scope="col">Pasos</th>
            <th scope="col">Contacto completado</th>
            <th scope="col"><span class="sg-sr-only">Acción</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($avances as $fila)
            @php
              $promocion = $fila['promocion'];
              $paso = $fila['paso'];
              $porcentaje = $fila['total'] > 0 ? (int) round($fila['completaron'] * 100 / $fila['total']) : 0;
              $accion = $fila['terminada'] ? 'Ver' : [2 => 'Cargar alumnos →', 3 => 'Enviar enlaces →', 4 => 'Ver seguimiento →'][$paso];
              $chips = [
                  [1, 'Promoción', true],
                  [2, 'Alumnos', $fila['total'] > 0],
                  [3, 'Enlaces', $paso >= 4],
                  [4, 'Contacto', $fila['terminada']],
              ];
            @endphp
            <tr>
              <td><strong>Promoción {{ $promocion->anio_ingreso }}</strong></td>
              <td>{{ $promocion->planCurricular?->codigo_version ?? '—' }}</td>
              <td class="sg-numeric">{{ $promocion->total_ingresantes }}</td>
              <td class="sg-numeric">{{ $fila['total'] }}</td>
              <td>
                <div class="sg-pasos" aria-label="Avance por pasos">
                  @foreach($chips as [$numero, $texto, $hecho])
                    <span class="sg-paso-chip {{ $hecho ? 'hecho' : ($numero === $paso && ! $fila['terminada'] ? 'toca' : '') }}">{{ $hecho ? '✓' : $numero }} {{ $texto }}</span>
                  @endforeach
                </div>
              </td>
              <td style="min-width: 150px;">
                @if($fila['total'] === 0)
                  <span class="sg-help">—</span>
                @else
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <div class="sg-barra" role="img" aria-label="{{ $porcentaje }}% completó su contacto"><div style="width: {{ $porcentaje }}%;"></div></div>
                    <span style="font-size: 12px; font-variant-numeric: tabular-nums; white-space: nowrap;">{{ $fila['completaron'] }} de {{ $fila['total'] }}</span>
                  </div>
                  @if($fila['vencidos'] > 0)<span class="sg-badge sg-badge-danger" style="margin-top: 4px;">{{ $fila['vencidos'] }} sin completar tras la fecha</span>@endif
                @endif
              </td>
              <td>
                <a class="sg-button {{ $fila['terminada'] ? 'sg-button-secondary' : '' }}" href="{{ route('seguimiento-egresado.padron.promocion', $promocion->id) }}#paso-{{ $fila['terminada'] ? 4 : $paso }}">{{ $accion }}</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endif

@include('modules.seguimiento-egresado.partials.promocion-formularios', ['planes' => $planes])
@endsection
