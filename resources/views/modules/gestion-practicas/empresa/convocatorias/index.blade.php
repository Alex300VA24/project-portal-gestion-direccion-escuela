<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Mis convocatorias</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-600">Cree una convocatoria y envíela a revisión del administrador para que se publique.</p>
                    <a href="{{ route('gestion-practicas.empresa.convocatorias.create') }}" class="px-4 py-2 bg-emerald-700 text-white rounded-md text-sm font-semibold hover:bg-emerald-800">Nueva convocatoria</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Título</th>
                                <th class="py-2 pe-4 font-medium">Cierre</th>
                                <th class="py-2 pe-4 font-medium">Vacantes</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($convocatorias as $convocatoria)
                                <tr>
                                    <td class="py-2 pe-4">{{ $convocatoria->titulo }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->vacantes }}</td>
                                    <td class="py-2 pe-4">
                                        @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
                                        @if ($convocatoria->observaciones_pendientes > 0)
                                            <span class="ms-1 text-xs text-red-700">{{ $convocatoria->observaciones_pendientes }} observación(es)</span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-end"><a class="text-emerald-700 hover:underline" href="{{ route('gestion-practicas.empresa.convocatorias.show', $convocatoria) }}">Ver</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">Aún no ha creado convocatorias.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
