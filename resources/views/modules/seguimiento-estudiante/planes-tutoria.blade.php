<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Mis aportes al plan de tutoría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        <ul class="divide-y rounded-lg bg-white px-5">
            @forelse ($ambitos as $ambito)
                <li class="py-4">
                    <a class="font-medium text-indigo-700 underline"
                       href="{{ route('seguimiento-estudiante.planes.mostrar', [$ambito->id_escuela, $ambito->id_periodo]) }}">
                        {{ $ambito->escuela }} · {{ $ambito->periodo }}
                    </a>
                </li>
            @empty
                <li class="py-4">Aún no tienes asignaciones vigentes de tutoría para un período activo.</li>
            @endforelse
        </ul>
    </section>
</x-app-layout>
