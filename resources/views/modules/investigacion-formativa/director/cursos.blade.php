@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Dirección · Cursos y habilitación')
@section('page-label', 'Cursos y habilitación')

@section('content')

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Cursos de mis escuelas</h1>
                <p class="if-lead">
                    Habilita las secciones que ya tienen la configuración completa. El alcance
                    se limita a las escuelas asignadas a tu cuenta.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="if-flash-success" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="if-alert" role="alert" data-conflicto>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                <span>{{ $conflicto }} Recarga la página antes de volver a intentar.</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="if-flash-danger" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                <ul class="if-list" role="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            class="if-panel if-form-grid"
            method="GET"
            action="{{ route('investigacion-formativa.director.cursos.index') }}"
        >
            <div class="if-field">
                <label for="filtro-escuela">Escuela</label>
                <select id="filtro-escuela" name="escuela">
                    <option value="">Todas mis escuelas</option>
                    @foreach ($escuelas as $escuela)
                        <option value="{{ $escuela->id_escuela }}" @selected($filtros['escuela'] === (int) $escuela->id_escuela)>
                            {{ $escuela->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="if-field">
                <label for="filtro-periodo">Periodo</label>
                <select id="filtro-periodo" name="periodo">
                    <option value="">Todos</option>
                    @foreach ($periodos as $periodo)
                        <option value="{{ $periodo }}" @selected($filtros['periodo'] === $periodo)>
                            {{ $periodo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="if-field">
                <label for="filtro-buscar">Buscar curso o sección</label>
                <input
                    id="filtro-buscar"
                    maxlength="100"
                    name="buscar"
                    type="search"
                    value="{{ $filtros['buscar'] }}"
                >
            </div>

            <div class="if-actions">
                <button class="if-button" type="submit">Filtrar</button>
                <a class="if-button-secondary" href="{{ route('investigacion-formativa.director.cursos.index') }}">
                    Limpiar
                </a>
            </div>
        </form>

        @if ($secciones->isEmpty())
            <div class="if-panel if-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Sin resultados</h2>
                <p>No hay secciones que coincidan con los filtros en tus escuelas asignadas.</p>
            </div>
        @else
            <div class="if-table-wrap" style="margin-top: 22px;">
                <table class="if-table">
                    <caption class="sr-only">Secciones de las escuelas asignadas</caption>
                    <thead>
                        <tr>
                            <th scope="col">Periodo</th>
                            <th scope="col">Escuela</th>
                            <th scope="col">Curso</th>
                            <th scope="col">Sección</th>
                            <th scope="col">Docente</th>
                            <th scope="col">Configuración</th>
                            <th scope="col">Encuesta</th>
                            <th scope="col">Habilitación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td data-label="Periodo">{{ $seccion->periodo }}</td>
                                <td data-label="Escuela">{{ $seccion->escuela }}</td>
                                <td data-label="Curso">
                                    <strong>{{ $seccion->curso_codigo }} · {{ $seccion->curso }}</strong>
                                </td>
                                <td data-label="Sección">{{ $seccion->seccion }}</td>
                                <td data-label="Docente">{{ $seccion->docente ?? 'Sin docente asignado' }}</td>
                                <td data-label="Configuración">
                                    @if ($seccion->configurada)
                                        <span class="if-status is-success">Completa</span>
                                    @else
                                        <span class="if-status is-warning">Incompleta</span>
                                        <p class="if-note" style="margin-top: 6px;">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @endif
                                </td>
                                <td data-label="Encuesta">
                                    <a
                                        class="if-button-secondary"
                                        style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                        href="{{ route('investigacion-formativa.director.encuestas.edit', $seccion->id_seccion) }}"
                                    >
                                        Gestionar encuesta
                                    </a>
                                </td>
                                <td data-label="Habilitación">
                                    <div class="if-stack" style="--if-stack-gap: 10px;">
                                        @if ($seccion->habilitada)
                                            <span class="if-status is-success">Habilitada</span>
                                        @else
                                            <span class="if-status is-neutral">Deshabilitada</span>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('investigacion-formativa.director.secciones.habilitacion', $seccion->id_seccion) }}"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="habilitada" value="{{ $seccion->habilitada ? 0 : 1 }}">
                                            <input type="hidden" name="version" value="{{ $seccion->version_configuracion }}">
                                            <input type="hidden" name="escuela" value="{{ $filtros['escuela'] }}">
                                            <input type="hidden" name="buscar" value="{{ $filtros['buscar'] }}">
                                            <input type="hidden" name="periodo" value="{{ $filtros['periodo'] }}">
                                            <button
                                                class="if-button-secondary"
                                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                                type="submit"
                                            >
                                                {{ $seccion->habilitada ? 'Deshabilitar' : 'Habilitar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="if-pagination">
                {{ $secciones->links('modules.investigacion-formativa.partials.paginacion') }}
            </div>
        @endif
@endsection
