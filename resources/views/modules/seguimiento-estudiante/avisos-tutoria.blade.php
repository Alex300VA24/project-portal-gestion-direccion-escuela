<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Avisos de Tutoría</h1>
    </x-slot>
    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        <ul class="divide-y rounded-lg bg-white px-5">
            @forelse ($bandeja as $aviso)
                <li class="py-4">
                    <p @class(['font-semibold' => $aviso->leido_en === null])>{{ $aviso->mensaje }}</p>
                    <p class="text-sm text-gray-700">{{ \Illuminate\Support\Carbon::parse($aviso->creado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}</p>
                    @if ($aviso->leido_en === null)
                        <form method="POST" action="{{ route('seguimiento-estudiante.avisos.leer', $aviso->id_aviso) }}" class="mt-1">
                            @csrf
                            <button type="submit" class="text-sm text-indigo-700 underline">Marcar como leído</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="py-4">Aún no tienes avisos de Tutoría.</li>
            @endforelse
        </ul>
        {{ $bandeja->links() }}
    </section>
</x-app-layout>
