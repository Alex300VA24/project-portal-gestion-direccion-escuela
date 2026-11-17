<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dirección de escuela · Cursos
        </h2>
    </x-slot>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Cursos de mis escuelas</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Habilita las secciones que ya tienen la configuración completa. El alcance
                    se limita a las escuelas asignadas a tu cuenta.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
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

        <form
            class="mt-6 grid gap-4 rounded-lg border border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-4"
            method="GET"
            action="{{ route('investigacion-formativa.director.cursos.index') }}"
        >
            <div>
                <label class="block text-sm font-medium text-gray-700" for="filtro-escuela">Escuela</label>
                <select
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="filtro-escuela"
                    name="escuela"
                >
                    <option value="">Todas mis escuelas</option>
                    @foreach ($escuelas as $escuela)
                        <option value="{{ $escuela->id_escuela }}" @selected($filtros['escuela'] === (int) $escuela->id_escuela)>
                            {{ $escuela->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="filtro-periodo">Periodo</label>
                <select
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="filtro-periodo"
                    name="periodo"
                >
                    <option value="">Todos</option>
                    @foreach ($periodos as $periodo)
                        <option value="{{ $periodo }}" @selected($filtros['periodo'] === $periodo)>
                            {{ $periodo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700" for="filtro-buscar">Buscar curso o sección</label>
                <input
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="filtro-buscar"
                    maxlength="100"
                    name="buscar"
                    type="search"
                    value="{{ $filtros['buscar'] }}"
                >
            </div>

            <div class="flex items-end gap-2">
                <button
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    type="submit"
                >
                    Filtrar
                </button>
                <a
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    href="{{ route('investigacion-formativa.director.cursos.index') }}"
                >
                    Limpiar
                </a>
            </div>
        </form>

        @if ($secciones->isEmpty())
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                No hay secciones que coincidan con los filtros en tus escuelas asignadas.
            </p>
        @else
            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-3" scope="col">Periodo</th>
                            <th class="px-4 py-3" scope="col">Escuela</th>
                            <th class="px-4 py-3" scope="col">Curso</th>
                            <th class="px-4 py-3" scope="col">Sección</th>
                            <th class="px-4 py-3" scope="col">Docente</th>
                            <th class="px-4 py-3" scope="col">Configuración</th>
                            <th class="px-4 py-3" scope="col">Encuesta</th>
                            <th class="px-4 py-3" scope="col">Habilitación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $seccion->periodo }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $seccion->escuela }}</td>
                                <td class="px-4 py-3 text-gray-900">
                                    {{ $seccion->curso_codigo }} · {{ $seccion->curso }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $seccion->seccion }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $seccion->docente ?? 'Sin docente asignado' }}</td>
                                <td class="px-4 py-3">
                                    @if ($seccion->configurada)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                                            Completa
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                            Incompleta
                                        </span>
                                        <p class="mt-1 max-w-xs text-xs text-amber-900">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a
                                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        href="{{ route('investigacion-formativa.director.encuestas.edit', $seccion->id_seccion) }}"
                                    >
                                        Gestionar encuesta
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2">
                                        @if ($seccion->habilitada)
                                            <span class="w-fit rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                                                Habilitada
                                            </span>
                                        @else
                                            <span class="w-fit rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
                                                Deshabilitada
                                            </span>
                                        @endif

                                        <form
                                            method="POST"
                                            action="{{ route('investigacion-formativa.director.secciones.habilitacion', $seccion->id_seccion) }}"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="habilitada" value="{{ $seccion->habilitada ? 0 : 1 }}">
                                            <input type="hidden" name="version" value="{{ $seccion->version_configuracion }}">
                                            <input type="hidden" name="escuela" value="{{ $filtros['escuela'] }}">
                                            <input type="hidden" name="buscar" value="{{ $filtros['buscar'] }}">
                                            <input type="hidden" name="periodo" value="{{ $filtros['periodo'] }}">
                                            <button
                                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                                type="submit"
                                            >
                                                {{ $seccion->habilitada ? 'Deshabilitar' : 'Habilitar' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $secciones->links() }}
            </div>
        @endif
    </main>
</x-app-layout>
