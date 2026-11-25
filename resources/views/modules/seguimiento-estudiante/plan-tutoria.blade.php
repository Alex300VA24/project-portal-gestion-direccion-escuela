@php
    $datosActividad = ['id', 'title', 'timing', 'mode', 'target', 'unit', 'evidence'];
    $filasAnteriores = old('activities', $plan['activities']);
    $filas = collect(is_array($filasAnteriores) ? array_slice($filasAnteriores, 0, 30) : [])
        ->filter(fn ($fila) => is_array($fila))
        ->map(fn ($fila) => collect($fila)->only($datosActividad)->filter(fn ($valor) => is_scalar($valor))->all())
        ->values()->all();
    $claves = array_map(fn () => (string) \Illuminate\Support\Str::uuid(), range(1, 30));
    $estados = ['SIN_GUARDAR' => 'Sin guardar', 'BORRADOR' => 'Borrador', 'PRESENTADO' => 'Presentado', 'OBSERVADO' => 'Con observaciones', 'RECIBIDO' => 'Revisión registrada'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Aporte al plan de tutoría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.planes.index') }}">Volver a mis aportes</a>
        <div class="rounded-lg bg-white p-5">
            <h2 class="font-semibold">{{ $ambito->escuela }} · {{ $ambito->periodo }}</h2>
            <p>{{ $estados[$plan['status']] }} · Versión {{ $plan['revision'] }}</p>
            @isset($plan['submitted_version'])
                <p>Última versión presentada {{ $plan['submitted_version'] }}</p>
                @if (isset($plan['review']) && $plan['review']['version'] === $plan['submitted_version'])
                    <p class="mt-2 whitespace-pre-wrap break-words">{{ $plan['review']['comment'] }}</p>
                @endif
            @endisset
        </div>
        @if (session('status'))
            <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />

        <form method="POST" action="{{ route('seguimiento-estudiante.planes.guardar', [$idEscuela, $idPeriodo]) }}"
              class="space-y-4 rounded-lg bg-white p-5"
              x-data="{ filas: @js($filas), claves: @js($claves) }">
            @csrf
            <input type="hidden" name="request_id" value="{{ $requestId }}">
            <input type="hidden" name="version" value="{{ $plan['revision'] }}">

            @foreach ($campos as $campo => $nombre)
                <div>
                    <x-input-label :for="$campo" :value="$nombre" />
                    <textarea id="{{ $campo }}" name="fields[{{ $campo }}]" maxlength="4000" rows="3"
                              class="block w-full rounded-md border-gray-300">{{ is_string(old('fields.'.$campo)) ? old('fields.'.$campo) : $plan['fields'][$campo] }}</textarea>
                </div>
            @endforeach

            <h3 class="font-semibold">Cronograma</h3>
            <p class="text-sm text-gray-700">Para presentar el aporte, completa las cinco secciones y registra al menos una actividad con nombre, fecha aproximada y meta.</p>
            <template x-for="(fila, indice) in filas" :key="fila.id">
                <fieldset class="space-y-3 rounded-md border p-4">
                    <legend x-text="'Actividad ' + (indice + 1)"></legend>
                    <input type="hidden" :name="'activities[' + indice + '][id]'" :value="fila.id">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label>Nombre
                            <input class="block w-full rounded-md border-gray-300" :name="'activities[' + indice + '][title]'"
                                   x-model="fila.title" maxlength="200">
                        </label>
                        <label>Mes o semana
                            <input class="block w-full rounded-md border-gray-300" :name="'activities[' + indice + '][timing]'"
                                   x-model="fila.timing" maxlength="100">
                        </label>
                        <label>Modalidad
                            <select class="block w-full rounded-md border-gray-300" :name="'activities[' + indice + '][mode]'" x-model="fila.mode">
                                <option value="INDIVIDUAL">Individual</option>
                                <option value="GRUPAL">Grupal</option>
                                <option value="GESTION">Gestión</option>
                            </select>
                        </label>
                        <label>Meta
                            <input type="number" min="1" max="10000" class="block w-full rounded-md border-gray-300"
                                   :name="'activities[' + indice + '][target]'" x-model="fila.target">
                        </label>
                        <label>Unidad de la meta
                            <select class="block w-full rounded-md border-gray-300" :name="'activities[' + indice + '][unit]'" x-model="fila.unit">
                                <option value="SESIONES">Sesiones</option>
                                <option value="ESTUDIANTES">Estudiantes</option>
                                <option value="ACTIVIDADES">Actividades</option>
                            </select>
                        </label>
                        <label>Evidencia esperada
                            <input class="block w-full rounded-md border-gray-300" :name="'activities[' + indice + '][evidence]'"
                                   x-model="fila.evidence" maxlength="500">
                        </label>
                    </div>
                    <button type="button" class="text-sm text-indigo-700 underline" x-on:click="filas.splice(indice, 1)">Quitar actividad</button>
                </fieldset>
            </template>
            <button type="button" :disabled="filas.length >= 30 || claves.length === 0"
                    class="text-indigo-700 underline disabled:text-gray-500"
                    x-on:click="filas.push({ id: claves.pop(), mode: 'INDIVIDUAL', unit: 'SESIONES' })">Añadir actividad</button>
            <div class="flex flex-wrap gap-3">
                <x-secondary-button type="submit" name="action" value="BORRADOR">Guardar borrador</x-secondary-button>
                <x-primary-button name="action" value="PRESENTAR">Presentar aporte</x-primary-button>
            </div>
        </form>
    </section>
</x-app-layout>
