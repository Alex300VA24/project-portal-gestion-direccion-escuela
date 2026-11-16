<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Gestión de Prácticas</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            @unless ($vinculada)
                <div class="rounded-md bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 text-sm">
                    Su cuenta de acceso ({{ $actor->user->email }}) aún no está vinculada a un usuario institucional.
                    Pida al administrador que registre ese mismo correo para poder operar en el módulo.
                </div>
            @endunless

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900">Hola, {{ $actor->user->name }}</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Rol: {{ $actor->user->getRoleNames()->map(fn ($r) => str_replace('practicas_', '', $r))->implode(', ') ?: 'sin rol asignado' }}
                </p>
            </div>

            @if ($resumen !== [])
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($resumen as $titulo => $valor)
                        <div class="bg-white shadow-sm sm:rounded-lg p-6">
                            <p class="text-sm text-gray-500">{{ $titulo }}</p>
                            <p class="mt-1 text-3xl font-bold text-gray-900">{{ $valor }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm text-gray-600">
                    Su usuario no tiene un rol de prácticas asignado. Contacte al administrador del portal.
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
