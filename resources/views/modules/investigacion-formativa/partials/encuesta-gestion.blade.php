{{--
    Formulario compartido de gestión de la encuesta.

    La dirección y la secretaría usan el mismo formulario. Cada espacio
    entrega sus propias rutas y decide si puede cerrar la encuesta.
--}}
<section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-preguntas">
    <div class="if-section-heading" style="margin: 0;">
        <div>
            <h2 id="titulo-preguntas" class="if-section-title">Preguntas</h2>
            <p class="if-note" style="margin-top: 4px;">
                @if ($encuesta->estado === 'borrador')
                    Puede guardar entre 1 y 50 preguntas. Cada pregunta admite hasta 1000 caracteres.
                @else
                    Las preguntas publicadas son definitivas y no admiten cambios.
                @endif
            </p>
        </div>

        <span class="if-tag">
            <strong>{{ $encuesta->respuestas_count }}</strong>
            {{ $encuesta->respuestas_count === 1 ? 'respuesta registrada' : 'respuestas registradas' }}
        </span>
    </div>

    @if ($conflicto !== null)
        @php
            $normalizarPreguntas = static function (mixed $pregunta): array {
                if (is_array($pregunta)) {
                    $texto = $pregunta['texto'] ?? ($pregunta[0] ?? '');
                } elseif (is_object($pregunta)) {
                    $texto = $pregunta->texto ?? '';
                } else {
                    $texto = $pregunta;
                }

                return [
                    'texto' => is_scalar($texto) ? (string) $texto : '',
                ];
            };

            $preguntasIntentadasNormalizadas = array_values(array_map(
                $normalizarPreguntas,
                is_array($preguntasIntentadas) ? $preguntasIntentadas : [],
            ));
        @endphp

        <div class="if-split" data-conflicto-borrador>
            <section class="if-card" data-borrador-actual>
                <h3 class="if-card-title">Encuesta guardada actualmente · versión {{ $encuesta->version }}</h3>

                @if ($encuesta->preguntas === [])
                    <p class="if-card-text">El borrador actual no tiene preguntas.</p>
                @else
                    <ol class="if-list" role="list">
                        @foreach ($encuesta->preguntas as $pregunta)
                            <li>{{ $pregunta->texto }}</li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @if ($preguntasIntentadas !== null)
                <section class="if-card" data-borrador-intentado style="border-color: var(--portal-warning);">
                    <h3 class="if-card-title">Cambios enviados · versión {{ $versionIntentada ?? 'no disponible' }}</h3>

                    @if ($preguntasIntentadasNormalizadas === [])
                        <p class="if-card-text">No se recuperaron preguntas del intento de guardado.</p>
                    @else
                        <ol class="if-list" role="list">
                            @foreach ($preguntasIntentadasNormalizadas as $indice => $pregunta)
                                <li>{{ $pregunta['texto'] }}</li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endif
        </div>
    @elseif ($encuesta->estado === 'borrador')
        @php
            $normalizarPreguntas = static function (mixed $pregunta): array {
                if (is_array($pregunta)) {
                    $texto = $pregunta['texto'] ?? ($pregunta[0] ?? '');
                } elseif (is_object($pregunta)) {
                    $texto = $pregunta->texto ?? '';
                } else {
                    $texto = $pregunta;
                }

                return [
                    'texto' => is_scalar($texto) ? (string) $texto : '',
                ];
            };

            $preguntasRecuperadas = old('preguntas');

            if ($preguntasRecuperadas === null) {
                $preguntasRecuperadas = $encuesta->preguntas;
            }

            $preguntasGuardadas = array_values(array_map(
                $normalizarPreguntas,
                is_array($preguntasRecuperadas) ? $preguntasRecuperadas : [],
            ));

            if ($preguntasGuardadas === []) {
                $preguntasGuardadas = [['texto' => '']];
            }
        @endphp

        <div
            x-data="{ preguntas: @js($preguntasGuardadas) }"
            data-editor-preguntas
        >
            @if ($encuesta->preguntas === [] && old('preguntas') === null)
                <div class="if-card">
                    <p>No hay preguntas guardadas todavía. Agregue la primera pregunta para crear el borrador.</p>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route($rutaActualizar, $seccion) }}"
            >
                @csrf
                @method('PUT')
                <input name="version" type="hidden" value="{{ old('version', $encuesta->version) }}">

                <template x-for="(pregunta, indice) in preguntas" :key="indice">
                    <div class="if-card" style="margin-top: 12px;">
                        <div class="if-toolbar" style="align-items: flex-start; justify-content: space-between;">
                            <div class="if-field" style="flex: 1 1 320px;">
                                <label :for="`pregunta-${indice}`">
                                    Pregunta <span x-text="indice + 1"></span>
                                </label>
                                <textarea
                                    :id="`pregunta-${indice}`"
                                    :name="`preguntas[${indice}]`"
                                    maxlength="1000"
                                    required
                                    rows="3"
                                    x-model="pregunta.texto"
                                ></textarea>
                            </div>

                            <button
                                class="if-button-secondary"
                                style="min-height: 38px; padding: 0 12px; font-size: 13px;"
                                type="button"
                                x-show="preguntas.length > 1"
                                @click="preguntas.splice(indice, 1)"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                </template>

                <div class="if-actions" style="margin-top: 16px;">
                    <button
                        class="if-button-secondary"
                        type="button"
                        @click="preguntas.push({ texto: '' })"
                        :disabled="preguntas.length >= 50"
                    >
                        Agregar pregunta
                    </button>

                    <button class="if-button" type="submit">Guardar borrador</button>
                </div>
            </form>

            <details class="if-card">
                <summary class="if-card-title">Vista previa</summary>
                <ol class="if-list" role="list" style="margin-top: 10px;">
                    <template x-for="(pregunta, indice) in preguntas" :key="`preview-${indice}`">
                        <li x-text="pregunta.texto || 'Pregunta sin texto'"></li>
                    </template>
                </ol>
            </details>
        </div>
    @else
        @if ($encuesta->preguntas === [])
            <div class="if-card">
                <p>Todavía no hay preguntas guardadas.</p>
            </div>
        @else
            <ol class="if-stack" role="list">
                @foreach ($encuesta->preguntas as $pregunta)
                    <li class="if-card">
                        <span class="if-muted">{{ $pregunta->orden }}.</span>
                        {{ $pregunta->texto }}
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</section>

<section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-ciclo">
    <h2 id="titulo-ciclo" class="if-section-title">Ciclo de la encuesta</h2>
    <p class="if-note">
        Publicar fija las preguntas. Cerrar impide nuevas respuestas y no permite reabrir la encuesta.
    </p>

    @if ($conflicto === null)
        <div class="if-actions">
            @if ($encuesta->estado === 'borrador' && $encuesta->id_encuesta !== null && $encuesta->preguntas !== [])
                <form method="POST" action="{{ route($rutaPublicar, $seccion) }}">
                    @csrf
                    <input name="version" type="hidden" value="{{ $encuesta->version }}">
                    <button class="if-button" type="submit">Publicar encuesta</button>
                </form>
            @elseif ($encuesta->estado === 'publicada' && $rutaCerrar !== null)
                <form method="POST" action="{{ route($rutaCerrar, $seccion) }}">
                    @csrf
                    <input name="version" type="hidden" value="{{ $encuesta->version }}">
                    <button class="if-button if-button-danger" type="submit">Cerrar encuesta</button>
                </form>
            @endif
        </div>
    @endif

    @if ($encuesta->publicada_en !== null)
        <p class="if-note">
            Publicada el
            {{ \Carbon\CarbonImmutable::parse($encuesta->publicada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
        </p>
    @endif

    @if ($encuesta->cerrada_en !== null)
        <p class="if-note">
            Cerrada el
            {{ \Carbon\CarbonImmutable::parse($encuesta->cerrada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
        </p>
    @endif
</section>

<section class="if-panel if-stack" style="margin-top: 22px; padding: 22px;" aria-labelledby="titulo-respuestas">
    <h2 id="titulo-respuestas" class="if-section-title">Respuestas</h2>

    @if ($encuesta->respuestas_count === 0)
        <div class="if-card">
            <p>Todavía no hay respuestas registradas.</p>
        </div>
    @else
        <p>
            Hay {{ $encuesta->respuestas_count }} {{ $encuesta->respuestas_count === 1 ? 'respuesta registrada' : 'respuestas registradas' }}.
            Los resultados agregados y los comentarios se muestran en la pantalla de resultados de la sección.
        </p>
    @endif
</section>
