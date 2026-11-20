<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Causas de finalización de Tutoría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.asignaciones.mostrar', [$idEscuela, $idPeriodo]) }}">Volver a las asignaciones</a>
        <h2 class="font-semibold">{{ $ambito->escuela }} · {{ $ambito->periodo }}</h2>
        @if (session('status'))
            <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />

        <form method="POST" action="{{ route('seguimiento-estudiante.asignaciones.causas.guardar', [$idEscuela, $idPeriodo]) }}" class="space-y-4 rounded-lg bg-white p-5">
            @csrf
            <input type="hidden" name="revision" value="{{ $catalogo['revision'] }}">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    Referencia de aprobación
                    <input name="referencia" required minlength="5" maxlength="250" class="block w-full rounded-md border-gray-300">
                </label>
                <label class="block text-sm">
                    Autoridad que aprobó
                    <input name="autoridad" maxlength="150" class="block w-full rounded-md border-gray-300">
                </label>
                <label class="block text-sm">
                    Fecha de aprobación
                    <input type="date" name="fecha_aprobacion" class="block rounded-md border-gray-300">
                </label>
                <label class="block text-sm">
                    Motivo del cambio
                    <input name="motivo" required minlength="10" maxlength="1000" class="block w-full rounded-md border-gray-300">
                </label>
            </div>

            @foreach ($filas as $i => $fila)
                <fieldset class="grid gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-2">
                    <legend class="px-1 text-sm font-medium">{{ $fila['codigo'] ?: 'Nueva causa' }}</legend>
                    <label class="block text-sm">
                        Código
                        <input name="causas[{{ $i }}][codigo]" value="{{ $fila['codigo'] }}" maxlength="40" pattern="[A-Z][A-Z0-9_]{1,39}" class="block w-full rounded-md border-gray-300">
                    </label>
                    <label class="block text-sm">
                        Nombre
                        <input name="causas[{{ $i }}][nombre]" value="{{ $fila['nombre'] }}" maxlength="150" class="block w-full rounded-md border-gray-300">
                    </label>
                    <label class="block text-sm">
                        Acción
                        <select name="causas[{{ $i }}][efecto]" class="block rounded-md border-gray-300">
                            <option value="FINALIZAR" @selected($fila['efecto'] === 'FINALIZAR')>Finalizar sin relevo</option>
                            <option value="REASIGNAR" @selected($fila['efecto'] === 'REASIGNAR')>Exigir reemplazo</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <input type="hidden" name="causas[{{ $i }}][habilitada]" value="0">
                        <input type="checkbox" name="causas[{{ $i }}][habilitada]" value="1" @checked($fila['habilitada']) class="rounded border-gray-300">
                        Habilitada
                    </label>
                </fieldset>
            @endforeach

            <label class="block text-sm">
                <input type="checkbox" name="aprobado" value="1" class="rounded border-gray-300">
                Confirmo la aprobación institucional de esta versión.
            </label>
            <div class="flex flex-wrap gap-3">
                <x-secondary-button type="submit" name="estado" value="BORRADOR">Guardar borrador</x-secondary-button>
                <x-primary-button name="estado" value="VIGENTE">Publicar versión vigente</x-primary-button>
            </div>
        </form>

        <div class="space-y-2 rounded-lg bg-white p-5">
            <h3 class="font-semibold">Historial del catálogo</h3>
            <ul class="divide-y divide-gray-200">
                @forelse ($versiones as $version)
                    <li class="py-2 text-sm">
                        Versión {{ $version['revision'] }} · {{ $version['referencia'] }} ·
                        {{ $version['version'] === $catalogo['activa'] ? 'Vigente' : ($version['estado'] === 'BORRADOR' ? 'Borrador' : 'Sustituida') }}
                    </li>
                @empty
                    <li class="py-2 text-sm">Todavía no hay versiones registradas.</li>
                @endforelse
            </ul>
        </div>
    </section>
</x-app-layout>
