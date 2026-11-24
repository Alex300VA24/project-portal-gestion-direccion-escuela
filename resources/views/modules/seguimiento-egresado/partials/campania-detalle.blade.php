{{-- Contenido de la ventana de detalle de una campaña. Sin scripts: se inserta con fetch y funciona con los formularios y botones de la página. --}}
@php
  $fecha = fn ($v, $f = 'd/m/Y') => $v ? \Illuminate\Support\Carbon::parse($v)->format($f) : '—';
  $esEgresados = $campania->tipo === 'EGRESADOS';
  $cerrada = $campania->estado === 'CERRADA';
  $estados = ['ABIERTA' => ['Abierta', 'sg-badge-success'], 'PROGRAMADA' => ['Programada', 'sg-badge-warning'], 'CERRADA' => ['Cerrada', '']];
  [$estadoTexto, $estadoClase] = $estados[$campania->estado] ?? [ucfirst(strtolower($campania->estado)), ''];
  $etiquetas = ['COMPLETADA' => ['RESPONDIDA', 'sg-badge-success'], 'PENDIENTE' => ['PENDIENTE', 'sg-badge-warning'], 'EXPIRADA' => ['VENCIDA', 'sg-badge-danger'], 'ANULADA' => ['ANULADA', '']];
  $datosEdicion = [
      'accion' => route('seguimiento-egresado.campanias.update', $campania->id), 'nombre' => $campania->nombre, 'tipo' => $campania->tipo,
      'periodo' => $campania->periodo, 'horizonte' => $campania->horizonte_anios, 'inicio' => \Illuminate\Support\Carbon::parse($campania->fecha_inicio)->format('Y-m-d'), 'fin' => \Illuminate\Support\Carbon::parse($campania->fecha_fin)->format('Y-m-d'),
      'descripcion' => $campania->descripcion, 'promociones' => $campania->cohortes->pluck('id')->map(fn ($i) => (int) $i)->values()->all(),
  ];
@endphp

<div class="sg-detalle-cabecera">
  <div>
    <h2 class="sg-dialogo-titulo" id="dlg-detalle-titulo" style="text-align: left; margin: 0 0 6px;">{{ $campania->nombre }}</h2>
    <div class="sg-resumen-badges">
      <span class="sg-badge">{{ $esEgresados ? 'Para egresados' : 'Para empleadores' }}</span>
      <span class="sg-badge">Periodo {{ $campania->periodo }}</span>
      <span class="sg-badge {{ $estadoClase }}">{{ $estadoTexto }}</span>
      <span class="sg-badge">{{ $fecha($campania->fecha_inicio) }} – {{ $fecha($campania->fecha_fin) }}</span>
      @if($campania->horizonte_anios)<span class="sg-badge">{{ $campania->horizonte_anios }} {{ $campania->horizonte_anios === 1 ? 'año' : 'años' }} desde el egreso</span>@endif
      @foreach($campania->cohortes->sortBy('anio_ingreso') as $promocion)<span class="sg-badge">Promoción {{ $promocion->anio_ingreso }}</span>@endforeach
    </div>
    @if($campania->descripcion)<p class="sg-help" style="margin-top: 8px;">{{ $campania->descripcion }}</p>@endif
  </div>
  <button type="button" class="sg-dialogo-cerrar" aria-label="Cerrar" onclick="document.getElementById('dlg-detalle').close()">✕</button>
</div>

@if($esEgresados)
  <div class="sg-cobertura">
    @if($resumen['convocados'] === 0)
      <strong style="font-size: 14px;">Aún no se enviaron invitaciones</strong>
    @else
      <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 14px;">
        <strong>{{ $resumen['respondieron'] }} de {{ $resumen['convocados'] }} respondieron ({{ number_format($resumen['cobertura'], 1) }}%)</strong>
        <span class="sg-help">{{ $resumen['pendientes'] }} {{ $resumen['pendientes'] === 1 ? 'pendiente' : 'pendientes' }}</span>
      </div>
      <div class="sg-barra-cob" role="img" aria-label="{{ $resumen['cobertura'] }}% respondió"><div style="width: {{ min(100, $resumen['cobertura']) }}%;"></div></div>
    @endif
    @if(($resumen['por_convocar'] ?? 0) > 0)
      <p class="sg-help" style="margin: 6px 0 0; color: var(--sg-warning);">
        {{ $resumen['por_convocar'] === 1 ? 'Hay 1 egresado confirmado' : 'Hay '.$resumen['por_convocar'].' egresados confirmados' }} de estas promociones que todavía no {{ $resumen['por_convocar'] === 1 ? 'recibió' : 'recibieron' }} su invitación. Pulsa «Enviar invitaciones» para convocarlos.
      </p>
    @endif
  </div>
@else
  <div class="sg-cobertura">
    <strong style="font-size: 14px;">{{ $empresas['respondieron'] }} de {{ $empresas['enlaces'] }} empleadores respondieron</strong>
    <p class="sg-help" style="margin: 4px 0 8px;">Los enlaces para empleadores se generan y se entregan desde Encuesta empresarial.</p>
    <a class="sg-button" href="{{ route('seguimiento-egresado.empresas', ['campania' => $campania->id]) }}">Abrir en Encuesta empresarial</a>
  </div>
@endif

<div class="sg-acciones-detalle">
  @if($esEgresados)
    <form method="POST" data-sg-cargando="Enviando invitaciones…" data-sg-nota="Se envía un correo a cada egresado: puede tardar unos segundos." action="{{ route('seguimiento-egresado.campanias.emitir-invitaciones', $campania->id) }}"
          data-sg-confirmar="Se enviará la invitación al correo personal de cada egresado confirmado de las promociones de esta campaña que aún no la tenga. ¿Continuar?">
      @csrf
      <button class="sg-button" type="submit" @disabled($cerrada)>@include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])<span>Enviar invitaciones</span></button>
    </form>
    <form method="POST" data-sg-cargando="Enviando recordatorios…" data-sg-nota="Se envía un correo a cada pendiente: puede tardar unos segundos." action="{{ route('seguimiento-egresado.campanias.recordar-pendientes', $campania->id) }}"
          data-sg-confirmar="Cada egresado pendiente recibirá un enlace nuevo y el anterior dejará de funcionar. ¿Continuar?">
      @csrf
      <button class="sg-button sg-button-secondary" type="submit" @disabled($cerrada || $pendientesConvocables === 0)>
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
        <span>Recordar {{ $pendientesConvocables === 1 ? 'al pendiente' : 'a los '.$pendientesConvocables.' pendientes' }}</span>
      </button>
    </form>
  @endif
  <button type="button" class="sg-button sg-button-secondary" onclick="abrirEditarCampania(@js($datosEdicion))">Editar</button>
  @if($esEgresados)
    <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.reportes.invitaciones.csv', $campania->id) }}">Descargar lista (CSV)</a>
  @endif
</div>
@if($cerrada && $esEgresados)
  <p class="sg-help" style="margin: 0 0 12px;">La campaña ya cerró: no se pueden enviar invitaciones ni recordatorios.</p>
@endif

@if(session('enlaces_emitidos'))
  <section class="sg-enlaces" aria-labelledby="sg-enlaces-titulo">
    <h3 id="sg-enlaces-titulo" style="margin: 0 0 4px; font-size: 15px;">Enlaces personales recién generados</h3>
    <p class="sg-help" style="margin-bottom: 8px;">Cópialos ahora: por seguridad solo se muestran una vez. Si se pierde uno, envía un recordatorio y se genera otro.</p>
    <div class="sg-table-wrap">
      <table class="sg-table">
        <thead><tr><th scope="col">Egresado</th><th scope="col">Matrícula</th><th scope="col">Correo</th><th scope="col">Enlace personal</th><th scope="col">Copiar</th></tr></thead>
        <tbody>
          @foreach(session('enlaces_emitidos') as $enlace)
            <tr>
              <td>{{ $enlace['egresado'] }}</td>
              <td>{{ $enlace['matricula'] }}</td>
              <td><span class="sg-badge sg-badge-{{ $enlace['correo_insignia'] ?? 'warning' }}">{{ $enlace['correo_texto'] ?? 'No enviado' }}</span></td>
              <td><input class="sg-input" type="text" readonly value="{{ $enlace['url'] }}" aria-label="Enlace de {{ $enlace['egresado'] }}" onfocus="this.select()"></td>
              <td><button type="button" class="sg-button sg-button-secondary" onclick="navigator.clipboard.writeText(this.closest('tr').querySelector('input').value); this.textContent = 'Copiado';">Copiar</button></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endif

@if($esEgresados)
  <h3 style="margin: 18px 0 8px; font-size: 15px;">Egresados convocados ({{ $invitaciones->count() }})</h3>
  @if($invitaciones->isEmpty())
    <p class="sg-help">Aún no se enviaron invitaciones. Pulsa «Enviar invitaciones» para convocar a los egresados confirmados de las promociones elegidas.</p>
  @else
    <div class="sg-table-wrap">
      <table class="sg-table sg-tabla-detalle">
        <caption class="sg-sr-only">Egresados convocados a esta campaña</caption>
        <thead>
          <tr>
            <th scope="col">Matrícula</th><th scope="col">Egresado</th><th scope="col">Promoción</th><th scope="col">Fecha de envío</th>
            <th scope="col">Estado</th><th scope="col">Fecha de respuesta</th><th scope="col" class="sg-numeric">Recordatorios</th><th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          @foreach($invitaciones as $inv)
            @php
              $eg = $inv->egresado;
              $respondida = $inv->estado === 'COMPLETADA' || $inv->medicion !== null;
              [$textoEstado, $claseEstado] = $respondida ? $etiquetas['COMPLETADA'] : ($etiquetas[$inv->estado] ?? [$inv->estado, '']);
              $respondio = $inv->respondido_en ?? $inv->medicion?->fecha_respuesta;
            @endphp
            <tr>
              <td><code>{{ $eg?->codigo_matricula }}</code></td>
              <td><strong>{{ $eg?->apellido_paterno }} {{ $eg?->apellido_materno }}, {{ $eg?->nombres }}</strong></td>
              <td>{{ $eg?->cohorte?->anio_ingreso ?? '—' }}</td>
              <td>{{ $inv->enviado_en ? $fecha($inv->enviado_en, 'd/m/Y H:i') : '—' }}</td>
              <td>
                <span class="sg-badge {{ $claseEstado }}">{{ $textoEstado }}</span>
                @if($inv->entrega_fallida)<span class="sg-badge sg-badge-danger">Falló el envío</span>@endif
              </td>
              <td>{{ $respondio ? $fecha($respondio, 'd/m/Y H:i') : '—' }}</td>
              <td class="sg-numeric">{{ $inv->recordatorios_enviados }}</td>
              <td>
                @if($respondida && ($puedeVerRespuestas ?? false) && $inv->medicion)
                  <button type="button" class="sg-button sg-button-secondary" data-respuesta="{{ route('seguimiento-egresado.campanias.respuesta', $inv->id) }}">Ver respuesta</button>
                @elseif($respondida)
                  <span class="sg-badge sg-badge-success">✓ Respondió</span>
                @elseif($inv->estado === 'PENDIENTE' && ! $cerrada)
                  <form method="POST" data-sg-cargando="Enviando…" action="{{ route('seguimiento-egresado.campanias.recordatorio', $inv->id) }}">
                    @csrf
                    <button type="submit" class="sg-button sg-button-secondary">Recordar</button>
                  </form>
                @else
                  <span class="sg-help">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endif
