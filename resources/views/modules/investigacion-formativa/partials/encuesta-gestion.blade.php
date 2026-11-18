{{--
    Formulario compartido de gestión de la encuesta.

    La dirección y la secretaría usan el mismo formulario. Cada espacio
    entrega sus propias rutas y decide si puede cerrar la encuesta.
--}}
<section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-preguntas">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="titulo-preguntas" class="text-lg font-semibold text-gray-900">Preguntas</h2>
            <p class="mt-1 text-sm text-gray-600">
                @if ($encuesta->estado === 'borrador')
                    Puede guardar entre 1 y 50 preguntas. Cada pregunta admite hasta 1000 caracteres.
                @else
                    Las preguntas publicadas son definitivas y no admiten cambios.
                @endif
            </p>
        </div>

        <div class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
            <span class="font-semibold">{{ $encuesta->respuestas_count }}</span>
            {{ $encuesta->respuestas_count === 1 ? 'respuesta registrada' : 'respuestas registradas' }}
        </div>
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

        <div class="mt-5 grid gap-5 lg:grid-cols-2" data-conflicto-borrador>
            <section class="rounded-md border border-gray-200 p-4" data-borrador-actual>
                <h3 class="text-sm font-semibold text-gray-900">
                    Encuesta guardada actualmente · versión {{ $encuesta->version }}
                </h3>

                @if ($encuesta->preguntas === [])
                    <p class="mt-3 text-sm text-gray-600">El borrador actual no tiene preguntas.</p>
                @else
                    <ol class="mt-3 space-y-2 pl-5 text-sm text-gray-700" role="list">
                        @foreach ($encuesta->preguntas as $pregunta)
                            <li>{{ $pregunta->texto }}</li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @if ($preguntasIntentadas !== null)
                <section class="rounded-md border border-amber-300 bg-amber-50 p-4" data-borrador-intentado>
                    <h3 class="text-sm font-semibold text-gray-900">
                        Cambios enviados · versión {{ $versionIntentada ?? 'no disponible' }}
                    </h3>

                    @if ($preguntasIntentadasNormalizadas === [])
                        <p class="mt-3 text-sm text-gray-700">No se recuperaron preguntas del intento de guardado.</p>
                    @else
                        <ol class="mt-3 space-y-2 pl-5 text-sm text-gray-700" role="list">
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
            class="mt-5"
            x-data="{ preguntas: @js($preguntasGuardadas) }"
            data-editor-preguntas
        >
            @if ($encuesta->preguntas === [] && old('preguntas') === null)
                <p class="mb-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                    No hay preguntas guardadas todavía. Agregue la primera pregunta para crear el borrador.
                </p>
            @endif

            <form
                method="POST"
                action="{{ route($rutaActualizar, $seccion) }}"
            >
                @csrf
                @method('PUT')
                <input name="version" type="hidden" value="{{ old('version', $encuesta->version) }}">

                <template x-for="(pregunta, indice) in preguntas" :key="indice">
                    <div class="rounded-md border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <label
                                    class="block text-sm font-medium text-gray-700"
                                    :for="`pregunta-${indice}`"
                                >
                                    Pregunta <span x-text="indice + 1"></span>
                                </label>
                                <textarea
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :id="`pregunta-${indice}`"
                                    :name="`preguntas[${indice}]`"
                                    maxlength="1000"
                                    required
                                    rows="3"
                                    x-model="pregunta.texto"
                                ></textarea>
                            </div>

                            <button
                                class="rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                type="button"
                                x-show="preguntas.length > 1"
                                @click="preguntas.splice(indice, 1)"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                </template>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        type="button"
                        @click="preguntas.push({ texto: '' })"
                        :disabled="preguntas.length >= 50"
                    >
                        Agregar pregunta
                    </button>

                    <button
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        type="submit"
                    >
                        Guardar borrador
                    </button>
                </div>
            </form>

            <details class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-gray-900">Vista previa</summary>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700" role="list">
                    <template x-for="(pregunta, indice) in preguntas" :key="`preview-${indice}`">
                        <li x-text="pregunta.texto || 'Pregunta sin texto'"></li>
                    </template>
                </ol>
            </details>
        </div>
    @else
        @if ($encuesta->preguntas === [])
            <p class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                Todavía no hay preguntas guardadas.
            </p>
        @else
            <ol class="mt-5 space-y-3" role="list">
                @foreach ($encuesta->preguntas as $pregunta)
                    <li class="rounded-md border border-gray-200 p-4 text-sm text-gray-800">
                        <span class="mr-2 font-semibold text-gray-500">{{ $pregunta->orden }}.</span>
                        {{ $pregunta->texto }}
                    </li>
                @endforeach
            </ol>
        @endif
    @endif
</section>

<section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-ciclo">
    <h2 id="titulo-ciclo" class="text-lg font-semibold text-gray-900">Ciclo de la encuesta</h2>
    <p class="mt-1 text-sm text-gray-600">
        Publicar fija las preguntas. Cerrar impide nuevas respuestas y no permite reabrir la encuesta.
    </p>

    @if ($conflicto === null)
        <div class="mt-4 flex flex-wrap gap-3">
            @if ($encuesta->estado === 'borrador' && $encuesta->id_encuesta !== null && $encuesta->preguntas !== [])
                <form method="POST" action="{{ route($rutaPublicar, $seccion) }}">
                    @csrf
                    <input name="version" type="hidden" value="{{ $encuesta->version }}">
                    <button
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                        type="submit"
                    >
                        Publicar encuesta
                    </button>
                </form>
            @elseif ($encuesta->estado === 'publicada' && $rutaCerrar !== null)
                <form method="POST" action="{{ route($rutaCerrar, $seccion) }}">
                    @csrf
                    <input name="version" type="hidden" value="{{ $encuesta->version }}">
                    <button
                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"
                        type="submit"
                    >
                        Cerrar encuesta
                    </button>
                </form>
            @endif
        </div>
    @endif

    @if ($encuesta->publicada_en !== null)
        <p class="mt-4 text-sm text-gray-600">
            Publicada el
            {{ \Carbon\CarbonImmutable::parse($encuesta->publicada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
        </p>
    @endif

    @if ($encuesta->cerrada_en !== null)
        <p class="mt-1 text-sm text-gray-600">
            Cerrada el
            {{ \Carbon\CarbonImmutable::parse($encuesta->cerrada_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
        </p>
    @endif
</section>

<section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-respuestas">
    <h2 id="titulo-respuestas" class="text-lg font-semibold text-gray-900">Respuestas</h2>

    @if ($encuesta->respuestas_count === 0)
        <p class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
            Todavía no hay respuestas registradas.
        </p>
    @else
        <p class="mt-3 text-sm text-gray-700">
            Hay {{ $encuesta->respuestas_count }} {{ $encuesta->respuestas_count === 1 ? 'respuesta registrada' : 'respuestas registradas' }}.
            Los resultados agregados y los comentarios se muestran en la pantalla de resultados de la sección.
        </p>
    @endif
</section>
