@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Estudiante · Encuesta')
@section('page-label', 'Encuesta')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Encuesta de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Encuesta de la sección {{ $seccion->codigo }}</h1>
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

        @if ($encuesta->estado === 'no_disponible')
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 26px;" aria-labelledby="titulo-no-disponible">
                <h2 id="titulo-no-disponible" class="if-section-title">Todavía no hay una encuesta disponible</h2>
                <p class="if-lead">
                    Cuando la dirección de escuela publique la encuesta, sus preguntas aparecerán aquí.
                </p>
            </section>
        @elseif ($encuesta->respuesta !== null)
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 26px;" aria-labelledby="titulo-respondida">
                <div class="if-section-heading" style="margin: 0;">
                    <div>
                        <h2 id="titulo-respondida" class="if-section-title">Respuesta registrada</h2>
                        <p class="if-lead">
                            Enviada el
                            {{ \Carbon\CarbonImmutable::parse($encuesta->respuesta->respondida_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
                        </p>
                    </div>

                    <span class="if-status is-success">Confirmación recibida</span>
                </div>

                <ol class="if-stack" role="list">
                    @foreach ($encuesta->preguntas as $indice => $pregunta)
                        <li class="if-card">
                            <p class="if-card-title">
                                {{ $indice + 1 }}. {{ $pregunta->texto }}
                            </p>
                            <p class="if-text-success" style="margin-top: 8px; font-weight: 700;">
                                Respuesta: {{ $encuesta->respuesta->respuestas[$pregunta->id_pregunta] ?? '—' }} de 5
                            </p>
                        </li>
                    @endforeach
                </ol>

                @if ($encuesta->respuesta->comentario !== null)
                    <div class="if-card">
                        <p class="if-card-title">Comentario enviado</p>
                        <blockquote class="if-quote" style="margin-top: 10px;"> {{ $encuesta->respuesta->comentario }}</blockquote>
                    </div>
                @endif
            </section>
        @elseif ($encuesta->estado === 'cerrada')
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 26px;" aria-labelledby="titulo-cerrada">
                <h2 id="titulo-cerrada" class="if-section-title">La encuesta está cerrada</h2>
                <p class="if-lead">
                    Ya no admite nuevas respuestas. Si enviaste una antes del cierre, puedes consultarla desde el enlace de la encuesta.
                </p>
            </section>
        @elseif (! $encuesta->habilitada)
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 26px;" aria-labelledby="titulo-deshabilitada">
                <h2 id="titulo-deshabilitada" class="if-section-title">La sección está deshabilitada</h2>
                <p class="if-lead">
                    No se pueden registrar nuevas respuestas mientras la sección permanezca deshabilitada.
                </p>
            </section>
        @else
            <section class="if-panel if-stack" style="margin-top: 22px; padding: 26px;" aria-labelledby="titulo-responder">
                <h2 id="titulo-responder" class="if-section-title">Responde la encuesta</h2>
                <p class="if-lead">
                    Selecciona una opción entre 1 y 5 para cada pregunta. Todas las preguntas son obligatorias.
                </p>
                <p class="if-info-note">
                    Tus respuestas son confidenciales. El sistema conserva tu identidad para evitar envíos duplicados;
                    por eso la respuesta no se presenta como anónima.
                </p>

                <form
                    class="if-stack"
                    method="POST"
                    action="{{ route('investigacion-formativa.alumno.encuestas.store', $seccion) }}"
                >
                    @csrf

                    @foreach ($encuesta->preguntas as $indice => $pregunta)
                        <fieldset class="if-fieldset">
                            <legend class="if-legend">
                                {{ $indice + 1 }}. {{ $pregunta->texto }}
                            </legend>

                            <input
                                name="respuestas[{{ $indice }}][id_pregunta]"
                                type="hidden"
                                value="{{ $pregunta->id_pregunta }}"
                            >

                            <div class="if-choice-grid" style="margin-top: 12px;">
                                @foreach ([
                                    1 => 'Muy en desacuerdo',
                                    2 => 'En desacuerdo',
                                    3 => 'Neutral',
                                    4 => 'De acuerdo',
                                    5 => 'Muy de acuerdo',
                                ] as $valor => $etiqueta)
                                    <label
                                        class="if-choice"
                                        for="respuesta-{{ $pregunta->id_pregunta }}-{{ $valor }}"
                                    >
                                        <input
                                            id="respuesta-{{ $pregunta->id_pregunta }}-{{ $valor }}"
                                            name="respuestas[{{ $indice }}][valor]"
                                            type="radio"
                                            value="{{ $valor }}"
                                            @checked((string) old("respuestas.{$indice}.valor") === (string) $valor)
                                            required
                                        >
                                        <span>
                                            <strong>{{ $valor }}</strong>
                                            <span class="if-note">{{ $etiqueta }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="if-field">
                        <label for="comentario-encuesta">
                            Comentario opcional
                        </label>
                        <textarea
                            id="comentario-encuesta"
                            maxlength="2000"
                            name="comentario"
                            rows="3"
                        >{{ old('comentario') }}</textarea>
                    </div>

                    <div class="if-actions">
                        <button
                            class="if-button"
                            type="submit"
                        >
                            Enviar respuesta
                        </button>
                    </div>
                </form>
            </section>
        @endif
@endsection
