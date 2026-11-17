@extends('modules.seguimiento-egresado.layout')
@section('title', 'Inicio')
@section('section_name', 'Inicio')
@section('content')
<section class="sg-heading sg-home-heading" aria-labelledby="sg-home-title">
  <h1 id="sg-home-title">Seguimiento al Egresado</h1>
  <p>Consulta expedientes, organiza campañas y revisa la formación profesional.</p>
  @guest
    <p class="sg-help">Las opciones de Secretaría y Dirección requieren iniciar sesión en el portal. Los egresados entran con el enlace personal que recibieron de la Escuela.</p>
  @endguest
</section>
@foreach ([
  ['registro', 'Registro y seguimiento', [
    ['padron', 'users', 'Padrón y cohortes', 'Expedientes de egresados organizados por cohorte.'],
    ['campanias', 'mail', 'Campañas e invitaciones', 'Periodos, convocatorias y cobertura del seguimiento.'],
  ]],
  ['evaluacion', 'Evaluación y resultados', [
    ['empresas', 'building', 'Encuesta empresarial', 'Opinión de los empleadores sobre la formación profesional.'],
    ['indicadores', 'chart', 'Indicadores', 'Indicadores I1 a I6 por cohorte y periodo de evaluación.'],
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
<p class="sg-help sg-module-info">{{ $module ?? 'Seguimiento al Egresado' }} · esquema de datos <code>{{ $schema ?? 'seguimiento_egresado' }}</code></p>
@endsection
