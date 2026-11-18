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
                    <a class="if-link" href="{{ route('investigacion-formativa.director.index', request()->only(['escuela', 'periodo'])) }}">
                        Volver al resumen e historial por periodo
                    </a>
                </p>
            </div>
        </div>

        <nav class="if-actions" aria-label="Secciones de resultados">
            <a class="if-link" href="#encuesta">Encuesta</a>
            <a class="if-link" href="#calificaciones">Calificaciones</a>
            <a class="if-link" href="#decisiones">Decisiones docentes</a>
            <a class="if-link" href="{{ route('investigacion-formativa.director.index', ['periodo' => $seccion->periodo->codigo]) }}">Historial del periodo</a>
        </nav>

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
                @foreach (request()->only(['escuela', 'periodo']) as $nombre => $valor)
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
                <p>La sección no tiene un trabajo destacado vigente.</p>
            @endif
        </section>
@endsection
