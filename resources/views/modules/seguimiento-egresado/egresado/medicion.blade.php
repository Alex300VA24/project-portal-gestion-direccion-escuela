@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', 'Mi respuesta')

@section('content')
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
  $sino = fn ($v) => $v === null ? '—' : ($v ? 'Sí' : 'No');
  $ingles = ['NINGUNO' => 'Ninguno', 'BASICO' => 'Básico', 'INTERMEDIO' => 'Intermedio', 'AVANZADO' => 'Avanzado'];
  $region = ['LA_LIBERTAD' => 'La Libertad', 'OTRA_REGION' => 'Otra región del Perú'];
  $habilidad = fn (array $h) => $h['codigo'] === 'OTRA' && $h['detalle_otro'] ? "{$h['nombre']}: {$h['detalle_otro']}" : $h['nombre'];
@endphp
<div>
  <span class="sg-kicker">Mi historial</span>
  <h1 class="sg-heading">Mi respuesta · {{ $m['campania'] }}</h1>
  <p class="sgs-intro">Periodo {{ $m['periodo'] }}. Respondida el {{ $fecha($m['fecha_respuesta']) }}.</p>

  <section class="sg-panel" aria-labelledby="t-lab">
    <h2 class="sg-section-title" id="t-lab">Situación laboral</h2>
    <dl class="sgs-ident">
      <div><dt>Situación</dt><dd>{{ $m['situacion'] }}</dd></div>
      <div><dt>País de residencia</dt><dd>{{ $m['pais_residencia'] }}</dd></div>
      <div><dt>Ciudad de residencia</dt><dd>{{ $m['ciudad_residencia'] ?? '—' }}</dd></div>
      <div><dt>Área profesional</dt><dd>{{ $m['area_profesional'] ?? '—' }}</dd></div>
      @if ($m['situacion_codigo'] === 'TRABAJA')
        <div><dt>Trabajo relacionado con la carrera</dt><dd>{{ $sino($m['trabajo_relacionado']) }}</dd></div>
        <div><dt>Empleador o cliente</dt><dd>{{ $m['nombre_empleador_cliente'] ?? '—' }}</dd></div>
        <div><dt>Tipo de vínculo</dt><dd>{{ $m['modalidad_laboral'] ?? '—' }}</dd></div>
        <div><dt>Modalidad de trabajo</dt><dd>{{ $m['modalidad_trabajo'] ?? '—' }}</dd></div>
        <div><dt>Sector</dt><dd>{{ $m['sector'] ?? '—' }}</dd></div>
        <div><dt>País del empleo</dt><dd>{{ $m['pais_empleador'] ?? '—' }}</dd></div>
        <div><dt>Ubicación en el Perú</dt><dd>{{ $region[$m['region_empleo']] ?? '—' }}</dd></div>
        <div><dt>Autorizó contactar a su empresa</dt><dd>{{ $sino($m['autoriza_contacto_empresa']) }}</dd></div>
        @if ($m['contacto_jefe'])<div><dt>Contacto del jefe</dt><dd>{{ $m['contacto_jefe'] }}</dd></div>@endif
      @endif
      <div><dt>Nivel de inglés</dt><dd>{{ $ingles[$m['nivel_ingles']] ?? '—' }}</dd></div>
      <div><dt>Otros idiomas</dt><dd>{{ $m['otros_idiomas'] ?? '—' }}</dd></div>
    </dl>
  </section>

  <section class="sg-panel" aria-labelledby="t-sat">
    <h2 class="sg-section-title" id="t-sat">Satisfacción y habilidades</h2>
    <dl class="sgs-ident">
      <div><dt>Satisfacción con la formación</dt><dd>{{ $m['satisfaccion'] ?? '—' }}</dd></div>
      <div><dt>Habilidades con formación insuficiente</dt><dd>{{ count($m['brechas']) ? collect($m['brechas'])->map($habilidad)->implode(', ') : '—' }}</dd></div>
      <div><dt>Fortalezas</dt><dd>{{ count($m['fortalezas']) ? collect($m['fortalezas'])->map($habilidad)->implode(', ') : '—' }}</dd></div>
      <div><dt>Comentario</dt><dd>{{ $m['comentario'] ?? '—' }}</dd></div>
    </dl>
  </section>

  <section class="sg-panel" aria-labelledby="t-for">
    <h2 class="sg-section-title" id="t-for">Formación posterior declarada</h2>
    @if (count($m['formaciones']))
      <div class="sg-table-wrap">
        <table class="sg-table sgs-wide">
          <thead><tr><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Área</th><th scope="col">Institución</th><th scope="col">Inicio</th><th scope="col">Fin</th></tr></thead>
          <tbody>
            @foreach ($m['formaciones'] as $f)
              <tr><td>{{ $f['tipo'] }}</td><td>{{ $f['estado'] }}</td><td>{{ $f['area_formacion'] ?? '—' }}</td><td>{{ $f['institucion'] ?? '—' }}</td><td>{{ $fecha($f['fecha_inicio']) }}</td><td>{{ $fecha($f['fecha_fin']) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="sgs-muted">No declaraste formación posterior en esta respuesta.</p>
    @endif
  </section>

  <div class="sg-actions"><a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.egresado.historial') }}">Volver al historial</a></div>
</div>
@endsection
