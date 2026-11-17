@extends('modules.seguimiento-egresado.egresado.layout')
@section('title', 'Mi expediente')

@section('content')
@php
  $fecha = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y') : '—';
  $esSeguimiento = $invitacion?->proposito === 'SEGUIMIENTO';
  $pendiente = $invitacion && $invitacion->estado === 'PENDIENTE' && \Carbon\Carbon::parse($invitacion->expira_en)->isFuture()
    && (! $esSeguimiento || $invitacion->estado_campania === 'ABIERTA');
  // Contacto inicial pendiente: lo que se pide con el enlace con el que entró. Las encuestas pendientes de cualquier
  // campaña abierta se ofrecen siempre, aunque haya entrado con otro enlace.
  $contactoPendiente = $pendiente && ! $esSeguimiento;
@endphp
<div>
  <span class="sg-kicker">Mi expediente</span>
  <h1 class="sg-heading">Hola, {{ $egresado->nombres }}</h1>
  <p class="sgs-intro">Aquí mantienes tus datos de contacto y respondes las encuestas de seguimiento.</p>

  <dl class="sgs-ident">
    <div><dt>Nombre</dt><dd>{{ $egresado->nombres }} {{ $egresado->apellido_paterno }} {{ $egresado->apellido_materno }}</dd></div>
    <div><dt>Matrícula</dt><dd>{{ $egresado->codigo_matricula }}</dd></div>
    <div><dt>Programa</dt><dd>{{ $egresado->programa }}</dd></div>
    <div><dt>Promoción</dt><dd>{{ $egresado->codigo_promocion }}</dd></div>
    <div><dt>Periodo de egreso</dt><dd>{{ $egresado->periodo_egreso ?? '—' }}</dd></div>
  </dl>

  @if ($contactoPendiente)
    <section class="sg-panel" aria-labelledby="t-contacto-pendiente">
      <h2 class="sg-section-title" id="t-contacto-pendiente">Completa tus datos de contacto</h2>
      <p>La Escuela necesita tus datos para mantener el vínculo contigo después de tu egreso.</p>
      <div class="sg-actions"><a class="sg-button" href="{{ route('seguimiento-egresado.egresado.contacto') }}">Completar mi contacto</a></div>
    </section>
  @endif

  @if (count($encuestas))
    <section class="sg-panel" aria-labelledby="t-pendiente">
      <h2 class="sg-section-title" id="t-pendiente">{{ count($encuestas) === 1 ? 'Tienes una encuesta por responder' : 'Tienes '.count($encuestas).' encuestas por responder' }}</h2>
      @foreach ($encuestas as $encuesta)
        <p>Campaña: <strong>{{ $encuesta->campania }}</strong> ({{ $encuesta->periodo }}). Puedes responder hasta el {{ $fecha($encuesta->expira_en) }}.</p>
        <div class="sg-actions"><a class="sg-button" href="{{ route('seguimiento-egresado.egresado.encuesta', ['invitacion' => $encuesta->id]) }}">Responder la encuesta</a></div>
      @endforeach
    </section>
  @elseif (! $contactoPendiente && $invitacion && $invitacion->estado === 'COMPLETADA')
    <div class="sg-alert" role="status">Ya completaste esta invitación. Gracias por tu respuesta.</div>
  @endif

  <section class="sg-panel" aria-labelledby="t-contacto">
    <h2 class="sg-section-title" id="t-contacto">Mi contacto actual</h2>
    @if ($contacto)
      <dl class="sgs-ident">
        <div><dt>Correo personal</dt><dd>{{ $contacto['correo_personal'] }}</dd></div>
        <div><dt>Celular / WhatsApp</dt><dd>{{ $contacto['celular_whatsapp'] }}</dd></div>
        <div><dt>Teléfono fijo</dt><dd>{{ $contacto['telefono_fijo'] ?? '—' }}</dd></div>
        <div><dt>Dirección</dt><dd>{{ $contacto['direccion'] ?? '—' }}</dd></div>
        <div><dt>Vigente desde</dt><dd>{{ $fecha($contacto['vigente_desde']) }}</dd></div>
      </dl>
    @else
      <p class="sgs-muted">Aún no tenemos tus datos de contacto.</p>
    @endif
    <div class="sg-actions"><a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.egresado.contacto') }}">{{ $contacto ? 'Actualizar mi contacto' : 'Registrar mi contacto' }}</a></div>
  </section>

  <section class="sg-panel" aria-labelledby="t-hist">
    <h2 class="sg-section-title" id="t-hist">Mi historial de seguimiento</h2>
    @if (count($campanias))
      <p>Has sido invitado a {{ count($campanias) }} {{ count($campanias) === 1 ? 'campaña' : 'campañas' }}; respondiste {{ collect($campanias)->where('estado_respuesta', 'RESPONDIDA')->count() }}.</p>
    @else
      <p class="sgs-muted">Todavía no hay campañas de seguimiento en tu historial.</p>
    @endif
    <div class="sg-actions"><a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.egresado.historial') }}">Ver mi historial</a></div>
  </section>
</div>
@endsection
