<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dirección de escuela
        </h2>
    </x-slot>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <h1 class="text-2xl font-bold text-gray-900">Dirección de escuela</h1>
        <p class="mt-2 text-sm text-gray-600">
            Escuelas asignadas a tu cuenta. La habilitación de cursos y los reportes se
            limitan a este alcance.
        </p>

        @if ($escuelas === [])
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                No tienes escuelas activas asignadas. Solicita la importación de tu alcance
                de dirección.
            </p>
        @else
            <p class="mt-6">
                <a
                    class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    href="{{ route('investigacion-formativa.director.cursos.index') }}"
                >
                    Ver cursos y habilitar secciones
                </a>
            </p>

            <ul class="mt-6 grid gap-3 sm:grid-cols-2" role="list">
                @foreach ($escuelas as $escuela)
                    <li class="rounded-lg border border-gray-200 bg-white p-4">
                        <strong class="text-gray-900">{{ $escuela->nombre }}</strong>
                    </li>
                @endforeach
            </ul>
        @endif
    </main>
</x-app-layout>
