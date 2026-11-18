@extends('layouts.app')
@section('title', 'Dirección de escuela')
@section('page-label', 'Dirección')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Módulo</span>
            <h1>Dirección de escuela</h1>
            <p>
                Resumen de las secciones de sus escuelas asignadas. Incluye secciones cerradas y
                deshabilitadas de los periodos seleccionados.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="if-flash-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($escuelas === [])
        <div class="if-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 17h.01"/></svg>
            <h3>No tiene escuelas activas asignadas</h3>
            <p>Solicite la importación de su alcance de dirección.</p>
        </div>
    @else
        <form
            class="if-panel if-inline-stats"
            method="GET"
            action="{{ route('investigacion-formativa.director.index') }}"
            aria-label="Filtros del resumen"
            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding:16px 18px;margin:0 0 16px;border:1px solid var(--portal-border);border-radius:12px;background:var(--portal-surface)"
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

            <div style="display:flex;align-items:flex-end;gap:8px">
                <button class="if-button" type="submit">Filtrar</button>
                <a class="if-button-secondary" href="{{ route('investigacion-formativa.director.index') }}">Limpiar</a>
            </div>
        </form>

        <dl class="if-inline-stats" aria-label="Totales de las secciones filtradas" style="margin-bottom:16px">
            <div><dt>Secciones</dt><dd>{{ $totales->secciones }}</dd></div>
            <div><dt>Habilitadas</dt><dd>{{ $totales->habilitadas }}</dd></div>
            <div><dt>Grupos</dt><dd>{{ $totales->grupos }}</dd></div>
            <div><dt>Avances evaluados</dt><dd>{{ $totales->evaluaciones }}</dd></div>
            <div><dt>Respuestas</dt><dd>{{ $totales->respuestas }}</dd></div>
        </dl>

        <p style="font-size:13px;color:var(--portal-muted);margin:0 0 12px">
            La participación usa respuestas / participantes activos actuales. Puede superar el 100%
            después de retiros. Sin participantes activos, la tasa no se calcula.
        </p>

        <div class="if-panel">
            <header class="if-panel-heading">
                <div>
                    <h2>Secciones de su alcance</h2>
                    <p>Se incluyen secciones cerradas y deshabilitadas de los periodos seleccionados.</p>
                </div>
                <a href="{{ route('investigacion-formativa.director.cursos.index') }}">Ver cursos y habilitar secciones</a>
            </header>

            @if ($secciones === [])
                <div class="if-empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <h3>No hay secciones en el alcance seleccionado</h3>
                    <p>Ajuste los filtros de escuela o periodo para ver otras secciones.</p>
                </div>
            @else
                <div style="overflow-x:auto">
                    <table class="if-table">
                        <thead>
                            <tr>
                                <th scope="col">Sección</th>
                                <th scope="col">Curso</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Docente</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Respuestas</th>
                                <th scope="col"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($secciones as $seccion)
                                <tr>
                                    <td data-label="Sección" style="font-weight:600">{{ $seccion->seccion }} · {{ $seccion->escuela }}</td>
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
                                    <td data-label="Acciones" style="text-align:right">
                                        <a class="if-link" href="{{ route('investigacion-formativa.director.resultados', array_filter(['seccion' => $seccion->id_seccion, 'escuela' => $filtros['escuela'], 'periodo' => $filtros['periodo']])) }}">
                                            Ver resultados
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection