@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Estudiante · Mis cursos')
@section('page-label', 'Mis cursos')

@section('content')

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Mis cursos</h1>
                <p class="if-lead">
                    Secciones donde figuras en el padrón de participación. Forma tu grupo cuando la
                    sección esté habilitada y configurada.
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

        @if ($secciones === [])
            <div class="if-panel if-empty" style="margin-top: 22px;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Sin cursos</h2>
                <p>No tienes secciones activas con participación registrada.</p>
            </div>
        @else
            <div class="if-table-wrap" style="margin-top: 22px;">
                <table class="if-table">
                    <caption class="sr-only">Secciones donde figuras en el padrón de participación</caption>
                    <thead>
                        <tr>
                            <th scope="col">Periodo</th>
                            <th scope="col">Curso</th>
                            <th scope="col">Sección</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Grupo</th>
                            <th scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td data-label="Periodo">{{ $seccion->periodo }}</td>
                                <td data-label="Curso">
                                    {{ $seccion->curso_codigo }} · {{ $seccion->curso }}
                                </td>
                                <td data-label="Sección">{{ $seccion->seccion }}</td>
                                <td data-label="Estado">
                                    @if (! $seccion->habilitada)
                                        <span class="if-status is-warning">Pendiente de habilitación</span>
                                    @elseif (! $seccion->configurada)
                                        <span class="if-status is-warning">Configuración pendiente</span>
                                        <p class="if-note" style="margin-top: 6px;">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @else
                                        <span class="if-status is-success">Habilitada</span>
                                    @endif
                                </td>
                                <td data-label="Grupo">
                                    {{ $seccion->id_grupo === null ? 'Sin grupo' : 'Grupo formado' }}
                                </td>
                                <td data-label="Acción" class="if-stack" style="--if-stack-gap: 8px;">
                                    @if ($seccion->id_grupo !== null)
                                        <a
                                            class="if-button-secondary if-button-sm"
                                            href="{{ route('investigacion-formativa.alumno.grupos.show', $seccion->id_seccion) }}"
                                        >
                                            Ver mi grupo
                                        </a>
                                    @elseif ($seccion->habilitada && $seccion->configurada)
                                        <a
                                            class="if-button if-button-sm"
                                            href="{{ route('investigacion-formativa.alumno.grupos.create', $seccion->id_seccion) }}"
                                        >
                                            Formar grupo
                                        </a>
                                    @else
                                        <span class="if-note">Disponible al completar la configuración</span>
                                    @endif

                                    <a
                                        class="if-button-secondary if-button-sm"
                                        href="{{ route('investigacion-formativa.alumno.encuestas.show', $seccion->id_seccion) }}"
                                    >
                                        Ver encuesta
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($retiradas !== [])
            <section style="margin-top: 28px;" aria-labelledby="titulo-grupos-anteriores">
                <h2 id="titulo-grupos-anteriores" class="if-section-title">
                    Grupos anteriores (solo lectura)
                </h2>
                <p class="if-lead" style="margin-top: 6px;">
                    Conservas acceso de lectura a los grupos de tu participación retirada.
                </p>

                <ul class="if-grid" style="margin-top: 14px;" role="list">
                    @foreach ($retiradas as $retirada)
                        <li class="if-card if-stack">
                            <p class="if-card-title">{{ $retirada->grupo }}</p>
                            <p class="if-card-text">
                                {{ $retirada->curso_codigo }} · {{ $retirada->curso }} · Sección {{ $retirada->seccion }}
                            </p>
                            <p class="if-note">Periodo {{ $retirada->periodo }}</p>
                            <a
                                class="if-button-secondary if-button-sm"
                                style="align-self: flex-start;"
                                href="{{ route('investigacion-formativa.alumno.grupos.show', $retirada->id_seccion) }}"
                            >
                                Ver grupo
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
@endsection
