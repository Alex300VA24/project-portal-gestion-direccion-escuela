<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dirección de escuela
        </h2>
    </x-slot>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <h1 class="text-2xl font-bold text-gray-900">Dirección de escuela</h1>
        <p class="mt-2 max-w-3xl text-sm text-gray-600">
            Resumen de las secciones de sus escuelas asignadas. Incluye secciones cerradas y deshabilitadas de los periodos seleccionados.
        </p>

        @if ($escuelas === [])
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                No tiene escuelas activas asignadas. Solicite la importación de su alcance
                de dirección.
            </p>
        @else
            <form
                class="mt-6 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-4"
                method="GET"
                action="{{ route('investigacion-formativa.director.index') }}"
                aria-label="Filtros del resumen"
            >
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="filtro-escuela">
                        Escuela
                    </label>
                    <select
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="filtro-escuela"
                        name="escuela"
                    >
                        <option value="">Todas las escuelas</option>
                        @foreach ($escuelas as $escuela)
                            <option value="{{ $escuela->id_escuela }}" @selected(($filtros['escuela'] ?? null) === (int) $escuela->id_escuela)>
                                {{ $escuela->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700" for="filtro-periodo">
                        Periodo
                    </label>
                    <select
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="filtro-periodo"
                        name="periodo"
                    >
                        <option value="">Todos los periodos</option>
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo }}" @selected(($filtros['periodo'] ?? null) === $periodo)>
                                {{ $periodo }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        type="submit"
                    >
                        Filtrar
                    </button>
                </div>
            </form>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5" aria-label="Totales de las secciones filtradas">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">Secciones</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $totales->secciones }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">Habilitadas</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $totales->habilitadas }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">Grupos</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $totales->grupos }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">Avances actuales evaluados</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $totales->evaluaciones }}</dd>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <dt class="text-sm text-gray-600">Respuestas</dt>
                    <dd class="mt-1 text-2xl font-bold text-gray-900">{{ $totales->respuestas }}</dd>
                </div>
            </dl>

            <p class="mt-4 text-sm text-gray-600">La participación usa respuestas / participantes activos actuales.
                Puede superar el 100% después de retiros. Sin participantes activos, la tasa no se calcula.</p>
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-900">Secciones de su alcance</h2>
                <a
                    class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    href="{{ route('investigacion-formativa.director.cursos.index') }}"
                >
                    Ver cursos y habilitar secciones
                </a>
            </div>

            @if ($secciones === [])
                <p class="mt-4 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                    No hay secciones en el alcance seleccionado.
                </p>
            @else
                <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 bg-white text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Sección</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Curso</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Periodo</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Docente</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Estado</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700" scope="col">Respuestas</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-700" scope="col"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($secciones as $seccion)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $seccion->seccion }} · {{ $seccion->escuela }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $seccion->curso }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $seccion->periodo }}</td>
                                    <td class="px-4 py-3 text-gray-700">{{ $seccion->docente ?? 'Sin docente asignado' }}</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $seccion->habilitada ? 'Habilitada' : 'Deshabilitada' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ $seccion->respuestas_count }}
                                        @if ($seccion->roster_activo > 0)
                                            · {{ $seccion->participacion_porcentaje }}% de {{ $seccion->roster_activo }} activos
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a
                                            class="font-semibold text-indigo-700 hover:text-indigo-900"
                                            href="{{ route('investigacion-formativa.director.resultados', array_filter(['seccion' => $seccion->id_seccion, 'escuela' => $filtros['escuela'], 'periodo' => $filtros['periodo']])) }}"
                                        >
                                            Ver resultados
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </main>
</x-app-layout>
