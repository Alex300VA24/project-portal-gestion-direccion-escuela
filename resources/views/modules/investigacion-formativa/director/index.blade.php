@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Dirección de escuela')
@section('page-label', 'Dirección')

@section('content')

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Dirección de escuela</h1>
                <p class="if-lead">
                    Resumen de las secciones de sus escuelas asignadas. Incluye secciones cerradas y deshabilitadas de los periodos seleccionados.
                </p>
            </div>
        </div>

        @if ($escuelas === [])
            <div class="if-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                <span>
                    No tiene escuelas activas asignadas. Solicite la importación de su alcance
                    de dirección.
                </span>
            </div>
        @else
            <form
                class="if-panel if-form-grid"
                method="GET"
                action="{{ route('investigacion-formativa.director.index') }}"
                aria-label="Filtros del resumen"
            >
                <div class="if-field">
                    <label for="filtro-escuela">Escuela</label>
                    <select id="filtro-escuela" name="escuela">
                        <option value="">Todas las escuelas</option>
                        @foreach ($escuelas as $escuela)
                            <option value="{{ $escuela->id_escuela }}" @selected(($filtros['escuela'] ?? null) === (int) $escuela->id_escuela)>
                                {{ $escuela->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="if-field">
                    <label for="filtro-periodo">Periodo</label>
                    <select id="filtro-periodo" name="periodo">
                        <option value="">Todos los periodos</option>
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo }}" @selected(($filtros['periodo'] ?? null) === $periodo)>
                                {{ $periodo }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="if-field">
                    <label for="filtro-curso">Curso</label>
                    <select id="filtro-curso" name="curso">
                        <option value="">Todos los cursos</option>
                        @foreach ($cursos as $curso)
                            <option value="{{ $curso->id_curso }}" @selected(($filtros['curso'] ?? null) === (int) $curso->id_curso)>
                                {{ $curso->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="if-field">
                    <label for="filtro-linea">Línea de investigación</label>
                    <select id="filtro-linea" name="linea">
                        <option value="">Todas las líneas</option>
                        @foreach ($lineas as $linea)
                            <option value="{{ $linea->id_linea }}" @selected(($filtros['linea'] ?? null) === (int) $linea->id_linea)>
                                {{ $linea->nombre }}
                            </option>
                        @endforeach
                    </select>
                    <p class="if-field-hint">
                        Incluye las secciones con al menos un proyecto de la línea seleccionada.
                    </p>
                </div>

                <div>
                    <button class="if-button" type="submit">Filtrar</button>
                </div>
            </form>

            <dl class="if-inline-stats" style="margin-top: 22px;" aria-label="Totales de las secciones filtradas">
                <div>
                    <dt>Secciones</dt>
                    <dd>{{ $totales->secciones }}</dd>
                </div>
                <div>
                    <dt>Habilitadas</dt>
                    <dd>{{ $totales->habilitadas }}</dd>
                </div>
                <div>
                    <dt>Grupos</dt>
                    <dd>{{ $totales->grupos }}</dd>
                </div>
                <div>
                    <dt>Proyectos</dt>
                    <dd>{{ $totales->proyectos }}</dd>
                </div>
                <div>
                    <dt>Completos</dt>
                    <dd>{{ $totales->proyectos_completos }}</dd>
                </div>
                <div>
                    <dt>Pendientes</dt>
                    <dd>{{ $totales->proyectos_pendientes }}</dd>
                </div>
                <div>
                    <dt>Nota final &gt; 16</dt>
                    <dd>{{ $totales->mayores_16 }}</dd>
                </div>
                <div>
                    <dt>Nota final &gt; 18</dt>
                    <dd>{{ $totales->mayores_18 }}</dd>
                </div>
                <div>
                    <dt>Avances evaluados</dt>
                    <dd>{{ $totales->evaluaciones }}</dd>
                </div>
                <div>
                    <dt>Respuestas</dt>
                    <dd>{{ $totales->respuestas }}</dd>
                </div>
                <div>
                    <dt>Trabajos aprobados</dt>
                    <dd>{{ $totales->aprobados }}</dd>
                </div>
                <div>
                    <dt>Trabajos destacados</dt>
                    <dd>{{ $totales->destacados }}</dd>
                </div>
            </dl>

            <p class="if-note" style="margin-top: 12px;">
                La participación usa respuestas / participantes activos actuales.
                Puede superar el 100% después de retiros. Sin participantes activos, la tasa no se calcula.
            </p>

            @php
                $experienciaIndicador = $indicadores->experiencia_curricular;
                $satisfaccionIndicador = $indicadores->satisfaccion;
                $logroIndicador = $indicadores->logro_competencias;
            @endphp

            <section
                id="indicadores"
                class="if-panel if-stack"
                style="margin-top: 22px; padding: 22px;"
                aria-labelledby="titulo-indicadores"
            >
                <div class="if-panel-heading">
                    <div>
                        <h2 id="titulo-indicadores">Indicadores de alcance</h2>
                        <p>
                            Los indicadores usan el alcance de escuela, periodo y curso seleccionado.
                            Cada valor muestra su regla, denominador y fuente de entrada.
                        </p>
                    </div>
                </div>

                @if (($filtros['linea'] ?? null) !== null)
                    <p class="if-note">
                        El filtro de línea no reduce el denominador del indicador 3.1. Las experiencias
                        curriculares no tienen línea; la encuesta del indicador 3.2 no se desagrega por línea.
                        El indicador 3.3 sí restringe la evidencia a los proyectos de la línea seleccionada.
                    </p>
                @endif

                <div class="if-grid-wide">
                    <div class="if-card">
                        <h3 class="if-card-title">Indicador 3.1 · Experiencias curriculares</h3>
                        <p>{{ $experienciaIndicador->nombre }}</p>
                        <p style="font-size: 1.8rem; font-weight: 700; margin: 8px 0;">
                            {{ $experienciaIndicador->valor === null ? 'No calculable' : $experienciaIndicador->valor.'%' }}
                        </p>

                        <dl class="if-inline-stats" aria-label="Numerador y denominador del indicador 3.1">
                            <div>
                                <dt>Con IF</dt>
                                <dd>{{ $experienciaIndicador->numerador }}</dd>
                            </div>
                            <div>
                                <dt>Total</dt>
                                <dd>{{ $experienciaIndicador->denominador }}</dd>
                            </div>
                            <div>
                                <dt>Sin clasificar</dt>
                                <dd>{{ $experienciaIndicador->sin_clasificar }}</dd>
                            </div>
                        </dl>

                        <p class="if-note" style="margin-top: 12px;">
                            <strong>Regla:</strong> {{ $experienciaIndicador->regla }}
                        </p>
                        <p class="if-note">
                            <strong>Unidad:</strong> {{ $experienciaIndicador->unidad }}
                        </p>

                        @if ($experienciaIndicador->sin_clasificar > 0)
                            <div class="if-alert" role="status" style="margin-top: 12px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                <span>
                                    Hay experiencias sin una entrada de fuente. El valor no debe interpretarse
                                    como un dato institucional definitivo hasta completar esa clasificación.
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="if-card">
                        <h3 class="if-card-title">Indicador 3.2 · Satisfacción estudiantil</h3>
                        <p>{{ $satisfaccionIndicador->nombre }}</p>
                        <p style="font-size: 1.8rem; font-weight: 700; margin: 8px 0;">
                            {{ $satisfaccionIndicador->valor === null ? 'No calculable' : $satisfaccionIndicador->valor.'%' }}
                        </p>

                        <dl class="if-inline-stats" aria-label="Numerador y denominador del indicador 3.2">
                            <div>
                                <dt>Satisfechos</dt>
                                <dd>{{ $satisfaccionIndicador->numerador }}</dd>
                            </div>
                            <div>
                                <dt>Respondientes</dt>
                                <dd>{{ $satisfaccionIndicador->denominador }}</dd>
                            </div>
                        </dl>

                        <p class="if-note" style="margin-top: 12px;">
                            <strong>Pregunta:</strong>
                            {{ $satisfaccionIndicador->pregunta ?? 'Sin pregunta configurada' }}
                        </p>
                        <p class="if-note">
                            <strong>Regla:</strong> {{ $satisfaccionIndicador->regla }}
                        </p>
                        <p class="if-note">
                            <strong>Alcance:</strong> {{ $satisfaccionIndicador->alcance }}
                        </p>

                        @if ($satisfaccionIndicador->provisional)
                            <p style="margin-top: 12px;">
                                <span class="if-status is-warning">Configuración provisional</span>
                            </p>
                        @endif

                        @if ($satisfaccionIndicador->fuente !== null)
                            <p class="if-note">Fuente: {{ $satisfaccionIndicador->fuente }}</p>
                        @endif

                        @if ($satisfaccionIndicador->motivo !== null)
                            <div class="if-alert" role="status" style="margin-top: 12px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                <span>{{ $satisfaccionIndicador->motivo }}</span>
                            </div>
                        @endif

                        @if ($satisfaccionIndicador->categorias !== [])
                            <div class="if-table-wrap" style="margin-top: 16px;">
                                <table class="if-table">
                                    <caption class="sr-only">Distribución de respuestas por categoría de satisfacción</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Valor</th>
                                            <th scope="col">Categoría</th>
                                            <th scope="col">Estudiantes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($satisfaccionIndicador->categorias as $categoria)
                                            <tr>
                                                <td data-label="Valor">{{ $categoria->valor }}</td>
                                                <td data-label="Categoría">
                                                    {{ $categoria->etiqueta }}
                                                    @if ($categoria->satisfecho)
                                                        <span class="if-status is-success">Satisfactoria</span>
                                                    @endif
                                                </td>
                                                <td data-label="Estudiantes">{{ $categoria->conteo }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="if-card">
                        <h3 class="if-card-title">Indicador 3.3 · Logro de competencias</h3>
                        <p>{{ $logroIndicador->nombre }}</p>
                        <p style="font-size: 1.8rem; font-weight: 700; margin: 8px 0;">
                            {{ $logroIndicador->valor === null ? 'No calculable' : $logroIndicador->valor.'%' }}
                        </p>

                        <dl class="if-inline-stats" aria-label="Numerador, denominador y cobertura del indicador 3.3">
                            <div>
                                <dt>En banda lograda</dt>
                                <dd>{{ $logroIndicador->numerador }}</dd>
                            </div>
                            <div>
                                <dt>Estudiantes válidos</dt>
                                <dd>{{ $logroIndicador->denominador }}</dd>
                            </div>
                            <div>
                                <dt>Cobertura</dt>
                                <dd>
                                    {{ $logroIndicador->cobertura === null ? 'No calculable' : $logroIndicador->cobertura.'%' }}
                                </dd>
                            </div>
                        </dl>

                        <p class="if-note" style="margin-top: 12px;">
                            <strong>Regla:</strong> {{ $logroIndicador->regla }}
                        </p>

                        @if ($logroIndicador->provisional)
                            <p style="margin-top: 12px;">
                                <span class="if-status is-warning">Configuración provisional</span>
                            </p>
                        @endif

                        @if ($logroIndicador->fuente !== null)
                            <p class="if-note">Fuente: {{ $logroIndicador->fuente }}</p>
                        @endif

                        @if ($logroIndicador->motivo !== null)
                            <div class="if-alert" role="status" style="margin-top: 12px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                                <span>{{ $logroIndicador->motivo }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="if-card-title">Experiencias curriculares del denominador</h3>
                    @if ($experienciaIndicador->experiencias === [])
                        <div class="if-empty" style="min-height: 160px;">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                            <h3>Sin experiencias implementadas</h3>
                            <p>El alcance seleccionado no tiene secciones de cursos implementadas.</p>
                        </div>
                    @else
                        <div class="if-table-wrap" style="margin-top: 12px;">
                            <table class="if-table">
                                <caption class="sr-only">Experiencias curriculares y clasificación del indicador 3.1</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Escuela</th>
                                        <th scope="col">Periodo</th>
                                        <th scope="col">Curso</th>
                                        <th scope="col">Secciones</th>
                                        <th scope="col">Aplica IF</th>
                                        <th scope="col">Fuente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($experienciaIndicador->experiencias as $experiencia)
                                        <tr>
                                            <td data-label="Escuela">{{ $experiencia->escuela }}</td>
                                            <td data-label="Periodo">{{ $experiencia->periodo }}</td>
                                            <td data-label="Curso">
                                                <strong>{{ $experiencia->curso_codigo }}</strong> · {{ $experiencia->curso }}
                                            </td>
                                            <td data-label="Secciones">{{ $experiencia->secciones }}</td>
                                            <td data-label="Aplica IF">
                                                @if ($experiencia->aplica === true)
                                                    <span class="if-status is-success">Sí</span>
                                                @elseif ($experiencia->aplica === false)
                                                    <span class="if-status is-neutral">No</span>
                                                @else
                                                    <span class="if-status is-warning">Sin clasificar</span>
                                                @endif
                                            </td>
                                            <td data-label="Fuente">
                                                {{ $experiencia->fuente ?? 'Sin fuente registrada' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div>
                    <h3 class="if-card-title">Logro por competencia</h3>
                    @if ($logroIndicador->competencias === [])
                        <div class="if-empty" style="min-height: 160px;">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                            <h3>Sin mapeos de competencias</h3>
                            <p>El alcance seleccionado no tiene criterios mapeados a competencias.</p>
                        </div>
                    @else
                        <div class="if-table-wrap" style="margin-top: 12px;">
                            <table class="if-table">
                                <caption class="sr-only">Logro y cobertura por competencia del indicador 3.3</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Sección</th>
                                        <th scope="col">Curso</th>
                                        <th scope="col">Competencia</th>
                                        <th scope="col">Logro</th>
                                        <th scope="col">Cobertura</th>
                                        <th scope="col">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($logroIndicador->competencias as $competencia)
                                        <tr>
                                            <td data-label="Sección">{{ $competencia->seccion }}</td>
                                            <td data-label="Curso">{{ $competencia->curso }}</td>
                                            <td data-label="Competencia">
                                                <strong>{{ $competencia->codigo }}</strong> · {{ $competencia->descripcion }}
                                            </td>
                                            <td data-label="Logro">
                                                {{ $competencia->valor === null ? 'No calculable' : $competencia->valor.'%' }}
                                            </td>
                                            <td data-label="Cobertura">
                                                {{ $competencia->cobertura === null ? 'No calculable' : $competencia->cobertura.'%' }}
                                            </td>
                                            <td data-label="Estado">{{ $competencia->estado }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>

            <section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-estadisticas">
                <div class="if-panel-heading">
                    <div>
                        <h2 id="titulo-estadisticas">Estadísticas descriptivas del alcance</h2>
                        <p>
                            Estos conteos describen los datos registrados. No son los indicadores 3.1,
                            3.2 y 3.3, que se calculan por separado.
                        </p>
                    </div>
                </div>

                <div class="if-grid-wide">
                    <div class="if-card">
                        <h3 class="if-card-title">Proyectos por línea de investigación</h3>
                        <p class="if-note">
                            Un proyecto está completo cuando las tres unidades tienen evaluada su última versión.
                        </p>

                        @if ($lineas_resumen === [])
                            <div class="if-empty" style="min-height: 180px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                                <h3>Sin proyectos</h3>
                                <p>No hay proyectos registrados en el alcance seleccionado.</p>
                            </div>
                        @else
                            @php
                                $maxProyectosLinea = 1;
                                foreach ($lineas_resumen as $lineaResumen) {
                                    $maxProyectosLinea = max($maxProyectosLinea, $lineaResumen->total);
                                }
                            @endphp

                            <div class="if-chart" aria-hidden="true">
                                @foreach ($lineas_resumen as $linea)
                                    <div class="if-chart-row">
                                        <div class="if-chart-label">
                                            <span>{{ $linea->nombre }}</span>
                                            <span class="if-chart-value">{{ $linea->total }}</span>
                                        </div>
                                        <div class="if-chart-track" style="width: {{ round($linea->total / $maxProyectosLinea * 100, 1) }}%;">
                                            <span class="if-chart-fill" style="width: {{ $linea->total > 0 ? round($linea->completos / $linea->total * 100, 1) : 0 }}%;"></span>
                                        </div>
                                        <p class="if-chart-legend">
                                            Completos {{ $linea->completos }} · Pendientes {{ $linea->pendientes }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>

                            <div class="if-table-wrap" style="margin-top: 16px;">
                                <table class="if-table">
                                    <caption class="sr-only">Proyectos por línea de investigación</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Línea</th>
                                            <th scope="col">Total</th>
                                            <th scope="col">Completos</th>
                                            <th scope="col">Pendientes</th>
                                            <th scope="col">&gt; 16</th>
                                            <th scope="col">&gt; 18</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($lineas_resumen as $linea)
                                            <tr>
                                                <th scope="row" data-label="Línea">{{ $linea->nombre }}</th>
                                                <td data-label="Total">{{ $linea->total }}</td>
                                                <td data-label="Completos">{{ $linea->completos }}</td>
                                                <td data-label="Pendientes">{{ $linea->pendientes }}</td>
                                                <td data-label="Mayor a 16">{{ $linea->mayores_16 }}</td>
                                                <td data-label="Mayor a 18">{{ $linea->mayores_18 }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th scope="row">Total del alcance</th>
                                            <td>{{ $totales->proyectos }}</td>
                                            <td>{{ $totales->proyectos_completos }}</td>
                                            <td>{{ $totales->proyectos_pendientes }}</td>
                                            <td>{{ $totales->mayores_16 }}</td>
                                            <td>{{ $totales->mayores_18 }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="if-card">
                        <h3 class="if-card-title">Proyectos completos por umbral de nota</h3>
                        <p class="if-note">
                            Solo se cuentan proyectos con las tres unidades evaluadas. La base es el total de proyectos completos.
                        </p>

                        @if ($umbrales->base === 0)
                            <div class="if-empty" style="min-height: 180px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                                <h3>Sin proyectos completos</h3>
                                <p>No hay proyectos con las tres unidades evaluadas para aplicar los umbrales.</p>
                            </div>
                        @else
                            <div class="if-chart" aria-hidden="true">
                                <div class="if-chart-row">
                                    <div class="if-chart-label">
                                        <span>Nota final mayor a 16</span>
                                        <span class="if-chart-value">{{ $umbrales->mayores_16 }}</span>
                                    </div>
                                    <div class="if-chart-track">
                                        <span class="if-chart-fill" style="width: {{ round($umbrales->mayores_16 / $umbrales->base * 100, 1) }}%;"></span>
                                    </div>
                                    <p class="if-chart-legend">Base: {{ $umbrales->base }} proyectos completos</p>
                                </div>

                                <div class="if-chart-row">
                                    <div class="if-chart-label">
                                        <span>Nota final mayor a 18</span>
                                        <span class="if-chart-value">{{ $umbrales->mayores_18 }}</span>
                                    </div>
                                    <div class="if-chart-track">
                                        <span class="if-chart-fill is-secondary" style="width: {{ round($umbrales->mayores_18 / $umbrales->base * 100, 1) }}%;"></span>
                                    </div>
                                    <p class="if-chart-legend">Base: {{ $umbrales->base }} proyectos completos</p>
                                </div>
                            </div>

                            <div class="if-table-wrap" style="margin-top: 16px;">
                                <table class="if-table">
                                    <caption class="sr-only">Proyectos completos por umbral de nota</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Umbral</th>
                                            <th scope="col">Proyectos</th>
                                            <th scope="col">Base</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th scope="row" data-label="Umbral">Nota final &gt; 16</th>
                                            <td data-label="Proyectos">{{ $umbrales->mayores_16 }}</td>
                                            <td data-label="Base">{{ $umbrales->base }}</td>
                                        </tr>
                                        <tr>
                                            <th scope="row" data-label="Umbral">Nota final &gt; 18</th>
                                            <td data-label="Proyectos">{{ $umbrales->mayores_18 }}</td>
                                            <td data-label="Base">{{ $umbrales->base }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="if-note" style="margin-top: 12px;">
                                Los umbrales se superponen: un proyecto con nota mayor a 18 también aparece
                                en el conteo mayor a 16. No son intervalos excluyentes.
                            </p>
                        @endif
                    </div>

                    <div class="if-card">
                        <h3 class="if-card-title">Aprobación y trabajo destacado</h3>
                        <p class="if-note">
                            La aprobación es una decisión explícita del docente. El trabajo destacado es
                            una selección manual; no se deducen de la nota ni del estado del título.
                        </p>

                        @if ($totales->proyectos === 0)
                            <div class="if-empty" style="min-height: 180px;">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                                <h3>Sin proyectos</h3>
                                <p>No hay proyectos registrados para contar decisiones docentes.</p>
                            </div>
                        @else
                            <div class="if-chart" aria-hidden="true">
                                <div class="if-chart-row">
                                    <div class="if-chart-label">
                                        <span>Trabajos aprobados</span>
                                        <span class="if-chart-value">{{ $decisiones->aprobados }}</span>
                                    </div>
                                    <div class="if-chart-track">
                                        <span class="if-chart-fill" style="width: {{ round($decisiones->aprobados / $totales->proyectos * 100, 1) }}%;"></span>
                                    </div>
                                    <p class="if-chart-legend">Base: {{ $totales->proyectos }} proyectos</p>
                                </div>

                                <div class="if-chart-row">
                                    <div class="if-chart-label">
                                        <span>Trabajos destacados vigentes</span>
                                        <span class="if-chart-value">{{ $decisiones->destacados }}</span>
                                    </div>
                                    <div class="if-chart-track">
                                        <span class="if-chart-fill is-secondary" style="width: {{ $totales->secciones > 0 ? round($decisiones->destacados / $totales->secciones * 100, 1) : 0 }}%;"></span>
                                    </div>
                                    <p class="if-chart-legend">Base: {{ $totales->secciones }} secciones</p>
                                </div>
                            </div>

                            <div class="if-table-wrap" style="margin-top: 16px;">
                                <table class="if-table">
                                    <caption class="sr-only">Decisiones docentes del alcance</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Indicador</th>
                                            <th scope="col">Conteo</th>
                                            <th scope="col">Universo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th scope="row" data-label="Indicador">Trabajos aprobados</th>
                                            <td data-label="Conteo">{{ $decisiones->aprobados }}</td>
                                            <td data-label="Universo">{{ $totales->proyectos }} proyectos</td>
                                        </tr>
                                        <tr>
                                            <th scope="row" data-label="Indicador">Trabajos destacados vigentes</th>
                                            <td data-label="Conteo">{{ $decisiones->destacados }}</td>
                                            <td data-label="Universo">{{ $totales->secciones }} secciones</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h3 class="if-card-title">Encuesta de las secciones incluidas</h3>
                    <p class="if-note">
                        Escala {{ $encuesta->escala_min }}–{{ $encuesta->escala_max }}. Los promedios y
                        distribuciones son estadística descriptiva; no definen satisfacción estudiantil
                        ni un indicador oficial.
                    </p>
                    @if (($filtros['linea'] ?? null) !== null)
                        <p class="if-note">
                            La encuesta es por sección y no se puede desagregar por línea de investigación.
                            Se muestran las secciones que tienen al menos un proyecto de la línea filtrada.
                        </p>
                    @endif

                    <dl class="if-inline-stats" style="margin-top: 16px;" aria-label="Resumen de la encuesta">
                        <div>
                            <dt>Secciones con encuesta</dt>
                            <dd>{{ $encuesta->secciones_con_encuesta }}</dd>
                        </div>
                        <div>
                            <dt>Respuestas</dt>
                            <dd>{{ $encuesta->respuestas }}</dd>
                        </div>
                        <div>
                            <dt>Participantes activos</dt>
                            <dd>{{ $encuesta->roster_activo }}</dd>
                        </div>
                        <div>
                            <dt>Participación</dt>
                            <dd>{{ $encuesta->participacion_porcentaje === null ? 'No calculable' : $encuesta->participacion_porcentaje.'%' }}</dd>
                        </div>
                    </dl>

                    <p class="if-note" style="margin-top: 10px;">
                        La participación usa respuestas / participantes activos actuales de las secciones
                        incluidas. Puede superar el 100% después de retiros. Sin participantes activos,
                        la tasa no se calcula.
                    </p>

                    @if ($encuesta->preguntas === [])
                        <div class="if-empty" style="min-height: 180px;">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                            <h3>Sin preguntas de encuesta</h3>
                            <p>No hay preguntas de encuesta registradas en las secciones incluidas.</p>
                        </div>
                    @else
                        <div class="if-table-wrap" style="margin-top: 16px;">
                            <table class="if-table">
                                <caption class="sr-only">Distribución de respuestas por pregunta, escala 1 a 5</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Pregunta</th>
                                        <th scope="col">Respuestas</th>
                                        <th scope="col">Promedio</th>
                                        @foreach (range($encuesta->escala_min, $encuesta->escala_max) as $valor)
                                            <th scope="col">Valor {{ $valor }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($encuesta->preguntas as $pregunta)
                                        <tr>
                                            <th scope="row" data-label="Pregunta">{{ $pregunta->texto }}</th>
                                            <td data-label="Respuestas">{{ $pregunta->respuestas }}</td>
                                            <td data-label="Promedio">{{ $pregunta->valor_promedio ?? 'Sin respuestas' }}</td>
                                            @foreach ($pregunta->distribucion as $valor)
                                                <td data-label="Valor {{ $valor->valor }}">{{ $valor->conteo }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="if-chart" style="margin-top: 18px;" aria-hidden="true">
                            @foreach ($encuesta->preguntas as $pregunta)
                                <div class="if-question-chart">
                                    <p class="if-chart-title">{{ $pregunta->texto }}</p>
                                    @if ($pregunta->respuestas === 0)
                                        <p class="if-note">Sin respuestas para esta pregunta.</p>
                                    @else
                                        @foreach ($pregunta->distribucion as $valor)
                                            <div class="if-chart-row">
                                                <div class="if-chart-label">
                                                    <span>Valor {{ $valor->valor }}</span>
                                                    <span class="if-chart-value">{{ $valor->conteo }}</span>
                                                </div>
                                                <div class="if-chart-track">
                                                    <span class="if-chart-fill" style="width: {{ round($valor->conteo / $pregunta->respuestas * 100, 1) }}%;"></span>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <div class="if-section-heading">
                <h2 class="if-section-title">Secciones de su alcance</h2>
                <a class="if-button" href="{{ route('investigacion-formativa.director.cursos.index') }}">
                    Ver cursos y habilitar secciones
                </a>
            </div>

            @if ($secciones === [])
                <div class="if-panel if-empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <h2>Sin secciones</h2>
                    <p>No hay secciones en el alcance seleccionado.</p>
                </div>
            @else
                <div class="if-table-wrap">
                    <table class="if-table">
                        <caption class="sr-only">Secciones del alcance seleccionado</caption>
                        <thead>
                            <tr>
                                <th scope="col">Sección</th>
                                <th scope="col">Curso</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Docente</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Proyectos</th>
                                <th scope="col">Respuestas</th>
                                <th scope="col">Decisiones</th>
                                <th scope="col" class="if-table-actions"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($secciones as $seccion)
                                <tr>
                                    <td data-label="Sección"><strong>{{ $seccion->seccion }} · {{ $seccion->escuela }}</strong></td>
                                    <td data-label="Curso">{{ $seccion->curso }}</td>
                                    <td data-label="Periodo">{{ $seccion->periodo }}</td>
                                    <td data-label="Docente">{{ $seccion->docente ?? 'Sin docente asignado' }}</td>
                                    <td data-label="Estado">
                                        <span class="if-status {{ $seccion->habilitada ? 'is-success' : 'is-neutral' }}">
                                            {{ $seccion->habilitada ? 'Habilitada' : 'Deshabilitada' }}
                                        </span>
                                    </td>
                                    <td data-label="Proyectos">
                                        {{ $seccion->grupos }} en alcance · {{ $seccion->grupos_con_nota }} completos
                                    </td>
                                    <td data-label="Respuestas">
                                        {{ $seccion->respuestas_count }}
                                        @if ($seccion->roster_activo > 0)
                                            · {{ $seccion->participacion_porcentaje }}% de {{ $seccion->roster_activo }} activos
                                        @endif
                                    </td>
                                    <td data-label="Decisiones">
                                        {{ $seccion->aprobados }} aprobados · {{ $seccion->destacados }} destacados
                                    </td>
                                    <td data-label="Acciones" class="if-table-actions">
                                        <a
                                            class="if-link"
                                            href="{{ route('investigacion-formativa.director.resultados', array_filter(['seccion' => $seccion->id_seccion, 'escuela' => $filtros['escuela'], 'periodo' => $filtros['periodo'], 'curso' => $filtros['curso'], 'linea' => $filtros['linea']])) }}"
                                        >
                                            Ver resultados
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
@endsection
