@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Resultados de Investigación Formativa')
@section('page-label', 'Resultados')

@section('content')
        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">{{ $seccion->cursoPlan->curso->nombre }} · {{ $seccion->codigo }}</h1>
                <p class="if-lead">
                    Periodo {{ $seccion->periodo->codigo }} · Estado académico: {{ $seccion->estado }}
                </p>
                <p class="if-actions" style="margin-top: 14px;">
                    <a class="if-link" href="{{ route('investigacion-formativa.director.index', request()->only(['escuela', 'periodo', 'curso', 'linea'])) }}">
                        Volver al resumen e historial por periodo
                    </a>
                    <a
                        class="if-button"
                        href="{{ route('investigacion-formativa.director.resultados.pdf', $seccion) }}@if (request()->filled('linea'))?linea={{ (int) request('linea') }}@endif"
                        download
                    >
                        Descargar PDF
                    </a>
                </p>
                <p class="if-note" style="margin-top: 8px;">
                    El PDF incluye los resultados visibles con los filtros activos. No incluye los comentarios confidenciales de la encuesta.
                </p>
            </div>
        </div>

        <nav class="if-actions" aria-label="Secciones de resultados">
            <a class="if-link" href="#encuesta">Encuesta</a>
            <a class="if-link" href="#indicadores">Indicadores</a>
            <a class="if-link" href="#calificaciones">Calificaciones</a>
            <a class="if-link" href="#decisiones">Decisiones docentes</a>
            <a class="if-link" href="{{ route('investigacion-formativa.director.index', array_filter(['periodo' => $seccion->periodo->codigo, 'escuela' => request('escuela'), 'curso' => request('curso'), 'linea' => request('linea')])) }}">Historial del periodo</a>
        </nav>

        @if (request()->filled('linea'))
            <p class="if-note">
                Las calificaciones y las decisiones están filtradas por línea de investigación.
                La encuesta corresponde a la sección completa y no se desagrega por línea.
            </p>
        @endif

        <section id="encuesta" class="if-panel if-stack" style="margin-top: 20px; padding: 22px;" aria-labelledby="titulo-encuesta">
            <h2 id="titulo-encuesta" class="if-section-title">Resultados de la encuesta</h2>
            <p>Estado: {{ $reporte->estado ?? 'Sin encuesta' }}. Respuestas: {{ $reporte->respuestas_count }}.</p>
            <p>
                Participantes activos actuales: {{ $reporte->roster_activo }}.
                Participación: {{ $reporte->participacion_porcentaje === null ? 'No calculable sin participantes activos' : $reporte->participacion_porcentaje.'%' }}.
            </p>
            <p class="if-note">
                La tasa usa el padrón activo actual. Puede superar el 100% después de retiros; las respuestas anteriores se conservan.
            </p>

            @if ($reporte->respuestas_count === 0)
                <div class="if-card">
                    <p>Todavía no hay respuestas registradas.</p>
                </div>
            @else
                <div class="if-table-wrap">
                    <table class="if-table">
                        <caption class="sr-only">Promedios y distribución de las respuestas (escala 1–5)</caption>
                        <thead>
                            <tr>
                                <th scope="col">Pregunta</th>
                                <th scope="col">Promedio</th>
                                @foreach ([1, 2, 3, 4, 5] as $valor)
                                    <th scope="col">Valor {{ $valor }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reporte->preguntas as $pregunta)
                                <tr>
                                    <th scope="row" data-label="Pregunta">{{ $pregunta->texto }}</th>
                                    <td data-label="Promedio">{{ $pregunta->valor_promedio ?? 'Sin respuestas' }}</td>
                                    @foreach ($pregunta->distribucion as $valor)
                                        <td data-label="Valor {{ $valor->valor }}">{{ $valor->conteo }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h3 class="if-section-title">Comentarios confidenciales</h3>
            <p class="if-note">
                Las respuestas se almacenan con una identidad. Esta vista omite la identidad de quien responde; no son respuestas anónimas.
            </p>

            <form class="if-form-grid" method="GET" action="{{ route('investigacion-formativa.director.resultados', $seccion) }}">
                @foreach (request()->only(['escuela', 'periodo', 'curso', 'linea']) as $nombre => $valor)
                    <input type="hidden" name="{{ $nombre }}" value="{{ $valor }}">
                @endforeach
                <div class="if-field">
                    <label for="por-pagina">Comentarios por página</label>
                    <select id="por-pagina" name="por_pagina">
                        @foreach (array_unique([10, 25, 50, 100, $porPagina]) as $cantidad)
                            <option value="{{ $cantidad }}" @selected($porPagina === $cantidad)>{{ $cantidad }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button class="if-button" type="submit">Aplicar</button>
                </div>
            </form>

            @forelse ($reporte->comentarios as $comentario)
                <blockquote class="if-quote">{{ $comentario->comentario }}</blockquote>
            @empty
                <p>No hay comentarios registrados.</p>
            @endforelse

            <div class="if-pagination">
                {{ $reporte->comentarios->fragment('encuesta')->links('modules.investigacion-formativa.partials.paginacion') }}
            </div>
        </section>

        @php
            $satisfaccionIndicador = $reporte->indicador_satisfaccion;
            $logroIndicador = $reporte->indicador_logro_competencias;
        @endphp

        <section
            id="indicadores"
            class="if-panel if-stack"
            style="margin-top: 22px; padding: 22px;"
            aria-labelledby="titulo-indicadores"
        >
            <h2 id="titulo-indicadores" class="if-section-title">Indicadores de la sección</h2>
            <h3 class="if-card-title">Indicador 3.2 · Satisfacción</h3>
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

            <p class="if-note" style="margin-top: 12px;">
                El indicador agregado 3.1 se calcula en el resumen de dirección con el alcance de
                escuela, periodo y curso. Esta vista de sección no lo duplica.
            </p>

            <div style="margin-top: 26px;">
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

                @if ($logroIndicador->competencias !== [])
                    <div class="if-table-wrap" style="margin-top: 16px;">
                        <table class="if-table">
                            <caption class="sr-only">Logro y cobertura por competencia</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Competencia</th>
                                    <th scope="col">Logro</th>
                                    <th scope="col">Cobertura</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logroIndicador->competencias as $competencia)
                                    <tr>
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

        <section id="calificaciones" class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-calificaciones">
            <h2 id="titulo-calificaciones" class="if-section-title">Calificaciones de los proyectos</h2>
            <p class="if-note">
                Escala de 0 a 20. Se usa la última versión de cada unidad. Una nueva versión sin evaluar queda pendiente. La nota final requiere las tres unidades evaluadas.
            </p>
            <p>Avances actuales evaluados: {{ $reporte->entregas_evaluadas }}.</p>

            @if ($reporte->grupos === [])
                <div class="if-card">
                    <p>No hay grupos registrados.</p>
                </div>
            @else
                <div class="if-table-wrap">
                    <table class="if-table">
                        <caption class="sr-only">Calificaciones de los proyectos por unidad</caption>
                        <thead>
                            <tr>
                                <th scope="col">Grupo</th>
                                @foreach ([1, 2, 3] as $unidad)
                                    <th scope="col">Unidad {{ $unidad }}</th>
                                @endforeach
                                <th scope="col">Evaluadas</th>
                                <th scope="col">Nota final</th>
                                <th scope="col">Decisión docente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reporte->grupos as $grupo)
                                <tr>
                                    <th scope="row" data-label="Grupo">{{ $grupo->nombre }}</th>
                                    @foreach ($grupo->notas_unidades as $nota)
                                        <td data-label="Unidad">{{ $nota ?? 'Pendiente' }}</td>
                                    @endforeach
                                    <td data-label="Evaluadas">{{ $grupo->entregas_evaluadas }}/3</td>
                                    <td data-label="Nota final">{{ $grupo->nota_final ?? 'Pendiente' }}</td>
                                    <td data-label="Decisión docente">
                                        @if ($grupo->aprobado)
                                            <span class="if-status is-success">Aprobado</span>
                                        @else
                                            <span class="if-status is-neutral">Sin aprobación</span>
                                        @endif
                                        @if ($grupo->destacado)
                                            <span class="if-status is-success">Destacado</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($reporte->promedio_denominador === 0)
                <div class="if-card">
                    <p>No hay proyectos con las tres unidades evaluadas para calcular el promedio o la clasificación.</p>
                </div>
            @else
                <p>Promedio del curso: {{ $reporte->promedio_curso }}. Proyectos incluidos: {{ $reporte->promedio_denominador }}.</p>
                @if ($reporte->promedio_denominador === 1)
                    <p>Solo hay un proyecto completo; ocupa tanto la mejor como la peor posición.</p>
                @endif
                <h3 class="if-section-title">Mejor nota: {{ $reporte->mejor_nota }}</h3>
                <ul class="if-list">
                    @foreach ($reporte->grupos_mejor as $grupo)
                        <li>{{ $grupo->nombre }}</li>
                    @endforeach
                </ul>
                <h3 class="if-section-title">Peor nota: {{ $reporte->peor_nota }}</h3>
                <ul class="if-list">
                    @foreach ($reporte->grupos_peor as $grupo)
                        <li>{{ $grupo->nombre }}</li>
                    @endforeach
                </ul>
                <p class="if-note">
                    Se incluyen todos los empates. Los proyectos incompletos quedan fuera del promedio y de la clasificación.
                </p>
            @endif
        </section>

        <section id="decisiones" class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-decisiones">
            <h2 id="titulo-decisiones" class="if-section-title">Aprobación final y trabajo destacado</h2>
            <p class="if-note">
                La aprobación final es una decisión explícita del docente responsable. El trabajo destacado se
                selecciona de forma manual; no es la nota más alta ni se deduce de las calificaciones.
            </p>

            <dl class="if-inline-stats" aria-label="Decisiones registradas en la sección">
                <div>
                    <dt>Trabajos aprobados</dt>
                    <dd>{{ $reporte->aprobados_count }}</dd>
                </div>
                <div>
                    <dt>Trabajos destacados</dt>
                    <dd>{{ $reporte->destacados_count }}</dd>
                </div>
            </dl>

            @if ($reporte->seleccion_destacada !== null)
                <p>
                    Trabajo destacado de la sección:
                    <strong>{{ $reporte->seleccion_destacada->nombre }}</strong> ·
                    {{ $reporte->seleccion_destacada->titulo }}
                    @if ($reporte->seleccion_destacada->desactualizada)
                        <span class="if-status is-warning">Selección desactualizada</span>
                    @endif
                </p>
            @else
                <p>
                    {{ request()->filled('linea')
                        ? 'La sección no tiene un trabajo destacado vigente para la línea filtrada.'
                        : 'La sección no tiene un trabajo destacado vigente.' }}
                </p>
            @endif
        </section>
@endsection
