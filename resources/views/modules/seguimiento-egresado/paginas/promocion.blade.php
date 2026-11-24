@extends('modules.seguimiento-egresado.layout')
@section('title', 'Promoción '.$promocion->anio_ingreso)
@section('section_name', 'Promoción '.$promocion->anio_ingreso)

@push('styles')
<style>
  .sg-paso { margin-bottom: var(--sg-space-4); scroll-margin-top: 16px; }
  .sg-paso-cabecera { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; }
  .sg-paso-num { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 14px; background: var(--sg-hover); color: var(--sg-muted); flex: none; }
  .sg-paso.hecho .sg-paso-num { background: var(--sg-success); color: #fff; }
  .sg-paso.toca { border-color: var(--sg-accent); box-shadow: 0 0 0 1px var(--sg-accent); }
  .sg-paso.toca .sg-paso-num { background: var(--sg-accent); color: #fff; }
  .sg-paso.bloqueado { opacity: .62; }
  .sg-paso-titulo { margin: 0; font-size: 1.05rem; font-weight: 600; }
  .sg-paso-estado { margin-left: auto; }
  .sg-resumen { display: flex; gap: 8px; flex-wrap: wrap; margin: 6px 0 12px; }
  .sg-fila-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .sg-datos { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 20px; margin: 8px 0 12px; }
  .sg-datos dt { font-size: 12px; color: var(--sg-muted); }
  .sg-datos dd { margin: 0; font-weight: 600; }
</style>
@endpush

@section('content')
@php
  $fecha = fn ($v, $f = 'd/m/Y') => \Illuminate\Support\Carbon::parse($v)->format($f);
  $paso = $avance['paso'];
  $terminada = $avance['terminada'];
  $hecho = [1 => true, 2 => $avance['total'] > 0, 3 => $paso >= 4, 4 => $terminada];
  $bloqueado = [1 => false, 2 => false, 3 => $avance['total'] === 0, 4 => ! $avance['con_avance']];
  $clase = fn (int $n) => $hecho[$n] ? 'hecho' : ($n === $paso ? 'toca' : ($bloqueado[$n] ? 'bloqueado' : ''));
  $sinContacto = $avance['total'] - $avance['completaron'];
  $datosPromocion = ['id' => $promocion->id, 'anio' => $promocion->anio_ingreso, 'total' => $promocion->total_ingresantes, 'plan' => $promocion->id_plan_curricular, 'obs' => $promocion->observacion];
  $etiquetas = ['completo' => ['COMPLETADA', 'sg-badge-success'], 'pendiente' => ['PENDIENTE', 'sg-badge-warning'], 'vencido' => ['VENCIDA', 'sg-badge-danger'], 'sin_enlace' => ['SIN ENLACE', '']];
@endphp

<section class="sg-heading" aria-labelledby="sg-promocion-title">
  <div class="sg-kicker"><a class="sg-link" href="{{ route('seguimiento-egresado.padron') }}">← Todas las promociones</a></div>
  <h1 id="sg-promocion-title">Promoción {{ $promocion->anio_ingreso }}</h1>
  <p>Sigue los pasos en orden. Puedes salir y volver cuando quieras: la lista de promociones te deja en el paso que toca.</p>
</section>

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Revisa lo siguiente:</strong>
      <ul style="margin: 4px 0 0; padding-left: 18px; font-size: 13px;">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  </div>
@endif

@if(session('enlaces_contacto'))
  <section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-enlaces-contacto-titulo">
    <h2 class="sg-section-title" id="sg-enlaces-contacto-titulo">Enlaces personales recién generados</h2>
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

{{-- PASO 1 · PROMOCIÓN Y TOTAL DE INGRESANTES --}}
<section class="sg-panel sg-paso {{ $clase(1) }}" id="paso-1" aria-labelledby="t-paso-1">
  <div class="sg-paso-cabecera">
    <span class="sg-paso-num" aria-hidden="true">{{ $hecho[1] ? '✓' : 1 }}</span>
    <h2 class="sg-paso-titulo" id="t-paso-1">Promoción y total de ingresantes</h2>
    <span class="sg-paso-estado sg-badge sg-badge-success">Hecho</span>
  </div>
  <dl class="sg-datos">
    <div><dt>Año de ingreso</dt><dd>{{ $promocion->anio_ingreso }}</dd></div>
    <div><dt>Total de ingresantes</dt><dd>{{ $promocion->total_ingresantes }}</dd></div>
    <div><dt>Plan de estudios</dt><dd>{{ $promocion->planCurricular?->codigo_version ?? '—' }}</dd></div>
  </dl>
  @if($puedeGestionar)<button type="button" class="sg-button sg-button-secondary" onclick="abrirEditarPromocion(@js($datosPromocion))">Editar</button>@endif
</section>

{{-- PASO 2 · CARGAR ALUMNOS --}}
<section class="sg-panel sg-paso {{ $clase(2) }}" id="paso-2" aria-labelledby="t-paso-2">
  <div class="sg-paso-cabecera">
    <span class="sg-paso-num" aria-hidden="true">{{ $hecho[2] ? '✓' : 2 }}</span>
    <h2 class="sg-paso-titulo" id="t-paso-2">Cargar a los alumnos</h2>
    <span class="sg-paso-estado sg-badge {{ $hecho[2] ? 'sg-badge-success' : 'sg-badge-warning' }}">{{ $hecho[2] ? $avance['total'].' cargados' : 'Te toca' }}</span>
  </div>
  <p class="sg-help" style="margin-bottom: var(--sg-space-3);">Sube el archivo (CSV o Excel) con los alumnos que ya cumplen para egresar, o elígelos de los estudiantes del portal. <strong>Todos quedan unidos a la Promoción {{ $promocion->anio_ingreso }}.</strong> Si una fila tiene un problema no se guarda ninguna, y se te dice cuál corregir.</p>
  @unless($puedeGestionar)
    <p class="sg-help">Solo la Secretaría carga a los alumnos. Aquí puedes consultar cuántos hay.</p>
  @else
  <form method="POST" action="{{ route('seguimiento-egresado.padron.importar') }}" enctype="multipart/form-data" class="sg-fila-form">
    @csrf
    <input type="hidden" name="promocion" value="{{ $promocion->id }}">
    <div class="sg-field">
      <label class="sg-label" for="archivo-padron">Archivo con los alumnos</label>
      <input class="sg-input" id="archivo-padron" type="file" name="archivo_padron" accept=".csv,.txt,.xlsx" required>
    </div>
    <button type="submit" class="sg-button">{{ $hecho[2] ? 'Cargar más alumnos' : 'Cargar alumnos' }}</button>
    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.padron.plantilla') }}">Descargar el formato modelo</a>
    <button type="button" class="sg-button sg-button-secondary" id="btn-elegir-estudiantes">Elegir de los estudiantes del portal</button>
  </form>
  @endunless
  <p class="sg-help" style="margin-top: var(--sg-space-2);">Cada alumno necesita matrícula, documento, nombres, apellido paterno y correo institucional.</p>
</section>

{{-- PASO 3 · ENVIAR ENLACES DE CONTACTO --}}
<section class="sg-panel sg-paso {{ $clase(3) }}" id="paso-3" aria-labelledby="t-paso-3">
  <div class="sg-paso-cabecera">
    <span class="sg-paso-num" aria-hidden="true">{{ $hecho[3] ? '✓' : 3 }}</span>
    <h2 class="sg-paso-titulo" id="t-paso-3">Enviar el enlace de contacto</h2>
    <span class="sg-paso-estado sg-badge {{ $hecho[3] ? 'sg-badge-success' : ($bloqueado[3] ? '' : 'sg-badge-warning') }}">{{ $hecho[3] ? 'Enviado a todos' : ($bloqueado[3] ? 'Primero carga alumnos' : 'Te toca') }}</span>
  </div>
  <p class="sg-help" style="margin-bottom: var(--sg-space-3);">Cada alumno recibe en su correo institucional un enlace personal para dejar sus datos de contacto. <strong>Es obligatorio:</strong> si no lo completa, no podrá egresar, y el correo y el formulario se lo dicen. El enlace funciona hasta la fecha límite que elijas.</p>
  @if($avance['con_avance'] && $avance['fecha_limite'])
    <p class="sg-help" style="margin-bottom: var(--sg-space-3);">Fecha límite actual: <strong>{{ $fecha($avance['fecha_limite']) }}</strong>. Puedes enviar enlaces a quienes aún no tienen uno.</p>
  @endif
  @if(! $puedeGestionar)
    <p class="sg-help">Solo la Secretaría envía los enlaces. Abajo ves quién ya completó su contacto.</p>
  @endif
  @unless($bloqueado[3] || ! $puedeGestionar)
    <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.cohorte') }}" class="sg-fila-form"
          data-sg-confirmar="Se enviará un enlace a cada alumno de la promoción que aún no dejó su contacto. ¿Continuar?">
      @csrf
      <input type="hidden" name="cohorte" value="{{ $promocion->id }}">
      <div class="sg-field">
        <label class="sg-label" for="ci-fecha-limite">Fecha límite para completarlo</label>
        <input class="sg-input" id="ci-fecha-limite" type="date" name="fecha_limite" required
               value="{{ old('fecha_limite', $fechaLimiteSugerida) }}" min="{{ now()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}">
      </div>
      <button type="submit" class="sg-button">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
        <span>Enviar enlaces</span>
      </button>
    </form>
  @endunless
</section>

{{-- PASO 4 · QUIÉN COMPLETÓ SU CONTACTO --}}
<section class="sg-panel sg-paso {{ $clase(4) }}" id="paso-4" aria-labelledby="t-paso-4">
  <div class="sg-paso-cabecera">
    <span class="sg-paso-num" aria-hidden="true">{{ $hecho[4] ? '✓' : 4 }}</span>
    <h2 class="sg-paso-titulo" id="t-paso-4">Quién completó su contacto</h2>
    <span class="sg-paso-estado sg-badge {{ $hecho[4] ? 'sg-badge-success' : ($bloqueado[4] ? '' : 'sg-badge-warning') }}">{{ $hecho[4] ? 'Todos completaron' : ($bloqueado[4] ? 'Primero envía los enlaces' : $avance['completaron'].' de '.$avance['total']) }}</span>
  </div>

  @if($bloqueado[4])
    <p class="sg-help">Aquí verás, alumno por alumno, quién ya dejó su contacto y quién no, para saber a quiénes aún no se les debe permitir egresar.</p>
  @else
    <div class="sg-resumen">
      <span class="sg-badge sg-badge-success">{{ $avance['completaron'] }} completaron</span>
      <span class="sg-badge sg-badge-warning">{{ $avance['pendientes'] }} en plazo</span>
      <span class="sg-badge {{ $avance['vencidos'] > 0 ? 'sg-badge-danger' : '' }}">{{ $avance['vencidos'] }} vencidos</span>
      <span class="sg-badge">{{ $avance['sin_enlace'] }} sin enlace</span>
      @if($avance['fecha_limite'])<span class="sg-badge">Fecha límite: {{ $fecha($avance['fecha_limite']) }}</span>@endif
    </div>
    @if($sinContacto > 0)
      <div class="sg-alert sg-alert-warning" role="note" style="margin-bottom: var(--sg-space-3);">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
        <p><strong>{{ $sinContacto }} {{ $sinContacto === 1 ? 'alumno no completó' : 'alumnos no completaron' }} su contacto inicial</strong> y aún no {{ $sinContacto === 1 ? 'debería' : 'deberían' }} egresar. Dales otra oportunidad con «Enlace nuevo».</p>
      </div>
    @endif

    <form method="GET" action="{{ route('seguimiento-egresado.padron.promocion', $promocion->id) }}#paso-4" class="sg-filters" style="margin-bottom: var(--sg-space-3);">
      <div class="sg-field">
        <label class="sg-label" for="ci-filtro-estado">Mostrar</label>
        <select class="sg-select" id="ci-filtro-estado" name="estado">
          <option value="todos" @selected($filtros['estado'] === 'todos')>Todos</option>
          <option value="pendientes" @selected($filtros['estado'] === 'pendientes')>En plazo, sin completar</option>
          <option value="vencidos" @selected($filtros['estado'] === 'vencidos')>Pasó la fecha límite sin completar</option>
          <option value="sin_enlace" @selected($filtros['estado'] === 'sin_enlace')>Sin enlace</option>
          <option value="completaron" @selected($filtros['estado'] === 'completaron')>Ya completaron</option>
        </select>
      </div>
      <div class="sg-field">
        <label class="sg-label" for="ci-filtro-q">Buscar</label>
        <input class="sg-input" id="ci-filtro-q" type="search" name="q" maxlength="80" value="{{ $filtros['q'] }}" placeholder="Nombre, matrícula o documento">
      </div>
      <div class="sg-field"><button type="submit" class="sg-button sg-button-secondary">Filtrar</button></div>
    </form>

    @if($filas->isEmpty())
      <p class="sg-help">No hay alumnos con esos filtros.</p>
    @else
      <p class="sg-help" style="margin-bottom: var(--sg-space-3);">
        @if($totalFilas > $limite)Se muestran los primeros {{ $limite }} de {{ $totalFilas }}. Usa los filtros para acotar.@else{{ $totalFilas }} {{ $totalFilas === 1 ? 'alumno' : 'alumnos' }} de la promoción.@endif
      </p>
      <div class="sg-table-wrap">
        <table class="sg-table">
          <caption class="sg-sr-only">Estado del contacto inicial de cada alumno de la promoción</caption>
          <thead>
            <tr>
              <th scope="col">Matrícula</th>
              <th scope="col">Alumno</th>
              <th scope="col">Fecha de envío</th>
              <th scope="col">Estado del enlace</th>
              <th scope="col">Fecha de respuesta</th>
              <th scope="col">Acción</th>
            </tr>
          </thead>
          <tbody>
            @foreach($filas as $fila)
              @php
                $alumno = $fila['alumno'];
                $invitacion = $fila['invitacion'];
                [$texto, $insignia] = $etiquetas[$fila['estado']];
                $correo = $fila['estado'] === 'pendiente' && $invitacion
                    ? ($invitacion->enviado_en ? 'Correo enviado' : ($invitacion->entrega_fallida ? 'Falló el envío' : 'Enlace listo para entregar'))
                    : null;
              @endphp
              <tr>
                <td><code>{{ $alumno->codigo_matricula }}</code></td>
                <td>
                  <strong>{{ $alumno->apellido_paterno }} {{ $alumno->apellido_materno }}, {{ $alumno->nombres }}</strong><br>
                  <span class="sg-help">{{ $alumno->correo_institucional }}</span>
                </td>
                <td>{{ $invitacion?->enviado_en ? $fecha($invitacion->enviado_en, 'd/m/Y H:i') : '—' }}</td>
                <td>
                  <span class="sg-badge {{ $insignia }}">{{ $texto }}</span>
                  @if($invitacion && $fila['estado'] !== 'completo' && $fila['estado'] !== 'sin_enlace')<span class="sg-help" style="display: block; margin-top: 4px;">hasta el {{ $fecha($invitacion->expira_en) }}</span>@endif
                  @if($correo)<span class="sg-help" style="display: block;">{{ $correo }}</span>@endif
                </td>
                <td>{{ $alumno->contactoVigente ? $fecha($alumno->contactoVigente->vigente_desde, 'd/m/Y H:i') : '—' }}</td>
                <td>
                  @if($fila['estado'] === 'completo')
                    <span class="sg-badge sg-badge-success">✓ Contacto completado</span>
                  @else
                    @if($puedeGestionar)
                    <form method="POST" action="{{ route('seguimiento-egresado.padron.contacto-inicial.egresado', $alumno->id) }}">
                      @csrf
                      <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                      <input type="hidden" name="estado" value="{{ $filtros['estado'] }}">
                      <button type="submit" class="sg-button sg-button-secondary">{{ $fila['estado'] === 'sin_enlace' ? 'Generar enlace' : 'Enlace nuevo' }}</button>
                    </form>
                    @endif
                    <span class="sg-help" style="display: block; margin-top: 4px; color: var(--sg-danger);">Aún no debería egresar</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  @endif
</section>

@include('modules.seguimiento-egresado.partials.promocion-formularios', ['planes' => $planes])
@if($puedeGestionar)
  @include('modules.seguimiento-egresado.partials.estudiantes-portal-dialogo')
@endif
@endsection
