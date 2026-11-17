@extends('modules.seguimiento-egresado.layout')
@section('title', 'Contacto inicial')
@section('section_name', 'Contacto inicial')

@section('content')
@php
  $fechaCorta = fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
@endphp
<section class="sg-heading" aria-labelledby="sg-contacto-inicial-title">
  <div class="sg-kicker">PADRÓN Y PROMOCIONES · PASO 3</div>
  <h1 id="sg-contacto-inicial-title">Contacto inicial</h1>
  <p>Cada alumno recibe en su correo institucional un enlace personal para dejar sus datos de contacto. <strong>Es obligatorio:</strong> sin ese paso no se puede confirmar su egreso.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px;" href="{{ route('seguimiento-egresado.padron') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'users'])
      <span>Volver al padrón</span>
    </a>
  </div>
</section>

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

@if(session('enlaces_contacto'))
  <section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-enlaces-contacto-titulo">
    <h2 class="sg-section-title" id="sg-enlaces-contacto-titulo">Enlaces personales de contacto inicial</h2>
    <p class="sg-help">Cada alumno entra con su enlace. Cópialos ahora: por seguridad solo se muestran una vez y no se pueden recuperar después. Si se pierde, genera uno nuevo.</p>
    <div class="sg-table-wrap">
      <table class="sg-table">
        <thead><tr><th scope="col">Alumno</th><th scope="col">Matrícula</th><th scope="col">Correo</th><th scope="col">Enlace personal</th><th scope="col">Copiar</th></tr></thead>
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
  <h2 class="sg-section-title" id="sg-invitar-cohorte-titulo">1. Enviar el contacto inicial a una promoción</h2>
  <p class="sg-help" style="margin-bottom: var(--sg-space-4);">Se envía un enlace a cada alumno de la promoción que aún no dejó su contacto. El enlace funciona hasta la fecha límite que elijas, y el correo y el formulario le recuerdan que es obligatorio.</p>
  <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.cohorte') }}" class="sg-filters"
        data-sg-confirmar="Se enviará un enlace a cada alumno de la promoción que aún no dejó su contacto. ¿Continuar?">
    @csrf
    <div class="sg-field">
      <label class="sg-label" for="ci-cohorte">Promoción</label>
      <select class="sg-select" id="ci-cohorte" name="cohorte" required>
        <option value="">Elige una promoción</option>
        @foreach($cohortes as $cohorte)
          <option value="{{ $cohorte->id }}" @selected((string) ($filtros['cohorte'] ?: old('cohorte')) === (string) $cohorte->id)>Promoción {{ $cohorte->anio_ingreso }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="ci-fecha-limite">Fecha límite para completarlo</label>
      <input class="sg-input" id="ci-fecha-limite" type="date" name="fecha_limite" required
             value="{{ old('fecha_limite', $fechaLimiteSugerida) }}" min="{{ now()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}">
    </div>
    <div class="sg-field">
      <button type="submit" class="sg-button">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
        <span>Enviar a la promoción</span>
      </button>
    </div>
  </form>
</section>

<section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-seguimiento-titulo">
  <h2 class="sg-section-title" id="sg-seguimiento-titulo">2. Quién ya completó su contacto</h2>
  <p class="sg-help" style="margin-bottom: var(--sg-space-3);">Revisa este cuadro al llegar la fecha límite. Quien no completó su contacto no puede egresar todavía: usa «Enlace nuevo» para darle otra oportunidad.</p>
  @if($seguimiento->isEmpty())
    <p class="sg-help">Aún no hay alumnos registrados. Carga a los alumnos en el padrón para empezar.</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table">
        <caption class="sg-sr-only">Avance del contacto inicial por promoción</caption>
        <thead>
          <tr>
            <th scope="col">Promoción</th>
            <th scope="col">Avance</th>
            <th scope="col" class="sg-numeric">Completaron</th>
            <th scope="col" class="sg-numeric">En plazo</th>
            <th scope="col" class="sg-numeric">Vencieron</th>
            <th scope="col" class="sg-numeric">Sin enlace</th>
            <th scope="col">Fecha límite</th>
          </tr>
        </thead>
        <tbody>
          @foreach($seguimiento as $fila)
            @php $porcentaje = $fila['total'] > 0 ? (int) round($fila['completaron'] * 100 / $fila['total']) : 0; @endphp
            <tr>
              <td><strong>Promoción {{ $fila['promocion']->anio_ingreso }}</strong></td>
              <td style="min-width: 150px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <div style="flex: 1; height: 8px; border-radius: 4px; background: var(--sg-border); overflow: hidden;" role="img" aria-label="{{ $porcentaje }}% completó su contacto">
                    <div style="width: {{ $porcentaje }}%; height: 100%; background: var(--sg-success);"></div>
                  </div>
                  <span style="font-size: 12px; font-variant-numeric: tabular-nums;">{{ $fila['completaron'] }} de {{ $fila['total'] }}</span>
                </div>
              </td>
              <td class="sg-numeric"><span class="sg-badge sg-badge-success">{{ $fila['completaron'] }}</span></td>
              <td class="sg-numeric"><span class="sg-badge sg-badge-warning">{{ $fila['pendientes'] }}</span></td>
              <td class="sg-numeric"><span class="sg-badge {{ $fila['vencidos'] > 0 ? 'sg-badge-danger' : '' }}">{{ $fila['vencidos'] }}</span></td>
              <td class="sg-numeric"><span class="sg-badge">{{ $fila['sin_enlace'] }}</span></td>
              <td>{{ $fila['fecha_limite'] ? $fechaCorta($fila['fecha_limite']) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

<section class="sg-panel" aria-labelledby="sg-egresados-contacto-titulo">
  <h2 class="sg-section-title" id="sg-egresados-contacto-titulo">3. Alumnos</h2>
  <form method="GET" action="{{ route('seguimiento-egresado.padron.contacto-inicial') }}" class="sg-filters" style="margin-bottom: var(--sg-space-4);">
    <div class="sg-field">
      <label class="sg-label" for="ci-filtro-cohorte">Promoción</label>
      <select class="sg-select" id="ci-filtro-cohorte" name="cohorte">
        <option value="">Todas</option>
        @foreach($cohortes as $cohorte)
          <option value="{{ $cohorte->id }}" @selected((string) $filtros['cohorte'] === (string) $cohorte->id)>Promoción {{ $cohorte->anio_ingreso }}</option>
        @endforeach
      </select>
    </div>
    <div class="sg-field">
      <label class="sg-label" for="ci-filtro-estado">Mostrar</label>
      <select class="sg-select" id="ci-filtro-estado" name="estado">
        <option value="sin_contacto" @selected($filtros['estado'] === 'sin_contacto')>Los que aún no completaron</option>
        <option value="vencido" @selected($filtros['estado'] === 'vencido')>Pasó la fecha límite sin completar</option>
        <option value="con_contacto" @selected($filtros['estado'] === 'con_contacto')>Los que ya completaron</option>
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
    <p class="sg-help">No hay alumnos con esos filtros.</p>
  @else
    <p class="sg-help" style="margin-bottom: var(--sg-space-3);">
      @if($total > $limite)Se muestran los primeros {{ $limite }} de {{ $total }}. Usa los filtros para acotar.@else{{ $total }} {{ $total === 1 ? 'alumno' : 'alumnos' }}.@endif
    </p>
    <div class="sg-table-wrap">
      <table class="sg-table">
        <thead>
          <tr>
            <th scope="col">Alumno</th>
            <th scope="col">Matrícula</th>
            <th scope="col">Promoción</th>
            <th scope="col">Contacto inicial</th>
            <th scope="col"><span class="sg-sr-only">Acción</span></th>
          </tr>
        </thead>
        <tbody>
          @foreach($egresados as $egresado)
            @php
              $invitacion = $invitaciones->get($egresado->id);
              $completo = $egresado->contactoVigente !== null;
              $vigente = $invitacion && $invitacion->estado === 'PENDIENTE' && \Illuminate\Support\Carbon::parse($invitacion->expira_en)->isFuture();
              $vencido = $invitacion && ! $vigente && in_array($invitacion->estado, ['PENDIENTE', 'EXPIRADA'], true);
              $correo = $vigente
                  ? ($invitacion->enviado_en ? 'Correo enviado' : ($invitacion->entrega_fallida ? 'Falló el envío' : 'Enlace listo para entregar'))
                  : null;
            @endphp
            <tr>
              <td>{{ $egresado->nombre_completo }}</td>
              <td>{{ $egresado->codigo_matricula }}</td>
              <td>Promoción {{ $egresado->cohorte?->anio_ingreso }}</td>
              <td>
                @if($completo)
                  <span class="sg-badge sg-badge-success">✓ Completó su contacto</span>
                @elseif($vigente)
                  <span class="sg-badge sg-badge-warning">Pendiente · hasta el {{ $fechaCorta($invitacion->expira_en) }}</span>
                  @if($correo)<span class="sg-help" style="display: block; margin-top: 4px;">{{ $correo }}</span>@endif
                @elseif($vencido)
                  <span class="sg-badge sg-badge-danger">Venció el {{ $fechaCorta($invitacion->expira_en) }} sin completar</span>
                @else
                  <span class="sg-badge">Sin enlace</span>
                @endif
              </td>
              <td>
                @if($completo)
                  <span class="sg-help">—</span>
                @else
                  <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.egresado', $egresado->id) }}">
                    @csrf
                    <input type="hidden" name="cohorte" value="{{ $filtros['cohorte'] }}">
                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                    <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                    <button type="submit" class="sg-button sg-button-secondary">{{ $vigente || $vencido ? 'Enlace nuevo' : 'Generar enlace' }}</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
