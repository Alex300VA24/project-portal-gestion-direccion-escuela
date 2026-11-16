<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Revisar convocatorias</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" class="flex items-center gap-3 mb-4 text-sm">
                    <label for="estado" class="text-gray-600">Estado</label>
                    <select id="estado" name="estado" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                        @foreach (['en_revision' => 'En revisión', 'observada' => 'Observadas', 'publicada' => 'Publicadas', 'cerrada' => 'Cerradas', 'todas' => 'Todas'] as $valor => $texto)
                            <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Empresa</th>
                                <th class="py-2 pe-4 font-medium">Título</th>
                                <th class="py-2 pe-4 font-medium">Cierre</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($convocatorias as $convocatoria)
                                <tr>
                                    <td class="py-2 pe-4">{{ $empresas[$convocatoria->id_empresa] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->titulo }}</td>
                                    <td class="py-2 pe-4">{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</td>
                                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])</td>
                                    <td class="py-2 text-end"><a class="text-emerald-700 hover:underline" href="{{ route('gestion-practicas.revision.convocatorias.show', $convocatoria) }}">Revisar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">No hay convocatorias en este estado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
