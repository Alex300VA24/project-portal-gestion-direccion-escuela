@extends('modules.seguimiento-egresado.layout')
@section('title', 'Campañas e invitaciones')
@section('section_name', 'Campañas e invitaciones')

@section('content')
<section class="sg-heading" aria-labelledby="sg-campanias-title">
  <div class="sg-kicker">MÓDULO DE SEGUIMIENTO AL EGRESADO · GESTIÓN DE CONVOCATORIAS Y COBERTURA</div>
  <h1 id="sg-campanias-title">Campañas e invitaciones</h1>
  <p>Planificación de convocatorias por cohortes, despacho de invitaciones por token seguro y seguimiento trazable de la tasa de cobertura.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <span class="sg-badge sg-badge-success">Módulo Oficial Activo</span>
    <span class="sg-badge">Gestión de Convocatorias</span>
    <span class="sg-badge">Trazabilidad de Cobertura</span>
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px; margin-left: auto;" href="{{ route('seguimiento-egresado.reportes') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
      <span>Ver Matriz de Formatos F1–F11</span>
    </a>
  </div>
</section>

{{-- MENSAJES DE ESTADO / ALERTAS --}}
@if(session('success'))
  <div class="sg-alert" role="status" style="margin-bottom: var(--sg-space-4); border-left-color: var(--sg-success); background: #e7f7f0;">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Operación exitosa:</strong>
      <p>{{ session('success') }}</p>
    </div>
  </div>
@endif

@if(session('enlaces_emitidos'))
  <section class="sg-panel" style="margin-bottom: var(--sg-space-4);" aria-labelledby="sg-enlaces-emitidos-titulo">
    <h2 class="sg-section-title" id="sg-enlaces-emitidos-titulo">Enlaces personales de las invitaciones</h2>
    <p class="sg-help">Cada egresado entra con su enlace. Cópialos ahora: por seguridad solo se muestran una vez y no se pueden recuperar después (en la base de datos queda únicamente su huella).</p>
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

@if(session('recordatorio_emitido'))
  @php $rec = session('recordatorio_emitido'); @endphp
  <section class="sg-panel" style="margin-bottom: var(--sg-space-4); border-left: 4px solid var(--sg-warning); background: #fffdf5;" aria-labelledby="sg-recordatorio-titulo">
    <div class="sg-section-title" style="margin-bottom: 8px;">
      <div>
        <h2 class="sg-section-title" id="sg-recordatorio-titulo" style="font-size: 16px; margin: 0; color: var(--sg-navy);">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
          Recordatorio personal emitido para {{ $rec['egresado'] }} (Matrícula {{ $rec['matricula'] }})
        </h2>
        <p class="sg-help" style="margin-top: 4px;">
          Se generó un nuevo token personal seguro (Recordatorio #{{ $rec['numero_recordatorio'] }}). Copia este enlace para despacharlo por el canal registrado ({{ $rec['correo'] }}).
          <span class="sg-badge sg-badge-{{ $rec['correo_insignia'] ?? 'warning' }}" style="margin-left: 4px;">{{ $rec['correo_texto'] ?? 'No enviado' }}</span>
        </p>
      </div>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; max-width: 720px; margin-top: 8px;">
      <input class="sg-input" type="text" readonly value="{{ $rec['url'] }}" aria-label="Enlace de recordatorio" onfocus="this.select()" style="font-family: monospace; font-size: 13px;">
      <button type="button" class="sg-button sg-button-secondary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent = '¡Copiado!';">Copiar enlace</button>
    </div>
  </section>
@endif

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Se encontraron observaciones en el servidor:</strong>
      <ul style="margin: 4px 0 0; padding-left: 18px; font-size: 13px;">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

{{-- AVISO METODOLÓGICO OBLIGATORIO --}}
<div class="sg-alert sg-alert-warning" role="note" style="margin-bottom: var(--sg-space-6);" aria-labelledby="sg-nota-metodologica-titulo">
  @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
  <div>
    <strong id="sg-nota-metodologica-titulo">Criterio metodológico fundamental:</strong>
    <p>
      La <strong>cobertura</strong> mide la proporción de personas convocadas que respondieron la encuesta
      (<em>cobertura = respuestas recibidas ÷ total de invitaciones enviadas</em>).
      <strong>La falta de respuesta o persona pendiente NO debe confundirse con desempleo</strong>:
      la no respuesta indica ausencia de información muestral, mientras que la situación laboral
      (empleado, en búsqueda, posgrado o independiente) se determina únicamente sobre los cuestionarios completados.
    </p>
  </div>
</div>

{{-- MÉTRICAS RESUMEN DE COBERTURA --}}
<section class="sg-panel" aria-labelledby="sg-metricas-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <h2 id="sg-metricas-title">Balance global de convocatorias y cobertura</h2>
      <p>Métricas consolidadas de las campañas registradas en el sistema institucional.</p>
    </div>
    <div class="sg-actions">
      <a class="sg-button sg-button-secondary" style="font-size: 13px; padding: 6px 12px; min-height: 36px;" href="{{ route('seguimiento-egresado.reportes.campanias.csv', request()->query()) }}">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
        <span>Exportar Campañas (CSV)</span>
      </a>
    </div>
  </div>

  <div class="sg-metric-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--sg-space-4); margin-top: var(--sg-space-3);">
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-navy);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Total egresados convocados</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-navy); margin-top: 4px;">{{ number_format($totalConvocadosGlobal) }}</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">En {{ count($campaniasData) }} campañas registradas</div>
    </div>
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-success);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Respuestas recibidas</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-success); margin-top: 4px;">{{ number_format($totalRespuestasGlobal) }}</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Encuestas completadas y válidas</div>
    </div>
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-warning);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Personas pendientes</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-warning); margin-top: 4px;">{{ number_format($totalPendientesGlobal) }}</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Sin responder (no equivale a desempleo)</div>
    </div>
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-accent);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Tasa de cobertura global</div>
      <div style="font-size: 28px; font-weight: 700; color: var(--sg-accent); margin-top: 4px;">{{ number_format($coberturaPromedioGlobal, 1) }}%</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Meta institucional mínima: 60.0%</div>
    </div>
  </div>
</section>

{{-- SECCIÓN CREAR NUEVA CAMPAÑA (EXPANDIBLE) --}}
<details class="sg-panel" style="margin-bottom: var(--sg-space-6);" id="seccion-crear-campania" @if($errors->any()) open @endif>
  <summary style="font-weight: 650; font-size: 16px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
    <span>➕ Programar nueva campaña institucional</span>
    <span style="font-size: 13px; color: var(--sg-accent); text-decoration: underline;">Mostrar / Ocultar formulario</span>
  </summary>
  <div style="margin-top: var(--sg-space-5); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
    <p style="font-size: 14px; color: var(--sg-muted); margin-bottom: var(--sg-space-4);">
      Permite configurar convocatorias oficiales. Las fechas son totalmente configurables (se valida en servidor que la fecha de fin sea posterior a la de inicio) y puede asociar una o múltiples cohortes.
    </p>

    <form method="POST" action="{{ route('seguimiento-egresado.campanias.store') }}">
      @csrf
      <div class="sg-form-grid">
        <div class="sg-field">
          <label class="sg-label" for="camp-nombre">Nombre de la campaña *</label>
          <input class="sg-input" type="text" id="camp-nombre" name="nombre" required value="{{ old('nombre', 'Campaña de Inserción Laboral 2024') }}" placeholder="Ej. Encuesta de Inserción Laboral a 1 Año">
          <p class="sg-help">Identificador formal de la convocatoria.</p>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-tipo">Población objetivo (Tipo) *</label>
          <select class="sg-select" id="camp-tipo" name="tipo" required>
            <option value="EGRESADOS" {{ old('tipo') === 'EGRESADOS' ? 'selected' : '' }}>Egresados y Graduados</option>
            <option value="EMPRESAS" {{ old('tipo') === 'EMPRESAS' ? 'selected' : '' }}>Empresas y Empleadores</option>
            <option value="MIXTA" {{ old('tipo') === 'MIXTA' ? 'selected' : '' }}>Convocatoria Mixta</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-finalidad">Finalidad de la convocatoria *</label>
          <select class="sg-select" id="camp-finalidad" name="finalidad" required>
            <option value="SEGUIMIENTO" {{ old('finalidad') === 'SEGUIMIENTO' ? 'selected' : '' }}>Seguimiento e Inserción Laboral</option>
            <option value="DIAGNOSTICO_TEMPRANO" {{ old('finalidad') === 'DIAGNOSTICO_TEMPRANO' ? 'selected' : '' }}>Diagnóstico Temprano de Empleabilidad</option>
            <option value="EVALUACION_INSTITUCIONAL" {{ old('finalidad') === 'EVALUACION_INSTITUCIONAL' ? 'selected' : '' }}>Evaluación de Competencias y OEs</option>
            <option value="EXTRAORDINARIA" {{ old('finalidad') === 'EXTRAORDINARIA' ? 'selected' : '' }}>Actualización Extraordinaria</option>
          </select>
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="camp-periodo">Periodo académico *</label>
          <input class="sg-input" type="text" id="camp-periodo" name="periodo" required value="{{ old('periodo', '2024-II') }}" placeholder="Ej. 2024-II">
          <p class="sg-help">Semestre o año de corte de evaluación.</p>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-horizonte">Horizonte temporal de estudio</label>
          <select class="sg-select" id="camp-horizonte" name="horizonte_anios">
            <option value="1" {{ old('horizonte_anios') == 1 ? 'selected' : '' }}>1 Año (Inserción laboral temprana)</option>
            <option value="3" {{ old('horizonte_anios') == 3 ? 'selected' : '' }}>3 Años (Consolidación y posgrados)</option>
            <option value="5" {{ old('horizonte_anios') == 5 ? 'selected' : '' }}>5 Años (Estudio longitudinal F8 / ICACIT)</option>
            <option value="10" {{ old('horizonte_anios') == 10 ? 'selected' : '' }}>10 Años (Impacto directivo)</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-estado">Estado inicial *</label>
          <select class="sg-select" id="camp-estado" name="estado" required>
            <option value="BORRADOR" {{ old('estado') === 'BORRADOR' ? 'selected' : '' }}>BORRADOR (En preparación)</option>
            <option value="PROGRAMADA" {{ old('estado') === 'PROGRAMADA' ? 'selected' : '' }}>PROGRAMADA (Inicio futuro)</option>
            <option value="ABIERTA" {{ old('estado') === 'ABIERTA' ? 'selected' : '' }} selected>ABIERTA (Activa para respuestas)</option>
            <option value="CERRADA" {{ old('estado') === 'CERRADA' ? 'selected' : '' }}>CERRADA (Concluida)</option>
          </select>
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="camp-inicio">Fecha de inicio / apertura *</label>
          <input class="sg-input" type="date" id="camp-inicio" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d')) }}" required>
          <p class="sg-help">Fecha en que se habilitan los accesos por token.</p>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="camp-fin">Fecha límite / cierre *</label>
          <input class="sg-input" type="date" id="camp-fin" name="fecha_fin" value="{{ old('fecha_fin', date('Y-m-d', strtotime('+90 days'))) }}" required>
          <p class="sg-help">Se valida en servidor que fin sea posterior a inicio.</p>
        </div>

        <div class="sg-field">
          <label class="sg-label">Cohortes participantes *</label>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 6px; padding: 8px; background: var(--sg-surface); border: 1px solid #9cabbc; border-radius: var(--sg-radius-sm); max-height: 120px; overflow-y: auto;">
            @forelse($cohortes as $coh)
              <label style="font-size: 13px; display: flex; align-items: center; gap: 4px;">
                <input type="checkbox" name="cohortes[]" value="{{ $coh->id }}" {{ in_array($coh->id, old('cohortes', [])) ? 'checked' : '' }}>
                {{ $coh->codigo_promocion }} ({{ $coh->anio_ingreso }})
              </label>
            @empty
              <span style="font-size: 12px; color: var(--sg-muted);">No hay cohortes registradas.</span>
            @endforelse
          </div>
          <p class="sg-help">Seleccione al menos una cohorte para emitir invitaciones.</p>
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label">Canales de notificación habilitados</label>
        <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 4px;">
          @foreach($canales as $canal)
            <label style="font-size: 14px; display: flex; align-items: center; gap: 6px;">
              <input type="checkbox" name="canales[]" value="{{ $canal->id }}" checked>
              {{ $canal->nombre }}
            </label>
          @endforeach
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="camp-descripcion">Descripción y objetivos del relevamiento</label>
        <textarea class="sg-textarea" id="camp-descripcion" name="descripcion" rows="2" placeholder="Describa el alcance de la encuesta, instrumentos utilizados y metas institucionales.">{{ old('descripcion') }}</textarea>
      </div>

      <div class="sg-actions" style="margin-top: var(--sg-space-5);">
        <button class="sg-button" type="submit">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
          <span>Registrar campaña</span>
        </button>
        <button class="sg-button sg-button-secondary" type="reset">Limpiar formulario</button>
      </div>
    </form>
  </div>
</details>

{{-- FILTROS DE CONSULTA --}}
<section class="sg-panel" aria-labelledby="sg-filtro-campanias-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <h2 id="sg-filtro-campanias-title">Consultar campañas en base de datos</h2>
    <p>Filtre por cohorte participante, periodo académico, estado o búsqueda por texto.</p>
  </div>

  <form method="GET" action="{{ route('seguimiento-egresado.campanias') }}" class="sg-filters" role="search" aria-label="Filtro de campañas">
    <div class="sg-field">
      <label class="sg-label" for="filtro-q">Buscar por texto</label>
      <input class="sg-input" type="search" id="filtro-q" name="q" value="{{ $busqueda }}" placeholder="Nombre, descripción o periodo...">
    </div>

    <div class="sg-field">
      <label class="sg-label" for="filtro-cohorte">Cohorte participante</label>
      <select class="sg-select" id="filtro-cohorte" name="cohorte">
        <option value="todas">— Todas las cohortes —</option>
        @foreach($cohortes as $coh)
          <option value="{{ $coh->id }}" {{ (string)$cohorteFiltro === (string)$coh->id ? 'selected' : '' }}>
            {{ $coh->codigo_promocion }} (Ingreso {{ $coh->anio_ingreso }})
          </option>
        @endforeach
      </select>
    </div>

    <div class="sg-field">
      <label class="sg-label" for="filtro-estado">Estado</label>
      <select class="sg-select" id="filtro-estado" name="estado">
        <option value="todos">— Todos los estados —</option>
        <option value="ABIERTA" {{ strtoupper($estadoFiltro) === 'ABIERTA' ? 'selected' : '' }}>ABIERTA (Activa)</option>
        <option value="PROGRAMADA" {{ strtoupper($estadoFiltro) === 'PROGRAMADA' ? 'selected' : '' }}>PROGRAMADA</option>
        <option value="CERRADA" {{ strtoupper($estadoFiltro) === 'CERRADA' ? 'selected' : '' }}>CERRADA</option>
        <option value="BORRADOR" {{ strtoupper($estadoFiltro) === 'BORRADOR' ? 'selected' : '' }}>BORRADOR</option>
      </select>
    </div>

    <div class="sg-field">
      <label class="sg-label" for="filtro-periodo">Periodo</label>
      <select class="sg-select" id="filtro-periodo" name="periodo">
        <option value="todos">— Todos los periodos —</option>
        @foreach($periodosDisponibles as $per)
          <option value="{{ $per }}" {{ $periodoFiltro === $per ? 'selected' : '' }}>{{ $per }}</option>
        @endforeach
      </select>
    </div>

    <div class="sg-actions">
      <button class="sg-button" type="submit">Aplicar filtros</button>
      <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.campanias') }}">Restablecer</a>
    </div>
  </form>
</section>

{{-- TABLA DE CAMPAÑAS EN BASE DE DATOS --}}
<section class="sg-panel" aria-labelledby="sg-tabla-campanias-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <h2 id="sg-tabla-campanias-title">Catálogo de campañas registradas ({{ count($campaniasData) }})</h2>
      <p>Haga clic en <strong>"Consultar detalle"</strong> para auditar la cobertura y el padrón de convocados.</p>
    </div>
  </div>

  @if($campaniasData->isEmpty())
    <div class="sg-empty" style="text-align: center;">
      <h3>No se encontraron campañas</h3>
      <p>No existen registros que coincidan con los criterios de búsqueda aplicados.</p>
      <div style="margin-top: 16px;">
        <a class="sg-button sg-button-secondary" href="{{ route('seguimiento-egresado.campanias') }}">Ver todas las campañas</a>
      </div>
    </div>
  @else
    <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-tabla-campanias-title">
      <table class="sg-table">
        <caption class="sg-sr-only">Listado de campañas de seguimiento registradas</caption>
        <thead>
          <tr>
            <th scope="col">Campaña / Denominación</th>
            <th scope="col">Periodo</th>
            <th scope="col">Vigencia / Fechas</th>
            <th scope="col">Cohortes</th>
            <th scope="col">Estado</th>
            <th scope="col" class="sg-numeric">Convocados</th>
            <th scope="col" class="sg-numeric">Respuestas</th>
            <th scope="col" class="sg-numeric">Pendientes</th>
            <th scope="col" class="sg-numeric">Cobertura</th>
            <th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($campaniasData as $camp)
            @php
              $esSeleccionada = $campaniaSeleccionada && $campaniaSeleccionada['id'] === $camp['id'];
            @endphp
            <tr style="{{ $esSeleccionada ? 'background: #edf4fb; font-weight: 550;' : '' }}">
              <td>
                <div style="font-weight: 600; color: var(--sg-navy);">{{ $camp['nombre'] }}</div>
                <div style="font-size: 12px; color: var(--sg-muted); font-family: monospace;">
                  ID #{{ $camp['id'] }} · {{ $camp['tipo'] }} · Horizonte: {{ $camp['horizonte_anios'] ? $camp['horizonte_anios'] . ' años' : 'General' }}
                </div>
              </td>
              <td><span class="sg-badge">{{ $camp['periodo'] }}</span></td>
              <td style="font-size: 13px;">
                <div>Desde: {{ date('d/m/Y', strtotime($camp['fecha_inicio'])) }}</div>
                <div>Hasta: {{ date('d/m/Y', strtotime($camp['fecha_fin'])) }}</div>
              </td>
              <td>
                @if(isset($camp['cohortes']))
                  @foreach ($camp['cohortes'] as $coh)
                    <span class="sg-badge" style="margin: 2px 0;">{{ $coh->codigo_promocion ?? $coh }}</span>
                  @endforeach
                @else
                  <span class="sg-badge">—</span>
                @endif
              </td>
              <td>
                <span class="sg-badge {{ $camp['estado_badge'] }}">{{ $camp['estado'] }}</span>
              </td>
              <td class="sg-numeric">{{ number_format($camp['invitaciones_count']) }}</td>
              <td class="sg-numeric" style="color: var(--sg-success); font-weight: 600;">{{ number_format($camp['respuestas_count']) }}</td>
              <td class="sg-numeric" style="color: var(--sg-warning);">{{ number_format($camp['pendientes_count']) }}</td>
              <td class="sg-numeric">
                <strong style="color: {{ $camp['cobertura_pct'] >= 60 ? 'var(--sg-success)' : ($camp['cobertura_pct'] > 0 ? 'var(--sg-warning)' : 'var(--sg-muted)') }};">
                  {{ number_format($camp['cobertura_pct'], 1) }}%
                </strong>
              </td>
              <td>
                <a class="sg-button sg-button-secondary" style="padding: 6px 10px; min-height: 32px; font-size: 12px;"
                   href="{{ route('seguimiento-egresado.campanias', array_merge(request()->query(), ['campania_id' => $camp['id']])) }}#detalle-campania"
                   aria-label="Consultar detalle de la campaña {{ $camp['nombre'] }}">
                  @if($esSeleccionada)
                    ✓ Viendo detalle
                  @else
                    Consultar detalle
                  @endif
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>

{{-- DETALLE DE LA CAMPAÑA SELECCIONADA --}}
@if($campaniaSeleccionada)
<section class="sg-panel" id="detalle-campania" aria-labelledby="sg-detalle-campania-title" style="border: 2px solid #8da9c0;">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">DETALLE Y SEGUIMIENTO EN TIEMPO REAL</span>
      <h2 id="sg-detalle-campania-title">{{ $campaniaSeleccionada['nombre'] }}</h2>
      <p style="margin-top: 4px; color: var(--sg-muted);">
        ID #{{ $campaniaSeleccionada['id'] }} | Población: <strong>{{ $campaniaSeleccionada['tipo'] }}</strong> | Periodo: <strong>{{ $campaniaSeleccionada['periodo'] }}</strong> | Finalidad: <strong>{{ $campaniaSeleccionada['finalidad'] }}</strong>
      </p>
    </div>
    <div class="sg-actions">
      <span class="sg-badge {{ $campaniaSeleccionada['estado_badge'] }}" style="font-size: 14px; padding: 6px 12px;">
        Estado: {{ $campaniaSeleccionada['estado'] }}
      </span>

      {{-- BOTÓN EMITIR INVITACIONES --}}
      <form method="POST" action="{{ route('seguimiento-egresado.campanias.emitir-invitaciones', $campaniaSeleccionada['id']) }}" onsubmit="return confirm('¿Desea emitir invitaciones formales a todos los egresados de las cohortes vinculadas? Se verificarán duplicados para no reemitir a quienes ya tienen invitación activa.');">
        @csrf
        <button class="sg-button" type="submit" style="font-size: 13px; padding: 6px 12px; min-height: 36px;">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'mail'])
          <span>Emitir invitaciones por cohorte</span>
        </button>
      </form>

      {{-- BOTÓN Y MODAL/EXPANDIBLE PARA EDITAR CAMPAÑA --}}
      <a class="sg-button sg-button-secondary" style="font-size: 13px; padding: 6px 12px; min-height: 36px;" href="#seccion-editar-campania" onclick="document.getElementById('seccion-editar-campania').open = true;">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
        <span>Editar campaña</span>
      </a>

      <a class="sg-button sg-button-secondary" style="font-size: 13px; padding: 6px 12px; min-height: 36px;" href="{{ route('seguimiento-egresado.reportes.invitaciones.csv', $campaniaSeleccionada['id']) }}">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
        <span>Descargar Padrón (CSV)</span>
      </a>
    </div>
  </div>

  {{-- FORMULARIO DE EDICIÓN DE CAMPAÑA SELECCIONADA --}}
  <details class="sg-panel" style="margin-bottom: var(--sg-space-5); background: #f8fafc; border: 1px dashed var(--sg-navy);" id="seccion-editar-campania">
    <summary style="font-weight: 650; font-size: 15px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
      <span>✏️ Modificar datos de la campaña: <strong>{{ $campaniaSeleccionada['nombre'] }}</strong></span>
      <span style="font-size: 12px; color: var(--sg-accent); text-decoration: underline;">Desplegar / Ocultar formulario de edición</span>
    </summary>
    <div style="margin-top: var(--sg-space-4); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
      @php
        $modelCamp = $campaniaSeleccionada['model'] ?? null;
        $cohortesIdsActuales = $modelCamp ? $modelCamp->cohortes->pluck('id')->toArray() : [];
        $canalesIdsActuales = $modelCamp ? $modelCamp->canales->pluck('id')->toArray() : [];
      @endphp
      <form method="POST" action="{{ route('seguimiento-egresado.campanias.update', $campaniaSeleccionada['id']) }}">
        @csrf
        @method('PUT')
        <div class="sg-form-grid">
          <div class="sg-field">
            <label class="sg-label" for="edit-nombre">Nombre de la campaña *</label>
            <input class="sg-input" type="text" id="edit-nombre" name="nombre" required value="{{ old('nombre', $campaniaSeleccionada['nombre']) }}">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="edit-tipo">Población objetivo *</label>
            <select class="sg-select" id="edit-tipo" name="tipo" required>
              <option value="EGRESADOS" {{ old('tipo', $campaniaSeleccionada['tipo']) === 'EGRESADOS' ? 'selected' : '' }}>Egresados y Graduados</option>
              <option value="EMPRESAS" {{ old('tipo', $campaniaSeleccionada['tipo']) === 'EMPRESAS' ? 'selected' : '' }}>Empresas y Empleadores</option>
              <option value="MIXTA" {{ old('tipo', $campaniaSeleccionada['tipo']) === 'MIXTA' ? 'selected' : '' }}>Convocatoria Mixta</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="edit-finalidad">Finalidad *</label>
            <select class="sg-select" id="edit-finalidad" name="finalidad" required>
              <option value="SEGUIMIENTO" {{ old('finalidad', $campaniaSeleccionada['finalidad']) === 'SEGUIMIENTO' ? 'selected' : '' }}>Seguimiento e Inserción Laboral</option>
              <option value="DIAGNOSTICO_TEMPRANO" {{ old('finalidad', $campaniaSeleccionada['finalidad']) === 'DIAGNOSTICO_TEMPRANO' ? 'selected' : '' }}>Diagnóstico Temprano de Empleabilidad</option>
              <option value="EVALUACION_INSTITUCIONAL" {{ old('finalidad', $campaniaSeleccionada['finalidad']) === 'EVALUACION_INSTITUCIONAL' ? 'selected' : '' }}>Evaluación de Competencias y OEs</option>
              <option value="EXTRAORDINARIA" {{ old('finalidad', $campaniaSeleccionada['finalidad']) === 'EXTRAORDINARIA' ? 'selected' : '' }}>Actualización Extraordinaria</option>
            </select>
          </div>
        </div>

        <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
          <div class="sg-field">
            <label class="sg-label" for="edit-periodo">Periodo académico *</label>
            <input class="sg-input" type="text" id="edit-periodo" name="periodo" required value="{{ old('periodo', $campaniaSeleccionada['periodo']) }}">
          </div>

          <div class="sg-field">
            <label class="sg-label" for="edit-horizonte">Horizonte temporal</label>
            <select class="sg-select" id="edit-horizonte" name="horizonte_anios">
              <option value="1" {{ old('horizonte_anios', $campaniaSeleccionada['horizonte_anios']) == 1 ? 'selected' : '' }}>1 Año (Inserción laboral temprana)</option>
              <option value="3" {{ old('horizonte_anios', $campaniaSeleccionada['horizonte_anios']) == 3 ? 'selected' : '' }}>3 Años (Consolidación y posgrados)</option>
              <option value="5" {{ old('horizonte_anios', $campaniaSeleccionada['horizonte_anios']) == 5 ? 'selected' : '' }}>5 Años (Estudio longitudinal F8 / ICACIT)</option>
              <option value="10" {{ old('horizonte_anios', $campaniaSeleccionada['horizonte_anios']) == 10 ? 'selected' : '' }}>10 Años (Impacto directivo)</option>
            </select>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="edit-estado">Estado de la campaña *</label>
            <select class="sg-select" id="edit-estado" name="estado" required>
              <option value="BORRADOR" {{ old('estado', $campaniaSeleccionada['estado']) === 'BORRADOR' ? 'selected' : '' }}>BORRADOR (En preparación)</option>
              <option value="PROGRAMADA" {{ old('estado', $campaniaSeleccionada['estado']) === 'PROGRAMADA' ? 'selected' : '' }}>PROGRAMADA (Inicio futuro)</option>
              <option value="ABIERTA" {{ old('estado', $campaniaSeleccionada['estado']) === 'ABIERTA' ? 'selected' : '' }}>ABIERTA (Activa para respuestas)</option>
              <option value="CERRADA" {{ old('estado', $campaniaSeleccionada['estado']) === 'CERRADA' ? 'selected' : '' }}>CERRADA (Concluida)</option>
            </select>
          </div>
        </div>

        <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
          <div class="sg-field">
            <label class="sg-label" for="edit-inicio">Fecha de inicio *</label>
            <input class="sg-input" type="date" id="edit-inicio" name="fecha_inicio" value="{{ old('fecha_inicio', date('Y-m-d', strtotime($campaniaSeleccionada['fecha_inicio']))) }}" required>
          </div>

          <div class="sg-field">
            <label class="sg-label" for="edit-fin">Fecha límite / cierre *</label>
            <input class="sg-input" type="date" id="edit-fin" name="fecha_fin" value="{{ old('fecha_fin', date('Y-m-d', strtotime($campaniaSeleccionada['fecha_fin']))) }}" required>
          </div>

          <div class="sg-field">
            <label class="sg-label">Cohortes participantes *</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 6px; padding: 8px; background: var(--sg-surface); border: 1px solid #9cabbc; border-radius: var(--sg-radius-sm); max-height: 120px; overflow-y: auto;">
              @foreach($cohortes as $coh)
                <label style="font-size: 13px; display: flex; align-items: center; gap: 4px;">
                  <input type="checkbox" name="cohortes[]" value="{{ $coh->id }}" {{ in_array($coh->id, old('cohortes', $cohortesIdsActuales)) ? 'checked' : '' }}>
                  {{ $coh->codigo_promocion }}
                </label>
              @endforeach
            </div>
          </div>
        </div>

        <div class="sg-field" style="margin-top: var(--sg-space-4);">
          <label class="sg-label">Canales de notificación habilitados</label>
          <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-top: 4px;">
            @foreach($canales as $canal)
              <label style="font-size: 14px; display: flex; align-items: center; gap: 6px;">
                <input type="checkbox" name="canales[]" value="{{ $canal->id }}" {{ in_array($canal->id, old('canales', $canalesIdsActuales)) ? 'checked' : '' }}>
                {{ $canal->nombre }}
              </label>
            @endforeach
          </div>
        </div>

        <div class="sg-field" style="margin-top: var(--sg-space-4);">
          <label class="sg-label" for="edit-descripcion">Descripción y objetivos de la campaña</label>
          <textarea class="sg-textarea" id="edit-descripcion" name="descripcion" rows="2">{{ old('descripcion', $campaniaSeleccionada['descripcion']) }}</textarea>
        </div>

        <div class="sg-actions" style="margin-top: var(--sg-space-4);">
          <button class="sg-button" type="submit">Guardar cambios de la campaña</button>
        </div>
      </form>
    </div>
  </details>

  {{-- BARRA DE PROGRESO DE COBERTURA --}}
  <div style="background: var(--sg-soft); border-radius: var(--sg-radius); padding: var(--sg-space-4); margin-bottom: var(--sg-space-5);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
      <span style="font-weight: 600; font-size: 14px;">Tasa de Cobertura de Respuestas:</span>
      <span style="font-size: 15px; font-weight: 700; color: var(--sg-accent);">
        {{ $campaniaSeleccionada['respuestas_count'] }} respondidas de {{ $campaniaSeleccionada['invitaciones_count'] }} convocados ({{ number_format($campaniaSeleccionada['cobertura_pct'], 1) }}%)
      </span>
    </div>

    <div style="width: 100%; height: 16px; background: #cbd8e4; border-radius: 8px; overflow: hidden; position: relative;" role="progressbar" aria-valuenow="{{ $campaniaSeleccionada['cobertura_pct'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Porcentaje de cobertura de respuestas">
      <div style="width: {{ min(100, $campaniaSeleccionada['cobertura_pct']) }}%; height: 100%; background: linear-gradient(90deg, var(--sg-accent), var(--sg-navy)); border-radius: 8px; transition: width 0.3s ease;"></div>
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; color: var(--sg-muted); flex-wrap: wrap; gap: 6px;">
      <span>Apertura: {{ date('d/m/Y', strtotime($campaniaSeleccionada['fecha_inicio'])) }}</span>
      <span>Pendientes de respuesta: <strong>{{ $campaniaSeleccionada['pendientes_count'] }} egresados</strong> (sin confundir con desempleo)</span>
      <span>Cierre programado: {{ date('d/m/Y', strtotime($campaniaSeleccionada['fecha_fin'])) }}</span>
    </div>
  </div>

  {{-- COHORTES Y CANALES ASOCIADOS --}}
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--sg-space-4); margin-bottom: var(--sg-space-6);">
    <div style="background: var(--sg-surface); border: var(--sg-stroke); padding: var(--sg-space-4); border-radius: var(--sg-radius);">
      <h3 style="font-size: 14px; margin: 0 0 8px; font-weight: 650; color: var(--sg-navy);">Cohortes vinculadas en la base de datos</h3>
      <div style="display: flex; gap: 6px; flex-wrap: wrap;">
        @if(isset($campaniaSeleccionada['cohortes']) && count($campaniaSeleccionada['cohortes']) > 0)
          @foreach ($campaniaSeleccionada['cohortes'] as $coh)
            <span class="sg-badge" style="font-size: 13px; padding: 4px 10px;">
              {{ $coh->codigo_promocion ?? $coh }} ({{ $coh->anio_ingreso ?? 'Promoción' }})
            </span>
          @endforeach
        @else
          <span style="font-size: 13px; color: var(--sg-muted);">Sin cohortes asignadas todavía.</span>
        @endif
      </div>
      <p style="font-size: 12px; color: var(--sg-muted); margin-top: 8px;">Al presionar 'Emitir invitaciones', el sistema selecciona los egresados de estas cohortes.</p>
    </div>

    <div style="background: var(--sg-surface); border: var(--sg-stroke); padding: var(--sg-space-4); border-radius: var(--sg-radius);">
      <h3 style="font-size: 14px; margin: 0 0 8px; font-weight: 650; color: var(--sg-navy);">Canales de despacho autorizados</h3>
      <div style="display: flex; gap: 6px; flex-wrap: wrap;">
        @if(isset($campaniaSeleccionada['canales']) && count($campaniaSeleccionada['canales']) > 0)
          @foreach ($campaniaSeleccionada['canales'] as $canal)
            <span class="sg-badge" style="font-size: 13px; padding: 4px 10px;">{{ $canal->nombre ?? $canal }}</span>
          @endforeach
        @else
          <span class="sg-badge">Correo institucional (@unitru.edu.pe)</span>
          <span class="sg-badge">WhatsApp oficial</span>
        @endif
      </div>
      <p style="font-size: 12px; color: var(--sg-muted); margin-top: 8px;">Cada invitación cuenta con un token hash criptográfico único intransferible.</p>
    </div>
  </div>

  {{-- TABLA DE INVITACIONES Y ESTADOS OFICIALES --}}
  <div class="sg-section-title">
    <div>
      <h3 style="font-size: 17px; margin: 0;">Padrón de egresados convocados en esta campaña ({{ count($invitacionesSeleccionadas) }})</h3>
      <p style="font-size: 13px; color: var(--sg-muted); margin-top: 2px;">
        Trazabilidad de estados: <strong>PENDIENTE</strong>, <strong>COMPLETADA</strong> o <strong>EXPIRADA</strong>. Se audita fecha de envío y recordatorios.
      </p>
    </div>
  </div>

  @if($invitacionesSeleccionadas->isEmpty())
    <div class="sg-empty" style="text-align: center; padding: var(--sg-space-5);">
      <h4>Aún no se han emitido invitaciones para esta campaña</h4>
      <p>Haga clic en el botón <strong>"Emitir invitaciones por cohorte"</strong> para generar los tokens seguros a partir del padrón de egresados.</p>
    </div>
  @else
    <div class="sg-table-wrap" tabindex="0" role="region" aria-label="Tabla de egresados convocados en la campaña">
      <table class="sg-table">
        <thead>
          <tr>
            <th scope="col">Matrícula</th>
            <th scope="col">Egresado convocado</th>
            <th scope="col">Cohorte</th>
            <th scope="col">Fecha Envío</th>
            <th scope="col">Estado Invitación</th>
            <th scope="col">Fecha Respuesta</th>
            <th scope="col" class="sg-numeric">Recordatorios</th>
            <th scope="col">Acción</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($invitacionesSeleccionadas as $inv)
            @php
              $eg = $inv->egresado;
              $badgeEstado = match($inv->estado) {
                'COMPLETADA' => 'sg-badge-success',
                'PENDIENTE' => 'sg-badge-warning',
                'EXPIRADA' => 'sg-badge-danger',
                default => 'sg-badge',
              };
            @endphp
            <tr>
              <td style="font-family: monospace;">{{ $eg->codigo_matricula ?? '—' }}</td>
              <td>
                <strong>{{ $eg ? "{$eg->apellido_paterno} {$eg->apellido_materno}, {$eg->nombres}" : 'Egresado' }}</strong>
                <div style="font-size: 12px; color: var(--sg-muted);">{{ $eg->correo_institucional ?? '—' }}</div>
              </td>
              <td><span class="sg-badge">{{ $eg->cohorte->codigo_promocion ?? '—' }}</span></td>
              <td style="font-size: 13px;">{{ $inv->enviado_en ? date('d/m/Y H:i', strtotime($inv->enviado_en)) : '—' }}</td>
              <td>
                <span class="sg-badge {{ $badgeEstado }}">{{ $inv->estado }}</span>
                @if($inv->entrega_fallida)
                  <span class="sg-badge sg-badge-danger" style="margin-left: 2px;">Envío fallido</span>
                @endif
              </td>
              <td style="font-size: 13px; color: {{ $inv->respondido_en ? 'var(--sg-success)' : 'var(--sg-muted)' }};">
                {{ $inv->respondido_en ? date('d/m/Y H:i', strtotime($inv->respondido_en)) : '—' }}
              </td>
              <td class="sg-numeric">{{ $inv->recordatorios_enviados }}</td>
              <td>
                @if($inv->estado === 'COMPLETADA')
                  <span style="font-size: 12px; color: var(--sg-success); font-weight: 600;">✓ Encuesta respondida</span>
                @elseif($inv->estado === 'PENDIENTE')
                  <form method="POST" action="{{ route('seguimiento-egresado.campanias.recordatorio', $inv->id) }}" style="display: inline;">
                    @csrf
                    <button class="sg-button sg-button-secondary" type="submit" style="padding: 4px 8px; min-height: 28px; font-size: 11px;">
                      Reenviar recordatorio
                    </button>
                  </form>
                @else
                  <span style="font-size: 12px; color: var(--sg-muted);">Expirada</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endif

@endsection
