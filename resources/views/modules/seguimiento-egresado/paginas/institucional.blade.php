@extends('modules.seguimiento-egresado.layout')
@section('title', 'Evaluación institucional')
@section('section_name', 'Evaluación institucional')

@section('content')
<section class="sg-heading" aria-labelledby="sg-institucional-title">
  <div class="sg-kicker">ASEGURAMIENTO DE LA CALIDAD Y GESTIÓN DE DIRECCIÓN · SINEACE / ICACIT</div>
  <h1 id="sg-institucional-title">Gestión y evaluación institucional</h1>
  <p>Vista de Dirección de Escuela para la evaluación de Objetivos Educacionales (OE), monitoreo de competencias, registro de planes de acción y formalización de mejoras curriculares.</p>
  <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
    <span class="sg-badge sg-badge-success">Módulo Oficial Activo</span>
    <span class="sg-badge">Acreditación y Calidad</span>
    <span class="sg-badge">Validación Oficial I3 e I6</span>
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px; margin-left: auto;" href="{{ route('seguimiento-egresado.reportes.formato', 'F10') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
      <span>Exportar Informe F10 (OE)</span>
    </a>
    <a class="sg-button sg-button-secondary" style="padding: 4px 10px; min-height: 28px; font-size: 12px;" href="{{ route('seguimiento-egresado.reportes.formato', 'F11') }}">
      @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
      <span>Exportar Plan F11 (Mejoras)</span>
    </a>
  </div>
</section>

{{-- MENSAJES DE ESTADO / ALERTAS --}}
@if(session('success'))
  <div class="sg-alert" role="status" style="margin-bottom: var(--sg-space-4); border-left-color: var(--sg-success); background: #e7f7f0;">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Acción de Dirección procesada:</strong>
      <p>{{ session('success') }}</p>
    </div>
  </div>
@endif

@if($errors->any())
  <div class="sg-alert sg-alert-danger" role="alert" style="margin-bottom: var(--sg-space-4);">
    @include('modules.seguimiento-egresado.partials.icon', ['name' => 'info'])
    <div>
      <strong>Observaciones de validación institucional:</strong>
      <ul style="margin: 4px 0 0; padding-left: 18px; font-size: 13px;">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  </div>
@endif

{{-- AVISO INSTITUCIONAL Y REGLAS DE INDICADORES --}}
<div class="sg-alert" role="note" style="margin-bottom: var(--sg-space-6);" aria-labelledby="sg-nota-institucional">
  @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
  <div>
    <strong id="sg-nota-institucional">Reglas oficiales de acreditación:</strong>
    <p>
      • <strong>Indicador I3 (Objetivos Educacionales):</strong> El denominador oficial es el <strong>total de OEs del programa ({{ $totalOePlan }} OEs vigentes)</strong>, no únicamente los evaluados en la muestra. Solo computan OEs con evaluación en estado <code>VALIDADA</code> por Dirección.<br>
      • <strong>Indicador I6 (Mejoras Curriculares):</strong> Se reporta como un <strong>conteo absoluto de mejoras</strong> con estado <code>IMPLEMENTADA</code> y <code>origen_seguimiento = TRUE</code> respaldadas con resolución de Consejo de Facultad (no en porcentaje).
    </p>
  </div>
</div>

{{-- TABLERO DE CONTROL DE DIRECCIÓN DE ESCUELA --}}
<section class="sg-panel" aria-labelledby="sg-resumen-dir-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <h2 id="sg-resumen-dir-title">Tablero de control de la Dirección de Escuela</h2>
      <p>Métricas de acreditación ICACIT / SINEACE consolidadas en base de datos.</p>
    </div>
    <span class="sg-badge">Periodo activo: {{ $periodoFiltro === 'todos' ? 'Consolidado histórico' : $periodoFiltro }}</span>
  </div>

  <div class="sg-metric-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--sg-space-4); margin-top: var(--sg-space-3);">
    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-navy);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Objetivos Educacionales (I3)</div>
      <div style="font-size: 26px; font-weight: 700; color: var(--sg-navy); margin-top: 4px;">
        {{ $oeLogradosCount }} de {{ $totalOePlan }} logrados
      </div>
      <div style="font-size: 12px; color: var(--sg-success); font-weight: 600; margin-top: 4px;">
        Denominador oficial: {{ $totalOePlan }} OEs del plan
      </div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-success);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Logro promedio de OEs</div>
      <div style="font-size: 26px; font-weight: 700; color: var(--sg-success); margin-top: 4px;">{{ $logroPromedioOe }}%</div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Sobre evaluaciones validadas</div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-warning);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Competencias del perfil</div>
      <div style="font-size: 26px; font-weight: 700; color: var(--sg-warning); margin-top: 4px;">
        {{ $competenciasConformes }} de {{ $totalCompetencias }} conformes
      </div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Monitoreadas con empleadores</div>
    </div>

    <div style="background: var(--sg-soft); padding: var(--sg-space-4); border-radius: var(--sg-radius); border-left: 4px solid var(--sg-accent);">
      <div style="font-size: var(--sg-text-sm); color: var(--sg-muted); font-weight: 600;">Mejoras Curriculares (I6)</div>
      <div style="font-size: 26px; font-weight: 700; color: var(--sg-accent); margin-top: 4px;">
        {{ $mejorasImplementadasI6 }} implementadas
      </div>
      <div style="font-size: 12px; color: var(--sg-muted); margin-top: 4px;">Atribuibles al seguimiento formal</div>
    </div>
  </div>
</section>

{{-- SECCIÓN REGISTRAR EVALUACIÓN DE OE (EXPANDIBLE) --}}
<details class="sg-panel" style="margin-bottom: var(--sg-space-6);" id="seccion-evaluar-oe">
  <summary style="font-weight: 650; font-size: 16px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
    <span>➕ Dirección: Registrar o Validar Evaluación de Objetivo Educacional (OE) <span class="sg-badge sg-badge-success" style="margin-left: 8px;">Acredita I3</span></span>
    <span style="font-size: 13px; color: var(--sg-accent); text-decoration: underline;">Mostrar / Ocultar formulario</span>
  </summary>
  <div style="margin-top: var(--sg-space-5); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
    <p style="font-size: 14px; color: var(--sg-muted); margin-bottom: var(--sg-space-4);">
      Permite al Director registrar formalmente la evaluación trianual de un OE para una cohorte y periodo determinado.
    </p>

    <form method="POST" action="{{ route('seguimiento-egresado.institucional.guardar-oe') }}">
      @csrf
      <div class="sg-form-grid">
        <div class="sg-field">
          <label class="sg-label" for="oe-id">Objetivo Educacional (OE) *</label>
          <select class="sg-select" id="oe-id" name="id_objetivo" required>
            @foreach($objetivosEducacionales as $oe)
              <option value="{{ $oe->id }}">{{ $oe->codigo }} — {{ $oe->titulo }}</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-cohorte">Cohorte evaluada *</label>
          <select class="sg-select" id="oe-cohorte" name="id_cohorte" required>
            @foreach($cohortes as $coh)
              <option value="{{ $coh->id }}">{{ $coh->codigo_promocion }} (Ingreso {{ $coh->anio_ingreso }})</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-periodo">Periodo de evaluación *</label>
          <input class="sg-input" type="text" id="oe-periodo" name="periodo" value="2024-II" required placeholder="Ej. 2024-II">
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="oe-nivel">Nivel de logro alcanzado *</label>
          <select class="sg-select" id="oe-nivel" name="id_nivel_logro" required>
            @foreach($nivelesLogro as $nl)
              <option value="{{ $nl->id }}" {{ $nl->codigo === 'BUENO' ? 'selected' : '' }}>{{ $nl->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-meta">Meta institucional (%) *</label>
          <input class="sg-input" type="number" step="0.1" min="0" max="100" id="oe-meta" name="meta_porcentaje" value="75.0" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-logro">Logro medido (%) *</label>
          <input class="sg-input" type="number" step="0.1" min="0" max="100" id="oe-logro" name="logro_porcentaje" value="82.5" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-estado">Estado de validación *</label>
          <select class="sg-select" id="oe-estado" name="estado" required>
            <option value="VALIDADA" selected>VALIDADA (Aprobada por Director de Escuela)</option>
            <option value="BORRADOR">BORRADOR (Propuesta técnica)</option>
          </select>
          <p class="sg-help">Solo las evaluaciones VALIDADAS computan en I3.</p>
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="oe-fecha">Fecha de sesión / evaluación *</label>
          <input class="sg-input" type="date" id="oe-fecha" name="fecha_evaluacion" value="{{ date('Y-m-d') }}" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="oe-fuente">Fuente documental y evidencias</label>
          <input class="sg-input" type="text" id="oe-fuente" name="fuente_evidencia" value="Informe de Empleabilidad F2 y Encuesta Empresarial F3" placeholder="Informes F2, F3 o actas de comité consultivo">
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="oe-acciones">Acciones derivadas y recomendaciones de mejora</label>
        <textarea class="sg-textarea" id="oe-acciones" name="acciones_derivadas" rows="2" placeholder="Acciones acordadas para retroalimentar el plan curricular o la docencia."></textarea>
      </div>

      <div class="sg-actions" style="margin-top: var(--sg-space-5);">
        <button class="sg-button" type="submit">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
          <span>Guardar evaluación de OE</span>
        </button>
      </div>
    </form>
  </div>
</details>

{{-- SECCIÓN REGISTRAR PLAN DE MEJORA CURRICULAR (EXPANDIBLE) --}}
<details class="sg-panel" style="margin-bottom: var(--sg-space-6);" id="seccion-registrar-mejora">
  <summary style="font-weight: 650; font-size: 16px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
    <span>➕ Dirección: Formalizar Plan de Mejora Curricular <span class="sg-badge sg-badge-success" style="margin-left: 8px;">Acredita I6</span></span>
    <span style="font-size: 13px; color: var(--sg-accent); text-decoration: underline;">Mostrar / Ocultar formulario</span>
  </summary>
  <div style="margin-top: var(--sg-space-5); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
    <p style="font-size: 14px; color: var(--sg-muted); margin-bottom: var(--sg-space-4);">
      Permite al Director registrar formalmente una mejora curricular atribuible al seguimiento de egresados, con su código, documento de resolución y estado.
    </p>

    <form method="POST" action="{{ route('seguimiento-egresado.institucional.guardar-mejora') }}">
      @csrf
      <div class="sg-form-grid">
        <div class="sg-field">
          <label class="sg-label" for="mejora-codigo">Código identificador *</label>
          <input class="sg-input" type="text" id="mejora-codigo" name="codigo" value="MC-{{ date('Y') }}-0{{ count($mejorasCurriculares) + 1 }}" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-plan">Plan curricular afectado *</label>
          <select class="sg-select" id="mejora-plan" name="id_plan_curricular" required>
            @foreach($planesCurriculares as $plan)
              <option value="{{ $plan->id }}">{{ $plan->codigo_version }} ({{ $plan->estado }})</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-tipo">Tipo de modificación *</label>
          <select class="sg-select" id="mejora-tipo" name="tipo" required>
            <option value="PERFIL">Modificación del Perfil de Egreso</option>
            <option value="OE">Ajuste de Objetivo Educacional</option>
            <option value="OTRA">Actualización de Syllabus / Laboratorios</option>
          </select>
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="mejora-dice">Dice (texto actual) *</label>
          <textarea class="sg-textarea" id="mejora-dice" name="dice" rows="2" placeholder="Texto vigente que se modifica">{{ old('dice') }}</textarea>
          <p class="sg-help">Obligatorio salvo en la actualización de syllabus o laboratorios.</p>
        </div>
        <div class="sg-field">
          <label class="sg-label" for="mejora-debe-decir">Debe decir (texto propuesto) *</label>
          <textarea class="sg-textarea" id="mejora-debe-decir" name="debe_decir" rows="2" placeholder="Texto que debe quedar">{{ old('debe_decir') }}</textarea>
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="mejora-titulo">Título de la mejora curricular *</label>
        <input class="sg-input" type="text" id="mejora-titulo" name="titulo" value="Modernización del laboratorio y syllabus de Cloud Computing y DevOps" required>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="mejora-estado">Estado del plan *</label>
          <select class="sg-select" id="mejora-estado" name="estado" required>
            <option value="IMPLEMENTADA" selected>IMPLEMENTADA (Con resolución y evidencia activa — Computa I6)</option>
            <option value="EN_EJECUCION">EN EJECUCIÓN (Aprobada en proceso)</option>
            <option value="APROBADA">APROBADA por Consejo de Facultad</option>
            <option value="PROPUESTA">PROPUESTA (En formulación)</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-origen">¿Se origina en el seguimiento al egresado? *</label>
          <select class="sg-select" id="mejora-origen" name="origen_seguimiento" required>
            <option value="1" selected>SÍ — Derivada de encuestas de egresados/empleadores</option>
            <option value="0">NO — Iniciativa de otra fuente</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-periodo">Periodo de aplicación</label>
          <input class="sg-input" type="text" id="mejora-periodo" name="periodo" value="2024-I" placeholder="Ej. 2024-I">
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="mejora-doc">Documento oficial de respaldo</label>
          <input class="sg-input" type="text" id="mejora-doc" name="documento_referencia" value="Resolución de Decanato R-0210-2024-D-FACINF" placeholder="Resolución Directoral o Acta de Consejo">
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-resp">Responsable asignado</label>
          <input class="sg-input" type="text" id="mejora-resp" name="responsable" value="Comité Curricular y Área de Ingeniería de Software">
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-freg">Fecha de registro *</label>
          <input class="sg-input" type="date" id="mejora-freg" name="fecha_registro" value="{{ date('Y-m-d') }}" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="mejora-fimp">Fecha de implementación</label>
          <input class="sg-input" type="date" id="mejora-fimp" name="fecha_implementacion" value="{{ date('Y-m-d') }}">
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="mejora-desc">Descripción y justificación técnica *</label>
        <textarea class="sg-textarea" id="mejora-desc" name="descripcion" rows="2" required placeholder="Explique las brechas detectadas en los egresados y cómo esta mejora las subsana.">Actualización de contenidos para incorporar pipelines CI/CD y despliegue sobre Kubernetes a solicitud de empleadores.</textarea>
      </div>

      <div class="sg-actions" style="margin-top: var(--sg-space-5);">
        <button class="sg-button" type="submit">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
          <span>Registrar mejora curricular</span>
        </button>
      </div>
    </form>
  </div>
</details>

{{-- SECCIÓN REGISTRAR EVALUACIÓN DE COMPETENCIA DEL PERFIL (EXPANDIBLE) --}}
<details class="sg-panel" style="margin-bottom: var(--sg-space-6);" id="seccion-evaluar-competencia">
  <summary style="font-weight: 650; font-size: 16px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
    <span>➕ Dirección: Registrar Evaluación de Competencia del Perfil <span class="sg-badge sg-badge-success" style="margin-left: 8px;">Criterio ICACIT</span></span>
    <span style="font-size: 13px; color: var(--sg-accent); text-decoration: underline;">Mostrar / Ocultar formulario</span>
  </summary>
  <div style="margin-top: var(--sg-space-5); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
    <p style="font-size: 14px; color: var(--sg-muted); margin-bottom: var(--sg-space-4);">
      Permite al Director registrar la evaluación de una competencia del perfil de egreso para una cohorte y periodo determinado.
    </p>

    <form method="POST" action="{{ route('seguimiento-egresado.institucional.guardar-competencia') }}">
      @csrf
      <div class="sg-form-grid">
        <div class="sg-field">
          <label class="sg-label" for="comp-id">Competencia del perfil *</label>
          <select class="sg-select" id="comp-id" name="id_competencia" required>
            @foreach($competencias as $c)
              <option value="{{ $c->id }}">{{ $c->codigo }} — {{ $c->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="comp-cohorte">Cohorte evaluada *</label>
          <select class="sg-select" id="comp-cohorte" name="id_cohorte" required>
            @foreach($cohortes as $coh)
              <option value="{{ $coh->id }}">{{ $coh->codigo_promocion }} (Ingreso {{ $coh->anio_ingreso }})</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="comp-periodo">Periodo de evaluación *</label>
          <input class="sg-input" type="text" id="comp-periodo" name="periodo" value="2024-II" required placeholder="Ej. 2024-II">
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="comp-nivel">Nivel de logro alcanzado *</label>
          <select class="sg-select" id="comp-nivel" name="id_nivel_logro" required>
            @foreach($nivelesLogro as $nl)
              <option value="{{ $nl->id }}" {{ $nl->codigo === 'BUENO' ? 'selected' : '' }}>{{ $nl->nombre }}</option>
            @endforeach
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="comp-logro">Logro alcanzado (%) *</label>
          <input class="sg-input" type="number" id="comp-logro" name="logro_porcentaje" step="0.1" min="0" max="100" value="80.0" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="comp-estado">Estado de validación *</label>
          <select class="sg-select" id="comp-estado" name="estado" required>
            <option value="VALIDADA" selected>VALIDADA por Dirección</option>
            <option value="BORRADOR">BORRADOR (En revisión)</option>
          </select>
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="comp-fecha">Fecha de sesión / evaluación *</label>
          <input class="sg-input" type="date" id="comp-fecha" name="fecha_evaluacion" value="{{ date('Y-m-d') }}" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="comp-fuente">Fuente documental y evidencias</label>
          <input class="sg-input" type="text" id="comp-fuente" name="fuente_evidencia" value="Encuesta Empresarial de Desempeño y Evaluación de Jefes Directos">
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="comp-obs">Observaciones y recomendaciones de mejora</label>
        <textarea class="sg-textarea" id="comp-obs" name="observacion" rows="2" placeholder="Observaciones de Dirección sobre el desempeño observado."></textarea>
      </div>

      <div class="sg-actions" style="margin-top: var(--sg-space-5);">
        <button class="sg-button" type="submit">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
          <span>Guardar evaluación de competencia</span>
        </button>
      </div>
    </form>
  </div>
</details>

{{-- SECCIÓN REGISTRAR PLAN DE ACCIÓN (INSERCIÓN / VINCULACIÓN / CONVENIOS - F5 Y F7) --}}
<details class="sg-panel" style="margin-bottom: var(--sg-space-6);" id="seccion-registrar-plan">
  <summary style="font-weight: 650; font-size: 16px; color: var(--sg-navy); cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
    <span>➕ Dirección: Formalizar Plan de Acción (Inserción / Vinculación / Convenios) <span class="sg-badge sg-badge-success" style="margin-left: 8px;">Formatos F5 y F7</span></span>
    <span style="font-size: 13px; color: var(--sg-accent); text-decoration: underline;">Mostrar / Ocultar formulario</span>
  </summary>
  <div style="margin-top: var(--sg-space-5); border-top: var(--sg-stroke); padding-top: var(--sg-space-4);">
    <p style="font-size: 14px; color: var(--sg-muted); margin-bottom: var(--sg-space-4);">
      Permite al Director registrar formalmente un plan de acción para inserción laboral (F5) o de vinculación y convenios con empleadores (F7).
    </p>

    <form method="POST" action="{{ route('seguimiento-egresado.institucional.guardar-plan') }}">
      @csrf
      <div class="sg-form-grid">
        <div class="sg-field">
          <label class="sg-label" for="plan-codigo">Código identificador *</label>
          <input class="sg-input" type="text" id="plan-codigo" name="codigo" value="PA-{{ date('Y') }}-0{{ count($planesAccion) + 1 }}" required>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-tipo">Tipo de Plan *</label>
          <select class="sg-select" id="plan-tipo" name="tipo" required>
            <option value="VINCULACION" selected>VINCULACION (Alianzas y Convenios con Empresas — Formato F7)</option>
            <option value="INSERCION">INSERCION (Bolsa de Trabajo y Empleabilidad — Formato F5)</option>
            <option value="COMPETENCIA">COMPETENCIA (Refuerzo del Perfil de Egreso)</option>
            <option value="EXTRACURRICULAR">EXTRACURRICULAR (Talleres y Certificaciones)</option>
            <option value="INFRAESTRUCTURA">INFRAESTRUCTURA (Laboratorios Especializados)</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-periodo">Periodo de aplicación *</label>
          <input class="sg-input" type="text" id="plan-periodo" name="periodo" value="2024-II" required placeholder="Ej. 2024-II">
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="plan-titulo">Título del Plan / Alianza *</label>
        <input class="sg-input" type="text" id="plan-titulo" name="titulo" value="Convenio Marco y Alianzas Estratégicas con Empresas del Sector TI para Prácticas y Empleabilidad" required>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="plan-estado">Estado del plan *</label>
          <select class="sg-select" id="plan-estado" name="estado" required>
            <option value="PLANIFICADA">PLANIFICADA</option>
            <option value="EN_EJECUCION" selected>EN EJECUCIÓN (Aprobada)</option>
            <option value="FINALIZADA">FINALIZADA</option>
            <option value="EVALUADA">EVALUADA</option>
          </select>
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-cumplimiento">Grado de cumplimiento (%)</label>
          <input class="sg-input" type="number" id="plan-cumplimiento" name="grado_cumplimiento" min="0" max="100" value="65">
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-flimite">Fecha límite / cumplimiento</label>
          <input class="sg-input" type="date" id="plan-flimite" name="fecha_limite" value="{{ date('Y-m-d', strtotime('+90 days')) }}">
        </div>
      </div>

      <div class="sg-form-grid" style="margin-top: var(--sg-space-4);">
        <div class="sg-field">
          <label class="sg-label" for="plan-resp">Responsable asignado</label>
          <input class="sg-input" type="text" id="plan-resp" name="responsable" value="Comisión de Vinculación y Prácticas Preprofesionales">
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-doc">Documento de respaldo / Resolución</label>
          <input class="sg-input" type="text" id="plan-doc" name="documento_referencia" value="Resolución de Decanato R-0045-2024-FACINF / Convenio Marco UNT">
        </div>

        <div class="sg-field">
          <label class="sg-label" for="plan-meta">Meta esperada</label>
          <input class="sg-input" type="text" id="plan-meta" name="meta_esperada" value="Formalizar 4 alianzas estratégicas e incorporar 25 egresados a proyectos empresariales">
        </div>
      </div>

      <div class="sg-field" style="margin-top: var(--sg-space-4);">
        <label class="sg-label" for="plan-actividad">Actividades programadas *</label>
        <textarea class="sg-textarea" id="plan-actividad" name="actividad" rows="2" required placeholder="Detalle las actividades de vinculación o inserción laboral programadas.">Mesas de trabajo con empleadores de software regional, talleres de inserción profesional y jornadas de reclutamiento.</textarea>
      </div>

      <div class="sg-actions" style="margin-top: var(--sg-space-5);">
        <button class="sg-button" type="submit">
          @include('modules.seguimiento-egresado.partials.icon', ['name' => 'clipboard'])
          <span>Registrar plan de acción</span>
        </button>
      </div>
    </form>
  </div>
</details>

{{-- SECCIÓN 1: EVALUACIÓN DE OBJETIVOS EDUCACIONALES (I3) --}}
<section class="sg-panel" aria-labelledby="sg-oe-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">DIMENSIÓN 1 · LOGRO DE OBJETIVOS EDUCACIONALES (I3)</span>
      <h2 id="sg-oe-title">Evaluación de Objetivos Educacionales del Programa</h2>
      <p>Logro alcanzado en el medio profesional contrastado con las metas del plan curricular.</p>
    </div>
    <span class="sg-badge sg-badge-success">{{ count($evaluacionesOe) }} Evaluaciones registradas</span>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-oe-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col" style="width: 80px;">Código</th>
          <th scope="col" style="min-width: 240px;">Objetivo Educacional (OE)</th>
          <th scope="col">Cohorte</th>
          <th scope="col">Periodo</th>
          <th scope="col" class="sg-numeric">Meta (%)</th>
          <th scope="col" class="sg-numeric">Logro (%)</th>
          <th scope="col">Estado</th>
          <th scope="col">Evidencia Oficial</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($evaluacionesOe as $oeEval)
          @php
            $logrado = ($oeEval->logro_porcentaje >= $oeEval->meta_porcentaje) || in_array($oeEval->id_nivel_logro, [1, 2]);
          @endphp
          <tr>
            <td style="font-weight: 700; color: var(--sg-navy);">{{ $oeEval->objetivo->codigo ?? 'OE' }}</td>
            <td>
              <div style="font-weight: 600; color: var(--sg-navy);">{{ $oeEval->objetivo->titulo ?? '—' }}</div>
              <p style="font-size: 13px; color: var(--sg-muted); margin: 2px 0 0;">{{ $oeEval->objetivo->descripcion ?? '' }}</p>
            </td>
            <td><span class="sg-badge">{{ $oeEval->cohorte->codigo_promocion ?? '—' }}</span></td>
            <td><span class="sg-badge">{{ $oeEval->periodo }}</span></td>
            <td class="sg-numeric" style="font-weight: 600;">{{ number_format($oeEval->meta_porcentaje, 1) }}%</td>
            <td class="sg-numeric">
              <strong style="color: {{ $logrado ? 'var(--sg-success)' : 'var(--sg-warning)' }}; font-size: 15px;">
                {{ number_format($oeEval->logro_porcentaje, 1) }}%
              </strong>
            </td>
            <td>
              <span class="sg-badge {{ $oeEval->estado === 'VALIDADA' ? 'sg-badge-success' : 'sg-badge-warning' }}">
                {{ $oeEval->estado }}
              </span>
            </td>
            <td style="font-size: 12px;">
              <div style="font-weight: 600;">{{ $oeEval->fuente_evidencia ?? 'Informes F2 y F3' }}</div>
              <div style="color: var(--sg-muted);">Evaluado: {{ date('d/m/Y', strtotime($oeEval->fecha_evaluacion)) }}</div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" style="text-align: center; color: var(--sg-muted); padding: var(--sg-space-4);">
              No hay evaluaciones de OEs registradas para los filtros aplicados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- SECCIÓN 2: COMPETENCIAS DEL GRADUADO --}}
<section class="sg-panel" aria-labelledby="sg-comp-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">DIMENSIÓN 2 · EVALUACIÓN DEL PERFIL DE EGRESO</span>
      <h2 id="sg-comp-title">Monitoreo de Competencias del Perfil</h2>
      <p>Apreciación de empleadores y autoevaluación del desempeño profesional en el sector TI.</p>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-comp-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col" style="width: 70px;">Código</th>
          <th scope="col">Competencia</th>
          <th scope="col">Tipo</th>
          <th scope="col">Plan Curricular</th>
          <th scope="col">Estado</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($competencias as $comp)
          <tr>
            <td style="font-weight: 700; color: var(--sg-navy);">{{ $comp->codigo }}</td>
            <td style="font-weight: 600;">{{ $comp->nombre }}</td>
            <td><span class="sg-badge">{{ $comp->tipo ?? 'ESPECIFICA' }}</span></td>
            <td><span class="sg-badge">{{ $comp->planCurricular->codigo_version ?? 'Plan 2017' }}</span></td>
            <td><span class="sg-badge sg-badge-success">Monitoreada</span></td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="text-align: center; color: var(--sg-muted); padding: var(--sg-space-4);">
              No hay competencias registradas.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- SECCIÓN 3: PLANES DE MEJORA CURRICULAR (I6) --}}
<section class="sg-panel" aria-labelledby="sg-mejoras-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">DIMENSIÓN 3 · PLANES DE MEJORA CURRICULAR (I6)</span>
      <h2 id="sg-mejoras-title">Mejoras Curriculares Implementadas</h2>
      <p>Acciones formalizadas con resolución aprobada derivadas del seguimiento a egresados.</p>
    </div>
    <span class="sg-badge">{{ count($mejorasCurriculares) }} Planes registrados</span>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-mejoras-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col">Código / Mejora</th>
          <th scope="col">Plan</th>
          <th scope="col">Tipo</th>
          <th scope="col">Estado</th>
          <th scope="col">Origen Seguimiento</th>
          <th scope="col">Documento Oficial</th>
          <th scope="col">Acción</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($mejorasCurriculares as $mej)
          @php
            $esMejoraSeleccionada = $mejoraSeleccionada && $mejoraSeleccionada->id === $mej->id;
          @endphp
          <tr style="{{ $esMejoraSeleccionada ? 'background: #edf4fb; font-weight: 550;' : '' }}">
            <td>
              <div style="font-weight: 600; color: var(--sg-navy);">{{ $mej->titulo }}</div>
              <div style="font-size: 12px; font-family: monospace; color: var(--sg-muted);">{{ $mej->codigo }} · {{ $mej->responsable }}</div>
            </td>
            <td><span class="sg-badge">{{ $mej->planCurricular->codigo_version ?? 'Plan 2017' }}</span></td>
            <td style="font-size: 13px;">{{ $mej->tipo }}</td>
            <td>
              <span class="sg-badge {{ $mej->estado === 'IMPLEMENTADA' ? 'sg-badge-success' : 'sg-badge-warning' }}">
                {{ $mej->estado }}
              </span>
            </td>
            <td>
              @if($mej->origen_seguimiento)
                <span class="sg-badge sg-badge-success">✓ Acredita I6</span>
              @else
                <span class="sg-badge">Otra fuente</span>
              @endif
            </td>
            <td style="font-size: 12px;"><strong>{{ $mej->documento_referencia ?? 'En trámite' }}</strong></td>
            <td>
              <a class="sg-button sg-button-secondary" style="padding: 6px 10px; min-height: 32px; font-size: 12px;"
                 href="{{ route('seguimiento-egresado.institucional', array_merge(request()->query(), ['mejora_id' => $mej->id])) }}#detalle-mejora">
                @if($esMejoraSeleccionada) ✓ En auditoría @else Auditar @endif
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align: center; color: var(--sg-muted); padding: var(--sg-space-4);">
              No hay mejoras curriculares registradas en la base de datos.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

{{-- FICHA DETALLADA DE LA MEJORA SELECCIONADA --}}
@if($mejoraSeleccionada)
<section class="sg-panel" id="detalle-mejora" aria-labelledby="sg-detalle-mejora-title" style="border: 2px solid #8da9c0;">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">FICHA DE AUDITORÍA Y TRAZABILIDAD DE LA MEJORA</span>
      <h2 id="sg-detalle-mejora-title">{{ $mejoraSeleccionada->titulo }}</h2>
      <p style="margin-top: 4px; color: var(--sg-muted);">
        Código: <strong>{{ $mejoraSeleccionada->codigo }}</strong> | Plan: <strong>{{ $mejoraSeleccionada->planCurricular->codigo_version ?? 'Plan 2017' }}</strong> | Periodo: <strong>{{ $mejoraSeleccionada->periodo ?? 'Vigente' }}</strong>
      </p>
    </div>
    <span class="sg-badge {{ $mejoraSeleccionada->estado === 'IMPLEMENTADA' ? 'sg-badge-success' : 'sg-badge-warning' }}" style="font-size: 14px; padding: 6px 12px;">
      Estado: {{ $mejoraSeleccionada->estado }}
    </span>
  </div>

  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--sg-space-4); margin-top: var(--sg-space-4);">
    <div style="background: var(--sg-surface); border: var(--sg-stroke); border-radius: var(--sg-radius); padding: var(--sg-space-4);">
      <h3 style="font-size: 14px; font-weight: 650; margin: 0 0 8px; color: var(--sg-navy);">Sustento documental y resolución oficial</h3>
      <p style="font-size: 13px; color: var(--sg-text); margin: 0; line-height: 1.5;">{{ $mejoraSeleccionada->origen_evidencia ?? 'Informes anuales de seguimiento' }}</p>
      <div style="margin-top: 12px; padding-top: 10px; border-top: var(--sg-stroke);">
        <strong style="font-size: 12px; color: var(--sg-muted); display: block;">Documento de resolución:</strong>
        <span style="font-size: 13px; font-weight: 600; color: var(--sg-navy);">{{ $mejoraSeleccionada->documento_referencia ?? 'En trámite de aprobación' }}</span>
      </div>
    </div>

    <div style="background: var(--sg-surface); border: var(--sg-stroke); border-radius: var(--sg-radius); padding: var(--sg-space-4);">
      <h3 style="font-size: 14px; font-weight: 650; margin: 0 0 8px; color: var(--sg-navy);">Responsables institucionales asignados</h3>
      <p style="font-size: 13px; color: var(--sg-text); margin: 0; line-height: 1.5;">{{ $mejoraSeleccionada->responsable ?? 'Comité Curricular' }}</p>
      <div style="margin-top: 12px; padding-top: 10px; border-top: var(--sg-stroke);">
        <strong style="font-size: 12px; color: var(--sg-muted); display: block;">Meta esperada:</strong>
        <span style="font-size: 13px; color: var(--sg-success); font-weight: 600;">{{ $mejoraSeleccionada->meta_esperada ?? '100% de estudiantes capacitados' }}</span>
      </div>
    </div>
  </div>

  <div style="background: var(--sg-surface); border: var(--sg-stroke); border-radius: var(--sg-radius); padding: var(--sg-space-4); margin-top: var(--sg-space-4);">
    <h3 style="font-size: 14px; font-weight: 650; margin: 0 0 8px; color: var(--sg-navy);">Acciones específicas aprobadas</h3>
    <p style="font-size: 14px; color: var(--sg-text); margin: 0; line-height: 1.6;">
      {{ $mejoraSeleccionada->descripcion }}
    </p>
  </div>
</section>
@endif

{{-- SECCIÓN 4: PLANES DE ACCIÓN, VINCULACIÓN Y CONVENIOS (FORMATOS F5 Y F7) --}}
<section class="sg-panel" aria-labelledby="sg-planes-title" style="margin-bottom: var(--sg-space-6);">
  <div class="sg-section-title">
    <div>
      <span class="sg-kicker">DIMENSIÓN 4 · ACCIÓN INSTITUCIONAL Y VINCULACIÓN EMPRESARIAL</span>
      <h2 id="sg-planes-title">Planes de Acción, Inserción y Convenios (F5 y F7)</h2>
      <p>Planes de inserción laboral (F5) y convenios/alianzas de vinculación con empleadores (F7) registrados en el sistema institucional.</p>
    </div>
    <div class="sg-actions">
      <a class="sg-button sg-button-secondary" style="font-size: 12px; padding: 4px 10px;" href="{{ route('seguimiento-egresado.reportes.formato', 'F5') }}">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
        <span>Descargar F5 (CSV)</span>
      </a>
      <a class="sg-button sg-button-secondary" style="font-size: 12px; padding: 4px 10px;" href="{{ route('seguimiento-egresado.reportes.formato', 'F7') }}">
        @include('modules.seguimiento-egresado.partials.icon', ['name' => 'chart'])
        <span>Descargar F7 (CSV)</span>
      </a>
    </div>
  </div>

  <div class="sg-table-wrap" tabindex="0" role="region" aria-labelledby="sg-planes-title">
    <table class="sg-table">
      <thead>
        <tr>
          <th scope="col" style="width: 85px;">Código</th>
          <th scope="col">Plan / Alianza Estratégica</th>
          <th scope="col">Tipo</th>
          <th scope="col">Periodo</th>
          <th scope="col">Estado</th>
          <th scope="col" class="sg-numeric">Avance (%)</th>
          <th scope="col">Documento / Resolución</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($planesAccion as $plan)
          <tr>
            <td style="font-weight: 700; color: var(--sg-navy);">{{ $plan->codigo }}</td>
            <td>
              <div style="font-weight: 600; color: var(--sg-navy);">{{ $plan->titulo }}</div>
              <p style="font-size: 12px; color: var(--sg-muted); margin: 2px 0 0;">{{ $plan->actividad }}</p>
            </td>
            <td>
              <span class="sg-badge {{ $plan->tipo === 'VINCULACION' ? 'sg-badge-success' : '' }}">
                {{ $plan->tipo }}
              </span>
            </td>
            <td><span class="sg-badge">{{ $plan->periodo }}</span></td>
            <td>
              <span class="sg-badge {{ $plan->estado === 'EN_EJECUCION' || $plan->estado === 'FINALIZADA' ? 'sg-badge-success' : 'sg-badge-warning' }}">
                {{ $plan->estado }}
              </span>
            </td>
            <td class="sg-numeric" style="font-weight: 600;">
              {{ $plan->grado_cumplimiento !== null ? $plan->grado_cumplimiento . '%' : '—' }}
            </td>
            <td style="font-size: 12px;">{{ $plan->documento_referencia ?? 'Resolución Directoral' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" style="text-align: center; color: var(--sg-muted); padding: var(--sg-space-4);">
              No hay planes de acción registrados en la base de datos.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@endsection
