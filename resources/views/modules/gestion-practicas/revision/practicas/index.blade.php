<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Validar prácticas</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" class="flex items-center gap-3 mb-4 text-sm">
                    <label for="estado" class="text-gray-600">Estado</label>
                    <select id="estado" name="estado" onchange="this.form.submit()" class="rounded-md border-gray-300 text-sm">
                        @foreach (['en_revision' => 'En revisión', 'observada' => 'Observadas', 'en_curso' => 'En curso', 'finalizada' => 'Finalizadas', 'todas' => 'Todas'] as $valor => $texto)
                            <option value="{{ $valor }}" @selected($estado === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pe-4 font-medium">Estudiante</th>
                                <th class="py-2 pe-4 font-medium">Empresa</th>
                                <th class="py-2 pe-4 font-medium">Área</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($practicas as $practica)
                                <tr>
                                    <td class="py-2 pe-4">{{ $estudiantes[$practica->id_estudiante] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $empresas[$practica->id_empresa] ?? '—' }}</td>
                                    <td class="py-2 pe-4">{{ $practica->area }}</td>
                                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])</td>
                                    <td class="py-2 text-end"><a class="text-emerald-700 hover:underline" href="{{ route('gestion-practicas.revision.practicas.show', $practica) }}">Revisar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">No hay prácticas en este estado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
