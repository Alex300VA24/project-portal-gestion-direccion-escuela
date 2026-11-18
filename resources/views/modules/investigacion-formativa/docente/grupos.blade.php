@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Grupos de investigación')
@section('page-label', 'Grupos')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Grupos de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Grupos de investigación</h1>
                <p class="if-lead">
                    Revisa el título propuesto por cada grupo de la sección. El estado del título solo
                    cambia por una revisión registrada.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="if-flash-success" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <span>{{ session('status') }}</span>
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

        <form class="if-form-grid" method="GET" action="{{ route('investigacion-formativa.docente.grupos.index', $seccion) }}">
            <div class="if-field">
                <label for="buscar-grupo">Buscar grupo o título</label>
                <input
                    id="buscar-grupo"
                    name="buscar"
                    type="search"
                    value="{{ $busqueda }}"
                >
            </div>
            <div>
                <button class="if-button" type="submit">
                    Buscar
                </button>
            </div>
        </form>

        @if ($grupos->isEmpty())
            <div class="if-panel if-empty" style="margin-top: 22px;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Sin resultados</h2>
                <p>No hay grupos que coincidan con la búsqueda.</p>
            </div>
        @else
            <div class="if-table-wrap" style="margin-top: 22px;">
                <table class="if-table">
                    <caption class="sr-only">Grupos de investigación de la sección</caption>
                    <thead>
                        <tr>
                            <th scope="col">Grupo</th>
                            <th scope="col">Coordinador</th>
                            <th scope="col">Título propuesto</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Avances</th>
                            <th scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grupos as $grupo)
                            <tr>
                                <td data-label="Grupo"><strong>{{ $grupo->nombre }}</strong></td>
                                <td data-label="Coordinador">{{ $grupo->coordinador ?? 'Sin coordinador' }}</td>
                                <td data-label="Título propuesto">{{ $grupo->titulo ?? 'Sin título' }}</td>
                                <td data-label="Estado">{{ $grupo->estado ?? '—' }}</td>
                                <td data-label="Avances">{{ $grupo->entregados }} de 3</td>
                                <td data-label="Acción">
                                    <a
                                        class="if-button-secondary"
                                        style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                        href="{{ route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo->id_grupo]) }}"
                                    >
                                        Ver grupo
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="if-pagination">
                {{ $grupos->links('modules.investigacion-formativa.partials.paginacion') }}
            </div>
        @endif
@endsection
