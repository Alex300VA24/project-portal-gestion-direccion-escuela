@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Convocatorias de prácticas</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($convocatorias as $convocatoria)
                    <article class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-col">
                        <p class="text-sm text-gray-500">{{ $empresas[$convocatoria->id_empresa] ?? '—' }}</p>
                        <h3 class="text-lg font-semibold text-gray-900 mt-1">{{ $convocatoria->titulo }}</h3>
                        <p class="text-sm text-gray-600 mt-2 line-clamp-3">{{ $convocatoria->descripcion }}</p>
                        <p class="text-xs text-gray-500 mt-3">
                            {{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }} ·
                            {{ $convocatoria->vacantes }} vacante(s) · cierra el {{ $convocatoria->fecha_cierre->format('d/m/Y') }}
                        </p>
                        <div class="mt-4 flex items-center gap-3">
                            <a href="{{ route('gestion-practicas.estudiante.convocatorias.show', $convocatoria) }}" class="px-4 py-2 bg-emerald-700 text-white rounded-md text-sm font-semibold hover:bg-emerald-800">Ver detalles</a>
                            @if (in_array((int) $convocatoria->id_convocatoria, $yaPostulo, true))
                                <span class="text-xs font-semibold text-emerald-700">Ya postuló</span>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="md:col-span-2 bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">No hay convocatorias vigentes por ahora.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
