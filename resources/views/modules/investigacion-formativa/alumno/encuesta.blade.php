<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Estudiante · Encuesta
        </h2>
    </x-slot>

    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Encuesta de la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Encuesta de la sección {{ $seccion->codigo }}</h1>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                <ul class="list-disc space-y-1 pl-5" role="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($encuesta->estado === 'no_disponible')
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6" aria-labelledby="titulo-no-disponible">
                <h2 id="titulo-no-disponible" class="text-lg font-semibold text-gray-900">Todavía no hay una encuesta disponible</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Cuando la dirección de escuela publique la encuesta, sus preguntas aparecerán aquí.
                </p>
            </section>
        @elseif ($encuesta->respuesta !== null)
            <section class="mt-6 rounded-lg border border-emerald-200 bg-white p-6" aria-labelledby="titulo-respondida">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="titulo-respondida" class="text-lg font-semibold text-gray-900">Respuesta registrada</h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Enviada el
                            {{ \Carbon\CarbonImmutable::parse($encuesta->respuesta->respondida_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}.
                        </p>
                    </div>

                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                        Confirmación recibida
                    </span>
                </div>

                <ol class="mt-5 space-y-4" role="list">
                    @foreach ($encuesta->preguntas as $indice => $pregunta)
                        <li class="rounded-md border border-gray-200 p-4">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $indice + 1 }}. {{ $pregunta->texto }}
                            </p>
                            <p class="mt-2 text-sm font-semibold text-indigo-700">
                                Respuesta: {{ $encuesta->respuesta->respuestas[$pregunta->id_pregunta] ?? '—' }} de 5
                            </p>
                        </li>
                    @endforeach
                </ol>

                @if ($encuesta->respuesta->comentario !== null)
                    <div class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                        <p class="text-sm font-semibold text-gray-800">Comentario enviado</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $encuesta->respuesta->comentario }}</p>
                    </div>
                @endif
            </section>
        @elseif ($encuesta->estado === 'cerrada')
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6" aria-labelledby="titulo-cerrada">
                <h2 id="titulo-cerrada" class="text-lg font-semibold text-gray-900">La encuesta está cerrada</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Ya no admite nuevas respuestas. Si enviaste una antes del cierre, puedes consultarla desde el enlace de la encuesta.
                </p>
            </section>
        @elseif (! $encuesta->habilitada)
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6" aria-labelledby="titulo-deshabilitada">
                <h2 id="titulo-deshabilitada" class="text-lg font-semibold text-gray-900">La sección está deshabilitada</h2>
                <p class="mt-2 text-sm text-gray-600">
                    No se pueden registrar nuevas respuestas mientras la sección permanezca deshabilitada.
                </p>
            </section>
        @else
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6" aria-labelledby="titulo-responder">
                <h2 id="titulo-responder" class="text-lg font-semibold text-gray-900">Responde la encuesta</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Selecciona una opción entre 1 y 5 para cada pregunta. Todas las preguntas son obligatorias.
                </p>
                <p class="mt-3 rounded-md border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-900">
                    Tus respuestas son confidenciales. El sistema conserva tu identidad para evitar envíos duplicados;
                    por eso la respuesta no se presenta como anónima.
                </p>

                <form
                    class="mt-6 space-y-6"
                    method="POST"
                    action="{{ route('investigacion-formativa.alumno.encuestas.store', $seccion) }}"
                >
                    @csrf

                    @foreach ($encuesta->preguntas as $indice => $pregunta)
                        <fieldset class="rounded-md border border-gray-200 p-4">
                            <legend class="px-1 text-sm font-semibold text-gray-900">
                                {{ $indice + 1 }}. {{ $pregunta->texto }}
                            </legend>

                            <input
                                name="respuestas[{{ $indice }}][id_pregunta]"
                                type="hidden"
                                value="{{ $pregunta->id_pregunta }}"
                            >

                            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                @foreach ([
                                    1 => 'Muy en desacuerdo',
                                    2 => 'En desacuerdo',
                                    3 => 'Neutral',
                                    4 => 'De acuerdo',
                                    5 => 'Muy de acuerdo',
                                ] as $valor => $etiqueta)
                                    <label
                                        class="flex cursor-pointer items-start gap-2 rounded border border-gray-200 p-3 text-sm text-gray-700"
                                        for="respuesta-{{ $pregunta->id_pregunta }}-{{ $valor }}"
                                    >
                                        <input
                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            id="respuesta-{{ $pregunta->id_pregunta }}-{{ $valor }}"
                                            name="respuestas[{{ $indice }}][valor]"
                                            type="radio"
                                            value="{{ $valor }}"
                                            @checked((string) old("respuestas.{$indice}.valor") === (string) $valor)
                                            required
                                        >
                                        <span>
                                            <span class="block font-semibold">{{ $valor }}</span>
                                            <span class="block text-xs text-gray-500">{{ $etiqueta }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="comentario-encuesta">
                            Comentario opcional
                        </label>
                        <textarea
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            id="comentario-encuesta"
                            maxlength="2000"
                            name="comentario"
                            rows="3"
                        >{{ old('comentario') }}</textarea>
                    </div>

                    <button
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        type="submit"
                    >
                        Enviar respuesta
                    </button>
                </form>
            </section>
        @endif
    </main>
</x-app-layout>
