@extends('modules.seguimiento-egresado.layout')
@section('title', 'Inicio')
@section('section_name', 'Inicio')
@section('content')
<section class="sg-heading sg-home-heading" aria-labelledby="sg-home-title" data-esquema="{{ $schema ?? 'seguimiento_egresado' }}">
  <h1 id="sg-home-title">Seguimiento al Egresado</h1>
  <p>Consulta expedientes, organiza campañas y revisa la formación profesional.</p>
  @guest
    <p class="sg-help">Las opciones de Secretaría y Dirección requieren iniciar sesión en el portal. Los egresados entran con el enlace personal que recibieron de la Escuela.</p>
  @endguest
</section>
@isset($avisos)
@php $fechaCorta = fn ($f) => \Carbon\CarbonImmutable::parse($f)->format('d/m/Y'); @endphp
<section class="sg-access-group" aria-labelledby="sg-group-avisos">
  <div class="sg-section-title">
    <h2 id="sg-group-avisos">Por cerrar</h2>
    @if ($avisos['total'] > count($avisos['avisos']))
      <a class="sg-link" href="{{ route('seguimiento-egresado.campanias') }}">Ver todas las campañas ({{ $avisos['total'] }})</a>
    @endif
  </div>
  @forelse ($avisos['avisos'] as $aviso)
    <article @class(['sg-aviso', 'sg-aviso-urgente' => $aviso['urgente']])>
      <div class="sg-aviso-info">
        <h3>{{ $aviso['titulo'] }}</h3>
        <p>{{ $aviso['subtitulo'] }}</p>
        <p class="sg-aviso-cierre">
          @if ($aviso['dias'] === 0) Cierra hoy @elseif ($aviso['dias'] === 1) Cierra mañana @else Cierra en {{ $aviso['dias'] }} días @endif
          · {{ $fechaCorta($aviso['cierra']) }}
        </p>
      </div>
      <div class="sg-aviso-avance">
        @if ($aviso['total'] > 0)
          <div class="sg-aviso-barra" role="img" aria-label="{{ $aviso['respondieron'] }} de {{ $aviso['total'] }}"><span style="width: {{ $aviso['porcentaje'] }}%"></span></div>
          <p><strong>{{ $aviso['respondieron'] }} de {{ $aviso['total'] }}</strong> · {{ $aviso['faltan'] === 1 ? 'falta 1' : 'faltan '.$aviso['faltan'] }}</p>
        @else
          <p>Aún no se generan {{ $aviso['unidad'] }}.</p>
        @endif
      </div>
      <a class="sg-button sg-button-secondary" href="{{ $aviso['enlace'] }}">{{ $aviso['boton'] }}</a>
    </article>
  @empty
    <div class="sg-panel sg-aviso-vacio">
      <p><strong>No hay campañas abiertas.</strong> Cuando abras una, aquí verás cuándo cierra y cuántas respuestas faltan.</p>
      @if ($puedeAdministrar)<a class="sg-button" href="{{ route('seguimiento-egresado.campanias') }}">Ir a campañas</a>@endif
    </div>
  @endforelse
</section>
@endisset
@foreach ([
  ['registro', 'Registro y seguimiento', [
    ['padron', 'users', 'Padrón y promociones', 'Alumnos y egresados organizados por promoción.'],
    ['campanias', 'mail', 'Campañas e invitaciones', 'Periodos, convocatorias y cobertura del seguimiento.'],
  ]],
  ['evaluacion', 'Evaluación y resultados', [
    ['empresas', 'building', 'Encuesta empresarial', 'Opinión de los empleadores sobre la formación profesional.'],
    ['indicadores', 'chart', 'Indicadores', 'Indicadores I1 a I6 por promoción y periodo de evaluación.'],
    ['institucional', 'clipboard', 'Evaluación institucional', 'Objetivos educacionales y acciones de mejora.'],
  ]],
] as [$group, $heading, $items])
<section class="sg-access-group" aria-labelledby="sg-group-{{ $group }}">
  <div class="sg-section-title"><h2 id="sg-group-{{ $group }}">{{ $heading }}</h2></div>
  <div class="sg-tiles">
    @foreach ($items as [$route, $icon, $title, $description])
      <a class="sg-tile" href="{{ route('seguimiento-egresado.'.$route) }}" aria-labelledby="sg-title-{{ $route }}" aria-describedby="sg-description-{{ $route }}">
        <span class="sg-icon">@include('modules.seguimiento-egresado.partials.icon', ['name' => $icon])</span>
        <div class="sg-tile-copy">
          <h3 id="sg-title-{{ $route }}">{{ $title }}</h3>
          <p id="sg-description-{{ $route }}">{{ $description }}</p>
        </div>
        <span class="sg-tile-arrow">@include('modules.seguimiento-egresado.partials.icon', ['name' => 'arrow'])</span>
      </a>
    @endforeach
  </div>
</section>
@endforeach
@endsection
