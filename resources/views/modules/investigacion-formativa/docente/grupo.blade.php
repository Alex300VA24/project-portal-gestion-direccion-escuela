@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Grupo de investigación')
@section('page-label', 'Grupo')

@section('content')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <a
                class="font-semibold text-indigo-700 hover:text-indigo-900"
                href="{{ route('investigacion-formativa.docente.grupos.index', $seccion) }}"
            >
                Grupos de la sección {{ $seccion->codigo }}
            </a>
            <span aria-hidden="true">/</span>
            <span>{{ $detalle?->grupo->nombre ?? 'Grupo' }}</span>
        </nav>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-conflicto>
                {{ $conflicto }} Recarga la página antes de volver a intentar.
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

        @if ($detalle === null || $detalle->proyecto === null)
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                Este grupo todavía no tiene un proyecto registrado.
            </p>
        @else
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-grupo">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 id="titulo-grupo" class="text-xl font-bold text-gray-900">{{ $detalle->grupo->nombre }}</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Sección {{ $seccion->codigo }} · {{ $detalle->entregados }} de 3 avances entregados
                        </p>
                    </div>

                    @php
                        $estado = $detalle->proyecto->estado;
                        $clasesEstado = match ($estado) {
                            'Aceptado' => 'bg-emerald-100 text-emerald-800',
                            'Cambio solicitado' => 'bg-rose-100 text-rose-800',
                            default => 'bg-amber-100 text-amber-800',
                        };
                    @endphp
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $clasesEstado }}">
                        {{ $estado }} · versión {{ $detalle->proyecto->version }}
                    </span>
                </div>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="font-medium text-gray-700">Línea de investigación</dt>
                        <dd class="text-gray-900">{{ $detalle->proyecto->linea }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-700">Tipo de producto</dt>
                        <dd class="text-gray-900">{{ $detalle->proyecto->tipo_producto }}</dd>
                    </div>
                </dl>

                <h2 class="mt-6 text-sm font-semibold text-gray-900">Integrantes</h2>
                <ul class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200" role="list">
                    @foreach ($detalle->miembros as $miembro)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span>
                                <span class="font-medium text-gray-900">{{ $miembro->nombre }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $miembro->codigo }}</span>
                            </span>
                            @if ($miembro->es_coordinador)
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                                    Coordinador
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-propuesta">
                <h2 id="titulo-propuesta" class="text-lg font-semibold text-gray-900">Título propuesto</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-900">{{ $detalle->proyecto->titulo }}</p>

                <form
                    class="mt-5 rounded-md border border-gray-200 p-4"
                    method="POST"
                    action="{{ route('investigacion-formativa.docente.titulos.update', [$seccion, $grupo]) }}"
                >
                    @csrf
                    @method('PATCH')

                    <input name="version" type="hidden" value="{{ $detalle->proyecto->version }}">

                    <fieldset>
                        <legend class="text-sm font-semibold text-gray-900">Revisión del título</legend>

                        <div class="mt-3 flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm text-gray-700" for="estado-aceptado">
                                <input
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    id="estado-aceptado"
                                    name="estado"
                                    type="radio"
                                    value="Aceptado"
                                    @checked(old('estado') === 'Aceptado')
                                >
                                Aceptar título
                            </label>

                            <label class="flex items-center gap-2 text-sm text-gray-700" for="estado-cambio">
                                <input
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    id="estado-cambio"
                                    name="estado"
                                    type="radio"
                                    value="Cambio solicitado"
                                    @checked(old('estado') === 'Cambio solicitado')
                                >
                                Solicitar cambios
                            </label>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700" for="motivo-revision">
                                Motivo del cambio solicitado
                            </label>
                            <textarea
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                id="motivo-revision"
                                maxlength="2000"
                                name="motivo"
                                rows="3"
                            >{{ old('motivo') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                El motivo es obligatorio cuando solicitas cambios.
                            </p>
                        </div>

                        <button
                            class="mt-4 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            type="submit"
                        >
                            Registrar revisión
                        </button>
                    </fieldset>
                </form>
            </section>

            @if ($detalle->historial !== [])
                <section class="mt-6" aria-labelledby="titulo-historial">
                    <h2 id="titulo-historial" class="text-lg font-semibold text-gray-900">Historial de revisiones</h2>

                    <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th class="px-3 py-2" scope="col">Título</th>
                                    <th class="px-3 py-2" scope="col">Estado</th>
                                    <th class="px-3 py-2" scope="col">Motivo</th>
                                    <th class="px-3 py-2" scope="col">Autor</th>
                                    <th class="px-3 py-2" scope="col">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($detalle->historial as $revision)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">{{ $revision->titulo }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->estado }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->motivo ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->autor }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-700">
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

        <section class="mt-6" aria-labelledby="titulo-avances">
            <h2 id="titulo-avances" class="text-lg font-semibold text-gray-900">Avances y evaluaciones</h2>

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
                    class="mt-4 rounded-lg border border-gray-200 bg-white p-5"
                    aria-labelledby="titulo-avance-{{ $avance->id_entrega }}"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 id="titulo-avance-{{ $avance->id_entrega }}" class="font-semibold text-gray-900">
                                {{ $avance->unidad }} · versión {{ $avance->version }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Entregada el
                                {{ \Carbon\CarbonImmutable::parse($avance->entregado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($avance->es_ultima)
                                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                                    Versión más reciente
                                </span>
                            @endif

                            @if ($evaluacionActual !== null)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                    Evaluada · nota {{ $evaluacionActual->nota ?? 'no disponible' }}
                                </span>
                            @elseif ($avance->es_ultima)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">
                                    Pendiente de evaluación
                                </span>
                            @else
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                    Versión anterior sin evaluación
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($evaluacionActual !== null)
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt class="font-medium text-gray-700">Nota</dt>
                                <dd class="text-lg font-semibold text-gray-900">{{ $evaluacionActual->nota ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-700">Puntos derivados</dt>
                                <dd class="text-gray-900">{{ $evaluacionActual->puntos }} / {{ $evaluacionActual->maximo }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-700">Revisión</dt>
                                <dd class="text-gray-900">Versión {{ $evaluacionActual->version }}</dd>
                            </div>
                        </dl>

                        @if ($evaluacionActual->comentario !== null)
                            <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800">
                                <p class="font-semibold">Comentario del docente</p>
                                <p class="mt-1 whitespace-pre-line">{{ $evaluacionActual->comentario }}</p>
                            </div>
                        @endif
                    @endif

                    <form
                        class="mt-5 rounded-md border border-gray-200 p-4"
                        method="POST"
                        action="{{ route('investigacion-formativa.docente.evaluaciones.update', [$seccion, $grupo, $avance->id_entrega]) }}"
                    >
                        @csrf
                        @method('PUT')

                        <input name="version" type="hidden" value="{{ $evaluacionActual?->version ?? 0 }}">

                        <fieldset>
                            <legend class="text-sm font-semibold text-gray-900">Criterios de evaluación</legend>
                            <p class="mt-1 text-xs text-gray-500">
                                Seleccione un nivel por criterio. Los puntajes y la nota los calcula el servidor.
                            </p>

                            @foreach ($avance->rubrica->criterios as $indiceCriterio => $criterio)
                                @php
                                    $nivelSeleccionado = old(
                                        "criterios.{$indiceCriterio}.nivel",
                                        $nivelesSeleccionados[$criterio->id_criterio] ?? null,
                                    );
                                @endphp

                                <div class="mt-4 rounded-md border border-gray-200 p-3">
                                    <input
                                        name="criterios[{{ $indiceCriterio }}][id_criterio]"
                                        type="hidden"
                                        value="{{ $criterio->id_criterio }}"
                                    >

                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $criterio->descripcion }}
                                        <span class="font-normal text-gray-500">· máximo {{ $criterio->puntaje_maximo }}</span>
                                    </p>

                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                        @foreach ($criterio->niveles as $nivel)
                                            <label
                                                class="flex items-start gap-2 rounded border border-gray-200 p-2 text-sm text-gray-700"
                                                for="nivel-{{ $avance->id_entrega }}-{{ $criterio->id_criterio }}-{{ $nivel->nivel }}"
                                            >
                                                <input
                                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    id="nivel-{{ $avance->id_entrega }}-{{ $criterio->id_criterio }}-{{ $nivel->nivel }}"
                                                    name="criterios[{{ $indiceCriterio }}][nivel]"
                                                    type="radio"
                                                    value="{{ $nivel->nivel }}"
                                                    @checked((string) $nivelSeleccionado === (string) $nivel->nivel)
                                                    required
                                                >
                                                <span>
                                                    <span class="block font-medium">Nivel {{ $nivel->nivel }} · {{ $nivel->puntaje }} puntos</span>
                                                    <span class="block text-xs text-gray-500">{{ $nivel->descripcion }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>

                        <div class="mt-5">
                            <label class="block text-sm font-medium text-gray-700" for="comentario-{{ $avance->id_entrega }}">
                                Comentario de la evaluación
                            </label>
                            <textarea
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                id="comentario-{{ $avance->id_entrega }}"
                                maxlength="4000"
                                name="comentario"
                                rows="3"
                            >{{ old('comentario', $evaluacionActual?->comentario) }}</textarea>
                        </div>

                        <div class="mt-5">
                            <input name="sin_observaciones" type="hidden" value="0">
                            <label class="flex items-center gap-2 text-sm text-gray-700" for="sin-observaciones-{{ $avance->id_entrega }}">
                                <input
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    id="sin-observaciones-{{ $avance->id_entrega }}"
                                    name="sin_observaciones"
                                    type="checkbox"
                                    value="1"
                                    @checked(filter_var($sinObservacionesActual, FILTER_VALIDATE_BOOL))
                                >
                                La entrega no tiene observaciones
                            </label>
                            <p class="mt-1 text-xs text-gray-500">
                                Si existe una observación, complete al menos un campo. Si la marca, deje los campos vacíos.
                            </p>

                            <div class="mt-3 grid gap-3">
                                @for ($indiceObservacion = 0; $indiceObservacion < $cantidadObservaciones; $indiceObservacion++)
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700"
                                            for="observacion-{{ $avance->id_entrega }}-{{ $indiceObservacion }}"
                                        >
                                            Observación {{ $indiceObservacion + 1 }}
                                        </label>
                                        <textarea
                                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            id="observacion-{{ $avance->id_entrega }}-{{ $indiceObservacion }}"
                                            maxlength="2000"
                                            name="observaciones[{{ $indiceObservacion }}]"
                                            rows="2"
                                        >{{ old("observaciones.{$indiceObservacion}", $observacionesPredeterminadas[$indiceObservacion] ?? '') }}</textarea>
                                    </div>
                                @endfor
                            </div>
                        </div>

                        <button
                            class="mt-5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            type="submit"
                        >
                            {{ $evaluacionActual === null ? 'Registrar evaluación' : 'Corregir evaluación' }}
                        </button>
                    </form>

                    @if ($avance->historial_observaciones !== [])
                        <details class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-900">
                                Historial de observaciones
                            </summary>

                            <ul class="mt-3 space-y-2" role="list">
                                @foreach ($avance->historial_observaciones as $observacion)
                                    <li class="rounded border border-gray-200 bg-white p-3 text-sm text-gray-800">
                                        <p>{{ $observacion->descripcion }}</p>
                                        <p class="mt-1 text-xs text-gray-500">
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
                <p class="mt-3 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                    Este grupo todavía no tiene entregas registradas.
                </p>
            @endforelse
        </section>
@endsection
