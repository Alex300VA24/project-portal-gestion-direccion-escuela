@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Estudiante · Mi grupo')
@section('page-label', 'Mi grupo')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>{{ $detalle?->grupo->nombre ?? 'Mi grupo' }}</span>
        </nav>

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

        @if ($detalle === null)
            <div class="if-panel if-empty" style="margin-top: 22px;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Grupo no encontrado</h2>
                <p>No se encontró información del grupo.</p>
            </div>
        @else
            @if (! $participa)
                <div class="if-warning-note" style="margin-top: 22px;">
                    Tu participación está retirada. Puedes consultar el grupo en modo lectura.
                </div>
            @endif

            <section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-grupo">
                <div class="if-section-heading" style="margin: 0;">
                    <div>
                        <h1 id="titulo-grupo" class="if-heading"> {{ $detalle->grupo->nombre }}</h1>
                        <p class="if-lead">
                            Sección {{ $seccion->codigo }} · {{ $detalle->entregados }} de 3 avances entregados
                        </p>
                    </div>

                    <div class="if-actions">
                        @if ($esCoordinador)
                            <span class="if-tag">Eres coordinador</span>
                        @endif

                        @if ($decision?->aprobado)
                            <span class="if-status is-success" data-aprobacion="vigente">Trabajo aprobado</span>
                        @elseif ($decision?->aprobacion_desactualizada)
                            <span class="if-status is-warning" data-aprobacion="desactualizada">Aprobación desactualizada</span>
                        @endif

                        @if ($decision?->destacado)
                            <span class="if-status is-success" data-destacado="vigente">Trabajo destacado</span>
                        @elseif ($decision?->seleccion_desactualizada)
                            <span class="if-status is-warning" data-destacado="desactualizado">Selección desactualizada</span>
                        @endif
                    </div>
                </div>

                @if ($detalle->proyecto !== null)
                    <dl class="if-dl">
                        <div>
                            <dt>Línea de investigación</dt>
                            <dd>{{ $detalle->proyecto->linea }}</dd>
                        </div>
                        <div>
                            <dt>Tipo de producto</dt>
                            <dd>{{ $detalle->proyecto->tipo_producto }}</dd>
                        </div>
                    </dl>
                @endif

                <h2 class="if-section-title">Integrantes</h2>
                <ul class="if-members" role="list">
                    @foreach ($detalle->miembros as $miembro)
                        <li>
                            <span>
                                <strong>{{ $miembro->nombre }}</strong>
                                <span class="if-note if-mono">{{ $miembro->codigo }}</span>
                            </span>
                            @if ($miembro->es_coordinador)
                                <span class="if-tag">Coordinador</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-propuesta">
                <div class="if-section-heading" style="margin: 0;">
                    <h2 id="titulo-propuesta" class="if-section-title">Título propuesto</h2>

                    @if ($detalle->proyecto !== null)
                        @php
                            $estado = $detalle->proyecto->estado;
                            $clasesEstado = match ($estado) {
                                'Aceptado' => 'is-success',
                                'Cambio solicitado' => 'is-danger',
                                default => 'is-warning',
                            };
                        @endphp
                        <span class="if-status {{ $clasesEstado }}">
                            {{ $estado }} · versión {{ $detalle->proyecto->version }}
                        </span>
                    @endif
                </div>

                @if ($detalle->proyecto === null)
                    <p class="if-text-warning">El grupo todavía no tiene un proyecto registrado.</p>
                @else
                    <blockquote class="if-quote" data-titulo-propuesto>{{ $detalle->proyecto->titulo }}</blockquote>

                    @if ($esCoordinador && $motivoBloqueo !== null)
                        <div class="if-warning-note">
                            {{ $motivoBloqueo }}
                        </div>
                    @elseif ($esCoordinador)
                        <form
                            class="if-form-grid"
                            method="POST"
                            action="{{ route('investigacion-formativa.alumno.titulos.update', $seccion) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input name="version" type="hidden" value="{{ $detalle->proyecto->version }}">

                            <div class="if-field">
                                <label for="titulo-propuesto">
                                    Editar título
                                </label>
                                <input
                                    id="titulo-propuesto"
                                    maxlength="250"
                                    name="titulo"
                                    required
                                    type="text"
                                    value="{{ old('titulo', $detalle->proyecto->titulo) }}"
                                >
                            </div>

                            <div>
                                <button
                                    class="if-button"
                                    type="submit"
                                >
                                    Guardar título
                                </button>
                            </div>
                        </form>
                    @endif
                @endif

                @if ($detalle->historial !== [])
                    <h3 class="if-section-title">Historial de revisiones</h3>
                    <div class="if-table-wrap">
                        <table class="if-table">
                            <caption class="sr-only">Revisiones registradas del título propuesto</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Título</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Motivo</th>
                                    <th scope="col">Autor</th>
                                    <th scope="col">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detalle->historial as $revision)
                                    <tr>
                                        <td data-label="Título">{{ $revision->titulo }}</td>
                                        <td data-label="Estado">{{ $revision->estado }}</td>
                                        <td data-label="Motivo">{{ $revision->motivo ?? '—' }}</td>
                                        <td data-label="Autor">{{ $revision->autor }}</td>
                                        <td data-label="Fecha">
                                            {{ \Carbon\CarbonImmutable::parse($revision->registrado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
@endsection
