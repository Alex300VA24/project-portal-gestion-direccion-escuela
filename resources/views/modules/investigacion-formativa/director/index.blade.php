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
                    <dt>Avances actuales evaluados</dt>
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
                                            href="{{ route('investigacion-formativa.director.resultados', array_filter(['seccion' => $seccion->id_seccion, 'escuela' => $filtros['escuela'], 'periodo' => $filtros['periodo']])) }}"
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
