<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Docencia · Investigación Formativa
        </h2>
    </x-slot>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <h1 class="text-2xl font-bold text-gray-900">Mis cursos asignados</h1>
        <p class="mt-2 text-sm text-gray-600">
            Secciones activas donde figuras como docente responsable. Configura competencias,
            rúbrica y cronograma antes de habilitar el trabajo con estudiantes.
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($secciones === [])
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                No tienes secciones activas asignadas en este momento.
            </p>
        @else
            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-3" scope="col">Periodo</th>
                            <th class="px-4 py-3" scope="col">Curso</th>
                            <th class="px-4 py-3" scope="col">Sección</th>
                            <th class="px-4 py-3" scope="col">Configuración</th>
                            <th class="px-4 py-3" scope="col">Pasos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $seccion->periodo }}</td>
                                <td class="px-4 py-3 text-gray-900">
                                    {{ $seccion->curso_codigo }} · {{ $seccion->curso }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $seccion->seccion }}</td>
                                <td class="px-4 py-3">
                                    @if ($seccion->habilitada)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                                            Habilitada
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                            Pendiente de habilitación
                                        </span>
                                    @endif

                                    @if ($seccion->configurada)
                                        <p class="mt-1 text-xs font-semibold text-emerald-800">
                                            Configuración completa
                                        </p>
                                    @else
                                        <p class="mt-1 text-xs text-amber-900">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <ul class="flex flex-wrap gap-2 text-xs font-semibold" role="list">
                                        <li>
                                            <a
                                                class="rounded-md border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50"
                                                href="{{ route('investigacion-formativa.docente.competencias.edit', $seccion->id_seccion) }}"
                                            >
                                                Competencias
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="rounded-md border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50"
                                                href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}"
                                            >
                                                Rúbrica
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="rounded-md border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50"
                                                href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}"
                                            >
                                                Cronograma
                                            </a>
                                        </li>
                                        <li>
                                            <a
                                                class="rounded-md border border-gray-300 px-2 py-1 text-gray-700 hover:bg-gray-50"
                                                href="{{ route('investigacion-formativa.docente.grupos.index', $seccion->id_seccion) }}"
                                            >
                                                Grupos
                                            </a>
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </main>
</x-app-layout>
