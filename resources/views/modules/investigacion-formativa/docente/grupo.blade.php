@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Grupo de investigación')
@section('page-label', 'Grupo')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <a
                class="if-link"
                href="{{ route('investigacion-formativa.docente.grupos.index', $seccion) }}"
            >
                Grupos de la sección {{ $seccion->codigo }}
            </a>
            <span aria-hidden="true">/</span>
            <span>{{ $detalle?->grupo->nombre ?? 'Grupo' }}</span>
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

        @if ($detalle === null || $detalle->proyecto === null)
            <div class="if-panel if-empty" style="margin-top: 22px;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <h2>Sin proyecto registrado</h2>
                <p>Este grupo todavía no tiene un proyecto registrado.</p>
            </div>
        @else
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-grupo">
                <div class="if-section-heading" style="margin: 0;">
                    <div>
                        <h1 id="titulo-grupo" class="if-heading"> {{ $detalle->grupo->nombre }}</h1>
                        <p class="if-lead">
                            Sección {{ $seccion->codigo }} · {{ $detalle->entregados }} de 3 avances entregados
                        </p>
                    </div>

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
                </div>

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
                <h2 id="titulo-propuesta" class="if-section-title">Título propuesto</h2>
                <blockquote class="if-quote" data-titulo-propuesto>{{ $detalle->proyecto->titulo }}</blockquote>

                <form
                    class="if-fieldset if-stack"
                    method="POST"
                    action="{{ route('investigacion-formativa.docente.titulos.update', [$seccion, $grupo]) }}"
                >
                    @csrf
                    @method('PATCH')

                    <input name="version" type="hidden" value="{{ $detalle->proyecto->version }}">

                    <fieldset>
                        <legend class="if-legend">Revisión del título</legend>

                        <div class="if-choice-list">
                            <label class="if-choice" for="estado-aceptado">
                                <input
                                    id="estado-aceptado"
                                    name="estado"
                                    type="radio"
                                    value="Aceptado"
                                    @checked(old('estado') === 'Aceptado')
                                >
                                <span>Aceptar título</span>
                            </label>

                            <label class="if-choice" for="estado-cambio">
                                <input
                                    id="estado-cambio"
                                    name="estado"
                                    type="radio"
                                    value="Cambio solicitado"
                                    @checked(old('estado') === 'Cambio solicitado')
                                >
                                <span>Solicitar cambios</span>
                            </label>
                        </div>

                        <div class="if-field" style="margin-top: 14px;">
                            <label for="motivo-revision">
                                Motivo del cambio solicitado
                            </label>
                            <textarea
                                id="motivo-revision"
                                maxlength="2000"
                                name="motivo"
                                rows="3"
                            >{{ old('motivo') }}</textarea>
                            <p class="if-field-hint">
                                El motivo es obligatorio cuando solicitas cambios.
                            </p>
                        </div>

                        <div class="if-actions" style="margin-top: 14px;">
                            <button class="if-button" type="submit">
                                Registrar revisión
                            </button>
                        </div>
                    </fieldset>
                </form>
            </section>

            <section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-decisiones">
                <h2 id="titulo-decisiones" class="if-section-title">Aprobación final y trabajo destacado</h2>
                <p class="if-note">
                    La aprobación final es una decisión explícita del docente responsable. No se deduce de la nota
                    ni del estado del título. La selección del trabajo destacado es manual y se registra aparte de la
                    mejor nota.
                </p>

                @php
                    $aprobado = $estadoDecision?->aprobado ?? false;
                    $aprobacionDesactualizada = $estadoDecision?->aprobacion_desactualizada ?? false;
                    $destacado = $estadoDecision?->destacado ?? false;
                    $seleccionDesactualizada = $estadoDecision?->seleccion_desactualizada ?? false;
                @endphp

                <div class="if-actions" style="margin-top: 10px;">
                    @if ($aprobado)
                        <span class="if-status is-success" data-aprobacion="vigente">Trabajo aprobado</span>
                    @elseif ($aprobacionDesactualizada)
                        <span class="if-status is-warning" data-aprobacion="desactualizada">Aprobación desactualizada</span>
                    @else
                        <span class="if-status is-neutral" data-aprobacion="pendiente">Sin aprobación final</span>
                    @endif

                    @if ($destacado)
                        <span class="if-status is-success" data-destacado="vigente">Trabajo destacado de la sección</span>
                    @elseif ($seleccionDesactualizada)
                        <span class="if-status is-warning" data-destacado="desactualizado">Selección desactualizada</span>
                    @endif
                </div>

                @if ($aprobacionDesactualizada)
                    <div class="if-warning-note">
                        La evidencia de entrega o evaluación cambió desde la aprobación. Confirme la aprobación
                        para que cuente como vigente.
                    </div>
                @endif

                @if ($puedeDecidir)
                    <form
                        class="if-fieldset if-stack"
                        method="POST"
                        action="{{ route('investigacion-formativa.docente.decisiones.aprobar', [$seccion, $grupo]) }}"
                    >
                        @csrf
                        <input name="version" type="hidden" value="{{ $versionAprobacion }}">

                        <div class="if-actions">
                            <button class="if-button" type="submit">
                                {{ $aprobacionDesactualizada ? 'Confirmar aprobación' : 'Aprobar trabajo final' }}
                            </button>
                        </div>
                        <p class="if-note">
                            Requiere las tres unidades con su versión más reciente evaluada. Si una unidad cambia,
                            la aprobación vigente queda desactualizada.
                        </p>
                    </form>
                @else
                    <p class="if-note">
                        La sección está cerrada, deshabilitada o incompleta: las decisiones quedan en solo lectura.
                    </p>
                @endif

                <h3 class="if-section-title">Trabajo destacado</h3>

                @if ($seleccion !== null && (int) $seleccion->id_grupo !== (int) $grupo->id_grupo)
                    <p>
                        Trabajo destacado actual:
                        <strong>{{ $seleccion->nombre }}</strong> · {{ $seleccion->titulo }}
                        @if ($seleccion->desactualizada)
                            <span class="if-status is-warning">desactualizado</span>
                        @endif
                    </p>
                @endif

                @if ($puedeDecidir && ($aprobado || $seleccionDesactualizada))
                    <form
                        class="if-fieldset if-stack"
                        method="POST"
                        action="{{ route('investigacion-formativa.docente.decisiones.seleccionar', [$seccion, $grupo]) }}"
                    >
                        @csrf
                        <input name="version" type="hidden" value="{{ $versionSeleccion }}">

                        <div class="if-actions">
                            <button class="if-button" type="submit">
                                {{ $seleccionDesactualizada ? 'Confirmar selección' : ($destacado ? 'Mantener como destacado' : 'Marcar como trabajo destacado') }}
                            </button>
                        </div>
                    </form>
                @elseif ($puedeDecidir)
                    <p class="if-note">
                        Solo un trabajo con aprobación final vigente puede marcarse como destacado.
                    </p>
                @endif

                @if ($puedeDecidir && $destacado)
                    <form
                        class="if-fieldset if-stack"
                        method="POST"
                        action="{{ route('investigacion-formativa.docente.decisiones.retirar', [$seccion, $grupo]) }}"
                    >
                        @csrf
                        @method('DELETE')
                        <input name="version" type="hidden" value="{{ $versionSeleccion }}">

                        <div class="if-field">
                            <label for="motivo-retiro">Motivo del retiro (opcional)</label>
                            <textarea id="motivo-retiro" maxlength="2000" name="motivo" rows="2">{{ old('motivo') }}</textarea>
                        </div>

                        <div class="if-actions">
                            <button class="if-button if-button-danger" type="submit">Retirar selección</button>
                        </div>
                    </form>
                @endif

                @if ($historialAprobacion !== [])
                    <details class="if-card" style="margin-top: 16px;">
                        <summary class="if-card-title">Historial de aprobación</summary>
                        <div class="if-table-wrap" style="margin-top: 10px;">
                            <table class="if-table">
                                <caption class="sr-only">Decisiones de aprobación final del trabajo</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Decisión</th>
                                        <th scope="col">Versión</th>
                                        <th scope="col">Actor</th>
                                        <th scope="col">Fecha</th>
                                        <th scope="col">Evidencia actual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($historialAprobacion as $decision)
                                        <tr>
                                            <td data-label="Decisión">{{ $decision->accion === 'retirar' ? 'Retirada' : 'Aprobación' }}</td>
                                            <td data-label="Versión">{{ $decision->version }}</td>
                                            <td data-label="Actor">{{ $decision->actor }}</td>
                                            <td data-label="Fecha">
                                                {{ \Carbon\CarbonImmutable::parse($decision->decidido_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                            </td>
                                            <td data-label="Evidencia actual">
                                                {{ $decision->desactualizada ? 'La evidencia cambió' : 'Coincide con la evidencia actual' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif

                @if ($historialSeleccion !== [])
                    <details class="if-card" style="margin-top: 16px;">
                        <summary class="if-card-title">Historial de selección del trabajo destacado</summary>
                        <div class="if-table-wrap" style="margin-top: 10px;">
                            <table class="if-table">
                                <caption class="sr-only">Decisiones de selección del trabajo destacado de la sección</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Decisión</th>
                                        <th scope="col">Grupo</th>
                                        <th scope="col">Versión</th>
                                        <th scope="col">Actor</th>
                                        <th scope="col">Fecha</th>
                                        <th scope="col">Motivo / evidencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($historialSeleccion as $decision)
                                        <tr>
                                            <td data-label="Decisión">{{ $decision->accion === 'retirar' ? 'Retiro' : 'Selección' }}</td>
                                            <td data-label="Grupo">{{ $decision->grupo }}</td>
                                            <td data-label="Versión">{{ $decision->version }}</td>
                                            <td data-label="Actor">{{ $decision->actor }}</td>
                                            <td data-label="Fecha">
                                                {{ \Carbon\CarbonImmutable::parse($decision->decidido_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                            </td>
                                            <td data-label="Motivo / evidencia">
                                                {{ $decision->motivo ?? ($decision->desactualizada ? 'Evidencia desactualizada' : '—') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>

            @if ($detalle->historial !== [])
                <section style="margin-top: 22px;" aria-labelledby="titulo-historial">
                    <h2 id="titulo-historial" class="if-section-title">Historial de revisiones</h2>

                    <div class="if-table-wrap" style="margin-top: 12px;">
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
                </section>
            @endif
        @endif

        @php
            $avances = $evaluaciones ?? [];
        @endphp

        <section style="margin-top: 22px;" aria-labelledby="titulo-avances">
            <h2 id="titulo-avances" class="if-section-title">Avances y evaluaciones</h2>

            @forelse ($avances as $avance)
                @php
                    $evaluacionActual = $avance->evaluacion;
                    $nivelesSeleccionados = [];

                    foreach ($avance->rubrica->criterios as $criterioEvaluacion) {
                        foreach ($criterioEvaluacion->niveles as $nivelCriterio) {
                            if ($nivelCriterio->seleccionado) {
                                $nivelesSeleccionados[$criterioEvaluacion->id_criterio] = $nivelCriterio->nivel;
                            }
                        }
                    }

                    $observacionesPredeterminadas = array_map(
                        static fn (object $observacion): string => (string) $observacion->descripcion,
                        $avance->observaciones,
                    );
                    $cantidadObservaciones = max(3, count($observacionesPredeterminadas));
                    $sinObservacionesActual = old(
                        'sin_observaciones',
                        $evaluacionActual?->sin_observaciones ?? false,
                    );
                @endphp

                <article
                    id="avance-{{ $avance->id_entrega }}"
                    class="if-panel if-stack"
                    style="margin-top: 16px; padding: 22px;"
                    aria-labelledby="titulo-avance-{{ $avance->id_entrega }}"
                >
                    <div class="if-section-heading" style="margin: 0;">
                        <div>
                            <h3 id="titulo-avance-{{ $avance->id_entrega }}" class="if-section-title">
                                {{ $avance->unidad }} · versión {{ $avance->version }}
                            </h3>
                            <p class="if-note">
                                Entregada el
                                {{ \Carbon\CarbonImmutable::parse($avance->entregado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <div class="if-actions">
                            @if ($avance->es_ultima)
                                <span class="if-tag">Versión más reciente</span>
                            @endif

                            @if ($evaluacionActual !== null)
                                <span class="if-status is-success">
                                    Evaluada · nota {{ $evaluacionActual->nota ?? 'no disponible' }}
                                </span>
                            @elseif ($avance->es_ultima)
                                <span class="if-status is-warning">Pendiente de evaluación</span>
                            @else
                                <span class="if-status is-neutral">Versión anterior sin evaluación</span>
                            @endif
                        </div>
                    </div>

                    @if ($evaluacionActual !== null)
                        <dl class="if-dl">
                            <div>
                                <dt>Nota</dt>
                                <dd class="if-mono">{{ $evaluacionActual->nota ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Puntos derivados</dt>
                                <dd>{{ $evaluacionActual->puntos }} / {{ $evaluacionActual->maximo }}</dd>
                            </div>
                            <div>
                                <dt>Revisión</dt>
                                <dd>Versión {{ $evaluacionActual->version }}</dd>
                            </div>
                        </dl>

                        @if ($evaluacionActual->comentario !== null)
                            <div class="if-card">
                                <p class="if-card-title">Comentario del docente</p>
                                <blockquote class="if-quote" style="margin-top: 10px;"> {{ $evaluacionActual->comentario }}</blockquote>
                            </div>
                        @endif
                    @endif

                    <form
                        class="if-fieldset if-stack"
                        method="POST"
                        action="{{ route('investigacion-formativa.docente.evaluaciones.update', [$seccion, $grupo, $avance->id_entrega]) }}"
                    >
                        @csrf
                        @method('PUT')

                        <input name="version" type="hidden" value="{{ $evaluacionActual?->version ?? 0 }}">

                        <fieldset>
                            <legend class="if-legend">Criterios de evaluación</legend>
                            <p class="if-note" style="margin-top: 4px;">
                                Seleccione un nivel por criterio. Los puntajes y la nota los calcula el servidor.
                            </p>

                            @foreach ($avance->rubrica->criterios as $indiceCriterio => $criterio)
                                @php
                                    $nivelSeleccionado = old(
                                        "criterios.{$indiceCriterio}.nivel",
                                        $nivelesSeleccionados[$criterio->id_criterio] ?? null,
                                    );
                                @endphp

                                <div class="if-card" style="margin-top: 14px;">
                                    <input
                                        name="criterios[{{ $indiceCriterio }}][id_criterio]"
                                        type="hidden"
                                        value="{{ $criterio->id_criterio }}"
                                    >

                                    <p class="if-card-title">
                                        {{ $criterio->descripcion }}
                                        <span class="if-note">· máximo {{ $criterio->puntaje_maximo }}</span>
                                    </p>

                                    <div class="if-choice-grid" style="margin-top: 10px;">
                                        @foreach ($criterio->niveles as $nivel)
                                            <label
                                                class="if-choice"
                                                for="nivel-{{ $avance->id_entrega }}-{{ $criterio->id_criterio }}-{{ $nivel->nivel }}"
                                            >
                                                <input
                                                    id="nivel-{{ $avance->id_entrega }}-{{ $criterio->id_criterio }}-{{ $nivel->nivel }}"
                                                    name="criterios[{{ $indiceCriterio }}][nivel]"
                                                    type="radio"
                                                    value="{{ $nivel->nivel }}"
                                                    @checked((string) $nivelSeleccionado === (string) $nivel->nivel)
                                                    required
                                                >
                                                <span>
                                                    <strong>Nivel {{ $nivel->nivel }} · {{ $nivel->puntaje }} puntos</strong>
                                                    <span class="if-note">{{ $nivel->descripcion }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>

                        <div class="if-field">
                            <label for="comentario-{{ $avance->id_entrega }}">
                                Comentario de la evaluación
                            </label>
                            <textarea
                                id="comentario-{{ $avance->id_entrega }}"
                                maxlength="4000"
                                name="comentario"
                                rows="3"
                            >{{ old('comentario', $evaluacionActual?->comentario) }}</textarea>
                        </div>

                        <div>
                            <input name="sin_observaciones" type="hidden" value="0">
                            <label class="if-choice" for="sin-observaciones-{{ $avance->id_entrega }}">
                                <input
                                    id="sin-observaciones-{{ $avance->id_entrega }}"
                                    name="sin_observaciones"
                                    type="checkbox"
                                    value="1"
                                    @checked(filter_var($sinObservacionesActual, FILTER_VALIDATE_BOOL))
                                >
                                <span>La entrega no tiene observaciones</span>
                            </label>
                            <p class="if-field-hint">
                                Si existe una observación, complete al menos un campo. Si la marca, deje los campos vacíos.
                            </p>

                            <div class="if-stack" style="margin-top: 12px;">
                                @for ($indiceObservacion = 0; $indiceObservacion < $cantidadObservaciones; $indiceObservacion++)
                                    <div class="if-field">
                                        <label
                                            for="observacion-{{ $avance->id_entrega }}-{{ $indiceObservacion }}"
                                        >
                                            Observación {{ $indiceObservacion + 1 }}
                                        </label>
                                        <textarea
                                            id="observacion-{{ $avance->id_entrega }}-{{ $indiceObservacion }}"
                                            maxlength="2000"
                                            name="observaciones[{{ $indiceObservacion }}]"
                                            rows="2"
                                        >{{ old("observaciones.{$indiceObservacion}", $observacionesPredeterminadas[$indiceObservacion] ?? '') }}</textarea>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <div class="if-actions">
                            <button
                                class="if-button"
                                type="submit"
                            >
                                {{ $evaluacionActual === null ? 'Registrar evaluación' : 'Corregir evaluación' }}
                            </button>
                        </div>
                    </form>

                    @if ($avance->historial_observaciones !== [])
                        <details class="if-card">
                            <summary class="if-card-title">Historial de observaciones</summary>

                            <ul class="if-list" role="list" style="margin-top: 10px;">
                                @foreach ($avance->historial_observaciones as $observacion)
                                    <li>
                                        <p>{{ $observacion->descripcion }}</p>
                                        <p class="if-note">
                                            {{ $observacion->autor !== '' ? $observacion->autor : 'Docente' }}
                                            ·
                                            {{ $observacion->estado }}
                                            ·
                                            revisión {{ $observacion->version_evaluacion ?? 'histórica' }}
                                            ·
                                            {{ \Carbon\CarbonImmutable::parse($observacion->registrado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </article>
            @empty
                <div class="if-panel if-empty" style="margin-top: 16px;">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <h2>Sin entregas</h2>
                    <p>Este grupo todavía no tiene entregas registradas.</p>
                </div>
            @endforelse
        </section>
@endsection
