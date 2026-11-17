@extends('modules.seguimiento-egresado.layout')
@section('title', 'Contacto inicial')
@section('section_name', 'Contacto inicial')

@section('content')
@php
  $mailer = config('mail.default');
  $correoActivo = config('seguimiento-egresado.correo.enviar');
  $redirigidoA = config('seguimiento-egresado.correo.redirigir_a');
  $sinEntregaReal = in_array($mailer, ['log', 'array'], true);
@endphp

<section class="sg-heading" aria-labelledby="sg-contacto-inicial-title">
  <div class="sg-kicker">MÓDULO DE SEGUIMIENTO AL EGRESADO · PADRÓN OFICIAL</div>
  <h1 id="sg-contacto-inicial-title">Contacto inicial de egresados</h1>
  <p>Genera el enlace personal con el que cada egresado deja sus datos de contacto. Sin esos datos no se puede convocar a las campañas de seguimiento.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px;" href="{{ route('seguimiento-egresado.padron') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'users'])
      <span>Volver al padrón</span>
    </a>
  </div>
</section>

@if(session('resumen_contacto'))
  <div class="sg-alert" role="status" style="margin-bottom: var(--sg-space-4); border-left-color: var(--sg-success); background: #e7f7f0;">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <p>{{ session('resumen_contacto') }}</p>
  </div>
@endif

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Revisa lo siguiente:</strong>
      <ul style="margin: 4px 0 0; padding-left: 18px; font-size: 13px;">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

@if(! $correoActivo || $sinEntregaReal || $redirigidoA)
  <div class="sg-alert sg-alert-warning" role="note" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
    <p>
      @if(! $correoActivo)
        El envío por correo está desactivado: los enlaces solo se muestran aquí.
      @elseif($sinEntregaReal)
        El correo no está conectado a un servidor de envío: los enlaces solo se muestran aquí y hay que entregarlos por otro medio.
      @endif
      @if($redirigidoA)
        Todos los correos se redirigen a <strong>{{ $redirigidoA }}</strong>, no llegan a los egresados.
      @endif
    </p>
  </div>
@endif

@if(session('enlaces_contacto'))
  <section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-enlaces-contacto-titulo">
    <h2 class="sg-section-title" id="sg-enlaces-contacto-titulo">Enlaces personales de contacto inicial</h2>
    <p class="sg-help">Cada egresado entra con su enlace. Cópialos ahora: por seguridad solo se muestran una vez y no se pueden recuperar después. Si se pierde, genera uno nuevo.</p>
    <div class="sg-table-wrap">
      <table class="sg-table">
        <thead><tr><th scope="col">Egresado</th><th scope="col">Matrícula</th><th scope="col">Correo</th><th scope="col">Enlace personal</th><th scope="col">Copiar</th></tr></thead>
        <tbody>
          @foreach(session('enlaces_contacto') as $enlace)
            <tr>
              <td>{{ $enlace['egresado'] }}</td>
              <td>{{ $enlace['matricula'] }}</td>
              <td><span class="sg-badge sg-badge-{{ $enlace['correo_insignia'] }}">{{ $enlace['correo_texto'] }}</span></td>
              <td><input class="sg-input" type="text" readonly value="{{ $enlace['url'] }}" aria-label="Enlace de {{ $enlace['egresado'] }}" onfocus="this.select()"></td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="navigator.clipboard.writeText(this.closest('tr').querySelector('input').value); this.textContent = 'Copiado';">Copiar</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endif

<section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-invitar-cohorte-titulo">
  <h2 class="sg-section-title" id="sg-invitar-cohorte-titulo">Invitar a una cohorte</h2>
  <p class="sg-help" style="margin-bottom: var(--sg-space-4);">Genera un enlace para cada egresado de la cohorte que aún no dejó su contacto. Quienes ya tienen un enlace pendiente se omiten.</p>
  <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.cohorte') }}" class="sg-filters">
    @csrf
    <div class="sg-field">
      <label class="sg-label" for="ci-cohorte">Cohorte</label>
      <select class="sg-select" id="ci-cohorte" name="cohorte" required>
        <option value="">Elige una cohorte</option>
        @foreach($cohortes as $cohorte)
          <option value="{{ $cohorte->id }}" @selected((string) $filtros['cohorte'] === (string) $cohorte->id)>{{ $cohorte->codigo_promocion }} · ingreso {{ $cohorte->anio_ingreso }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <button type="submit" class="sg-button" onclick="return confirm('Se generará un enlace para cada egresado de la cohorte que aún no dejó su contacto. ¿Continuar?');">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
        <span>Invitar a la cohorte</span>
      </button>
    </div>
  </form>
</section>

<section class="sg-panel" aria-labelledby="sg-egresados-contacto-titulo">
  <h2 class="sg-section-title" id="sg-egresados-contacto-titulo">Egresados</h2>
  <form method="GET" action="{{ route('seguimiento-egresado.padron.contacto-inicial') }}" class="sg-filters" style="margin-bottom: var(--sg-space-4);">
    <div class="sg-field">
      <label class="sg-label" for="ci-filtro-cohorte">Cohorte</label>
      <select class="sg-select" id="ci-filtro-cohorte" name="cohorte">
        <option value="">Todas</option>
        @foreach($cohortes as $cohorte)
          <option value="{{ $cohorte->id }}" @selected((string) $filtros['cohorte'] === (string) $cohorte->id)>{{ $cohorte->codigo_promocion }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="ci-filtro-estado">Contacto</label>
      <select class="sg-select" id="ci-filtro-estado" name="estado">
        <option value="sin_contacto" @selected($filtros['estado'] === 'sin_contacto')>Sin contacto registrado</option>
        <option value="con_contacto" @selected($filtros['estado'] === 'con_contacto')>Con contacto registrado</option>
        <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="ci-filtro-q">Buscar</label>
      <input class="sg-input" id="ci-filtro-q" type="search" name="q" maxlength="80" value="{{ $filtros['q'] }}" placeholder="Nombre, matrícula o documento">
    </div>
    <div class="sg-field">
      <button type="submit" class="sg-button sg-button-secondary">Filtrar</button>
    </div>
  </form>

  @if($egresados->isEmpty())
    <p class="sg-help">No hay egresados con esos filtros.</p>
  @else
    <p class="sg-help" style="margin-bottom: var(--sg-space-3);">
      @if($total > $limite)Se muestran los primeros {{ $limite }} de {{ $total }}. Usa los filtros para acotar.@else{{ $total }} {{ $total === 1 ? 'egresado' : 'egresados' }}.@endif
    </p>
    <div class="sg-table-wrap">
      <table class="sg-table">
        <thead>
          <tr>
            <th scope="col">Egresado</th>
            <th scope="col">Matrícula</th>
            <th scope="col">Cohorte</th>
            <th scope="col">Contacto</th>
            <th scope="col">Enlace de contacto inicial</th>
            <th scope="col"><span class="sg-sr-only">Acción</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($egresados as $egresado)
            @php
              $invitacion = $invitaciones->get($egresado->id);
              $vigente = $invitacion && $invitacion->estado === 'PENDIENTE' && \Illuminate\Support\Carbon::parse($invitacion->expira_en)->isFuture();
              if (! $invitacion) { [$texto, $insignia] = ['Sin enlace', '']; }
              elseif ($invitacion->estado === 'COMPLETADA') { [$texto, $insignia] = ['Completado', 'sg-badge-success']; }
              elseif ($invitacion->estado === 'PENDIENTE' && ! $vigente) { [$texto, $insignia] = ['Vencido', 'sg-badge-warning']; }
              elseif ($invitacion->estado === 'PENDIENTE') { [$texto, $insignia] = ['Pendiente', 'sg-badge-warning']; }
              else { [$texto, $insignia] = ['Anulado', '']; }
              $correo = $invitacion && $invitacion->estado === 'PENDIENTE'
                  ? ($invitacion->enviado_en ? 'Correo enviado' : ($invitacion->entrega_fallida ? 'Falló el envío' : 'Correo no enviado'))
                  : null;
            @endphp
            <tr>
              <td>{{ $egresado->nombre_completo }}</td>
              <td>{{ $egresado->codigo_matricula }}</td>
              <td>{{ $egresado->cohorte?->codigo_promocion }}</td>
              <td>
                @if($egresado->contactoVigente)
                  <span class="sg-badge sg-badge-success">Registrado</span>
                @else
                  <span class="sg-badge">Pendiente</span>
                @endif
              </td>
              <td>
                <span class="sg-badge {{ $insignia }}">{{ $texto }}</span>
                @if($correo)<span class="sg-help" style="display: block; margin-top: 4px;">{{ $correo }}</span>@endif
              </td>
              <td>
                <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id) }}">
                  @csrf
                  <input type="hidden" name="cohorte" value="{{ $filtros['cohorte'] }}">
                  <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                  <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                  <button type="submit" class="sg-button sg-button-secondary">{{ $vigente ? 'Enlace nuevo' : 'Generar enlace' }}</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
