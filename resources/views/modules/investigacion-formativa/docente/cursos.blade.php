@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Mis cursos')
@section('page-label', 'Mis cursos')

@section('content')

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Mis cursos asignados</h1>
                <p class="if-lead">
                    Secciones activas donde figuras como docente responsable. Configura competencias,
                    rúbrica y cronograma antes de habilitar el trabajo con estudiantes.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="if-flash-success" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($secciones === [])
            <div class="if-panel if-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Sin cursos asignados</h2>
                <p>No tienes secciones activas asignadas en este momento.</p>
            </div>
        @else
            <div class="if-table-wrap" style="margin-top: 22px;">
                <table class="if-table">
                    <caption class="sr-only">Secciones donde figuras como docente responsable</caption>
                    <thead>
                        <tr>
                            <th scope="col">Periodo</th>
                            <th scope="col">Curso</th>
                            <th scope="col">Sección</th>
                            <th scope="col">Configuración</th>
                            <th scope="col">Pasos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td data-label="Periodo">{{ $seccion->periodo }}</td>
                                <td data-label="Curso">
                                    <strong>{{ $seccion->curso_codigo }} · {{ $seccion->curso }}</strong>
                                </td>
                                <td data-label="Sección">{{ $seccion->seccion }}</td>
                                <td data-label="Configuración">
                                    @if ($seccion->habilitada)
                                        <span class="if-status is-success">Habilitada</span>
                                    @else
                                        <span class="if-status is-warning">Pendiente de habilitación</span>
                                    @endif

                                    @if ($seccion->configurada)
                                        <p class="if-note" style="margin-top: 6px;">Configuración completa</p>
                                    @else
                                        <p class="if-note" style="margin-top: 6px;">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @endif
                                </td>
                                <td data-label="Pasos">
                                    <ul class="if-actions" role="list">
                                        <li>
                                            <a
                                                class="if-button-secondary"
                                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                                href="{{ route('investigacion-formativa.docente.competencias.edit', $seccion->id_seccion) }}"
                                            >
                                                Competencias
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="if-button-secondary"
                                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                                href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}"
                                            >
                                                Rúbrica
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="if-button-secondary"
                                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                                href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}"
                                            >
                                                Cronograma
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="if-button-secondary"
                                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                                href="{{ route('investigacion-formativa.docente.grupos.index', $seccion->id_seccion) }}"
                                            >
                                                Grupos
                                            </a>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
@endsection
