@extends('layouts.app')
@section('title', 'Mis cursos · Investigación Formativa')
@section('page-label', 'Mis cursos')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Docencia</span>
            <h1>Mis cursos asignados</h1>
            <p>
                Secciones activas donde figuras como docente responsable. Configura competencias,
                rúbrica y cronograma antes de habilitar el trabajo con estudiantes.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="if-flash-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="if-flash-danger" role="alert">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 17h.01"/></svg>
            <span>
                <strong>Revisa los siguientes errores</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    @if ($secciones === [])
        <div class="if-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3>No tienes secciones activas asignadas</h3>
            <p>Cuando el director asigne secciones a tu cuenta, aparecerán en este listado.</p>
        </div>
    @else
        <div class="if-panel" style="overflow-x:auto">
            <table class="if-table">
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
                            <td data-label="Periodo" style="white-space:nowrap">{{ $seccion->periodo }}</td>
                            <td data-label="Curso" style="font-weight:600">{{ $seccion->curso_codigo }} · {{ $seccion->curso }}</td>
                            <td data-label="Sección">{{ $seccion->seccion }}</td>
                            <td data-label="Configuración">
                                @if ($seccion->habilitada)
                                    <span class="if-status is-success">Habilitada</span>
                                @else
                                    <span class="if-status is-warning">Pendiente de habilitación</span>
                                @endif

                                @if ($seccion->configurada)
                                    <p class="mt-1 text-xs font-semibold" style="color:var(--portal-success)">Configuración completa</p>
                                @else
                                    <p class="mt-1 text-xs" style="color:var(--portal-warning)">{{ implode(', ', $seccion->faltantes) }}</p>
                                @endif
                            </td>
                            <td data-label="Pasos">
                                <ul class="flex flex-wrap gap-2 text-xs font-semibold" role="list">
                                    <li>
                                        <a class="if-button-secondary" style="padding:0 10px;min-height:32px;font-size:11px" href="{{ route('investigacion-formativa.docente.competencias.edit', $seccion->id_seccion) }}">
                                            Competencias
                                        </a>
                                    </li>
                                    <li>
                                        <a class="if-button-secondary" style="padding:0 10px;min-height:32px;font-size:11px" href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}">
                                            Rúbrica
                                        </a>
                                    </li>
                                    <li>
                                        <a class="if-button-secondary" style="padding:0 10px;min-height:32px;font-size:11px" href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}">
                                            Cronograma
                                        </a>
                                    </li>
                                    <li>
                                        <a class="if-button-secondary" style="padding:0 10px;min-height:32px;font-size:11px" href="{{ route('investigacion-formativa.docente.grupos.index', $seccion->id_seccion) }}">
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