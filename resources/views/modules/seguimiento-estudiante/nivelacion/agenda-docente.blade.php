<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold">Mis sesiones de Nivelación</h1>
    </x-slot>
    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a Seguimiento</a>
        <ul class="divide-y rounded-lg bg-white px-5">
            @forelse ($sesiones as $sesion)
                <li class="py-4">
                    <time datetime="{{ $sesion->fecha }}">{{ $sesion->fecha }}</time>
                    <strong>{{ $sesion->tema ?? 'Sesión de nivelación' }}</strong>
                    <p>{{ $sesion->programa }} · Grupo {{ $sesion->grupo }} · {{ $sesion->periodo }}</p>
                </li>
            @empty
                <li class="py-4">No tienes sesiones próximas en grupos publicados.</li>
            @endforelse
        </ul>
        {{ $sesiones->links() }}
    </section>
</x-app-layout>
