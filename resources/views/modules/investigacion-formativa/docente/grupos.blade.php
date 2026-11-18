@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Grupos de investigación')
@section('page-label', 'Grupos')

@section('content')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Grupos de la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Grupos de investigación</h1>
        <p class="mt-2 text-sm text-gray-600">
            Revisa el título propuesto por cada grupo de la sección. El estado del título solo
            cambia por una revisión registrada.
        </p>

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

        <form class="mt-6 flex flex-wrap items-end gap-3" method="GET" action="{{ route('investigacion-formativa.docente.grupos.index', $seccion) }}">
            <div>
                <label class="block text-sm font-medium text-gray-700" for="buscar-grupo">Buscar grupo o título</label>
                <input
                    class="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="buscar-grupo"
                    name="buscar"
                    type="search"
                    value="{{ $busqueda }}"
                >
            </div>
            <button
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                type="submit"
            >
                Buscar
            </button>
        </form>

        @if ($grupos->isEmpty())
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                No hay grupos que coincidan con la búsqueda.
            </p>
        @else
            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-3" scope="col">Grupo</th>
                            <th class="px-4 py-3" scope="col">Coordinador</th>
                            <th class="px-4 py-3" scope="col">Título propuesto</th>
                            <th class="px-4 py-3" scope="col">Estado</th>
                            <th class="px-4 py-3" scope="col">Avances</th>
                            <th class="px-4 py-3" scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($grupos as $grupo)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900">{{ $grupo->nombre }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $grupo->coordinador ?? 'Sin coordinador' }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $grupo->titulo ?? 'Sin título' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $grupo->estado ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $grupo->entregados }} de 3</td>
                                <td class="px-4 py-3">
                                    <a
                                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        href="{{ route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo->id_grupo]) }}"
                                    >
                                        Ver grupo
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $grupos->links() }}
            </div>
        @endif
@endsection
