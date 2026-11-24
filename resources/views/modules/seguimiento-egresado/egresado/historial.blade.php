@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', 'Mi historial')

@section('content')
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
  $fechaHora = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
  $estados = [
    'RESPONDIDA' => ['Respondida', 'sg-badge sg-badge-success'],
    'PENDIENTE' => ['Pendiente', 'sg-badge sg-badge-warning'],
    'NO_RESPONDIO' => ['No respondida', 'sg-badge'],
  ];
@endphp
<div>
  <span class="sg-kicker">Mi expediente</span>
  <h1 class="sg-heading">Mi historial de seguimiento</h1>
  <p class="sgs-intro">Aquí ves tus respuestas de cada campaña.</p>

  <section class="sg-panel" aria-labelledby="t-camp">
    <h2 class="sg-section-title" id="t-camp">Campañas de seguimiento</h2>
    @if (count($campanias))
      <div class="sg-table-wrap">
        <table class="sg-table sgs-wide">
          <thead><tr><th scope="col">Campaña</th><th scope="col">Periodo</th><th scope="col">Estado</th><th scope="col">Respondida el</th><th scope="col">Situación laboral</th><th scope="col">Detalle</th></tr></thead>
          <tbody>
            @foreach ($campanias as $c)
              @php [$texto, $clase] = $estados[$c['estado_respuesta']]; @endphp
              <tr>
                <td>{{ $c['campania'] }}</td>
                <td>{{ $c['periodo'] }}</td>
                <td><span class="{{ $clase }}">{{ $texto }}</span></td>
                <td>{{ $fechaHora($c['fecha_respuesta']) }}</td>
                <td>{{ $c['situacion_laboral'] ? ucfirst(strtolower(str_replace('_', ' ', $c['situacion_laboral']))) : '—' }}</td>
                <td>
                  @if ($c['id_medicion'])
                    <a href="{{ route('seguimiento-egresado.egresado.historial.detalle', $c['id_medicion']) }}">Ver respuesta</a>
                  @elseif ($c['estado_respuesta'] === 'PENDIENTE')
                    <a href="{{ route('seguimiento-egresado.egresado.encuesta') }}">Responder</a>
                  @else
                    —
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="sgs-muted">Todavía no hay campañas en tu historial.</p>
    @endif
  </section>

  <section class="sg-panel" aria-labelledby="t-cont">
    <h2 class="sg-section-title" id="t-cont">Cambios de contacto</h2>
    @if (count($contactos))
      <div class="sg-table-wrap">
        <table class="sg-table sgs-wide">
          <thead><tr><th scope="col">Correo personal</th><th scope="col">Celular</th><th scope="col">Dirección</th><th scope="col">Vigente desde</th><th scope="col">Hasta</th><th scope="col">Origen</th></tr></thead>
          <tbody>
            @foreach ($contactos as $v)
              <tr>
                <td>{{ $v['correo_personal'] }}</td><td>{{ $v['celular_whatsapp'] }}</td><td>{{ $v['direccion'] ?? '—' }}</td>
                <td>{{ $fechaHora($v['vigente_desde']) }}</td>
                <td>{{ $v['vigente_hasta'] ? $fechaHora($v['vigente_hasta']) : 'Vigente' }}</td>
                <td>{{ $v['origen_registro'] === 'SECRETARIA' ? 'Secretaría' : 'Formulario' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="sgs-muted">Aún no hay datos de contacto registrados.</p>
    @endif
  </section>

  <section class="sg-panel" aria-labelledby="t-form">
    <h2 class="sg-section-title" id="t-form">Formación posterior</h2>
    @if (count($formaciones))
      <div class="sg-table-wrap">
        <table class="sg-table sgs-wide">
          <thead><tr><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Área</th><th scope="col">Institución</th><th scope="col">Inicio</th><th scope="col">Fin</th></tr></thead>
          <tbody>
            @foreach ($formaciones as $f)
              <tr><td>{{ $f['tipo'] }}</td><td>{{ $f['estado'] }}</td><td>{{ $f['area_formacion'] ?? '—' }}</td><td>{{ $f['institucion'] ?? '—' }}</td><td>{{ $fecha($f['fecha_inicio']) }}</td><td>{{ $fecha($f['fecha_fin']) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="sgs-muted">No has declarado formación posterior.</p>
    @endif
  </section>
</div>
@endsection
