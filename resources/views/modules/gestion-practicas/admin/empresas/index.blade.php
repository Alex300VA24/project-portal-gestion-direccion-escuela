<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Empresas</h2></x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-600">Empresas con cuenta de acceso al módulo.</p>
                    <a href="{{ route('gestion-practicas.empresas.create') }}" class="px-4 py-2 bg-emerald-700 text-white rounded-md text-sm font-semibold hover:bg-emerald-800">Registrar empresa</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pe-4 font-medium">RUC</th>
                                <th class="py-2 pe-4 font-medium">Razón social</th>
                                <th class="py-2 pe-4 font-medium">Cuenta de acceso</th>
                                <th class="py-2 pe-4 font-medium">Estado</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($empresas as $empresa)
                                <tr>
                                    <td class="py-2 pe-4">{{ $empresa->ruc }}</td>
                                    <td class="py-2 pe-4">{{ $empresa->razon_social }}</td>
                                    <td class="py-2 pe-4 text-gray-600">{{ $empresa->cuenta ?? '—' }}</td>
                                    <td class="py-2 pe-4">@include('modules.gestion-practicas.partials.estado', ['estado' => $empresa->estado])</td>
                                    <td class="py-2 text-end">
                                        <form method="POST" action="{{ route('gestion-practicas.empresas.estado', $empresa->id_empresa) }}">
                                            @csrf
                                            <button class="text-sm text-emerald-700 hover:underline">
                                                {{ strtolower($empresa->estado) === 'activo' ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-gray-500">Todavía no hay empresas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
