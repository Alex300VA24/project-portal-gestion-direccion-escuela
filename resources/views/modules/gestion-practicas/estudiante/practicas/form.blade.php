@php
    $editando = $practica->exists;
    $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">{{ $editando ? 'Corregir datos de la práctica' : 'Registrar mi práctica' }}</h2>
    </x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            @if ($convocatoria)
                <div class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-4 text-sm text-[color:var(--portal-muted)]">
                    Convocatoria: <strong>{{ $convocatoria->titulo }}</strong>
                </div>
            @endif

            <form method="POST" enctype="multipart/form-data"
                  action="{{ $editando ? route('gestion-practicas.estudiante.practicas.update', $practica) : route('gestion-practicas.estudiante.practicas.store', $postulacion) }}"
                  class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($editando) @method('PUT') @endif

                <div class="sm:col-span-2"><x-input-label for="area" value="Área de la práctica (software, redes, seguridad…)" /><input id="area" name="area" value="{{ old('area', $practica->area) }}" required class="{{ $campo }}"></div>
                <div class="sm:col-span-2"><x-input-label for="funciones" value="Funciones a desarrollar" /><textarea id="funciones" name="funciones" rows="4" required class="{{ $campo }}">{{ old('funciones', $practica->funciones) }}</textarea></div>
                <div><x-input-label for="fecha_inicio" value="Fecha de inicio" /><input id="fecha_inicio" name="fecha_inicio" type="date" value="{{ old('fecha_inicio', $practica->fecha_inicio?->format('Y-m-d')) }}" required class="{{ $campo }}"></div>
                <div><x-input-label for="fecha_fin" value="Fecha de fin" /><input id="fecha_fin" name="fecha_fin" type="date" value="{{ old('fecha_fin', $practica->fecha_fin?->format('Y-m-d')) }}" required class="{{ $campo }}"></div>
                <div><x-input-label for="horas_totales" value="Horas planificadas" /><input id="horas_totales" name="horas_totales" type="number" min="1" value="{{ old('horas_totales', $practica->horas_totales) }}" required class="{{ $campo }}"></div>
                <div>
                    <x-input-label for="id_periodo" value="Periodo académico" />
                    <select id="id_periodo" name="id_periodo" class="{{ $campo }}">
                        <option value="">—</option>
                        @foreach ($periodos as $id => $codigo)
                            <option value="{{ $id }}" @selected((string) old('id_periodo', $practica->id_periodo) === (string) $id)>{{ $codigo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="carta" value="{{ $editando ? 'Reemplazar carta de aceptación (opcional)' : 'Carta de aceptación (PDF o imagen, máx. 5 MB)' }}" />
                    <input id="carta" name="carta" type="file" accept=".pdf,.jpg,.jpeg,.png" @required(! $editando) class="mt-1 block w-full text-sm">
                </div>

                <div class="sm:col-span-2 flex items-center gap-3">
                    <x-primary-button>{{ $editando ? 'Guardar cambios' : 'Registrar práctica' }}</x-primary-button>
                    <a href="{{ route('gestion-practicas.estudiante.practicas.index') }}" class="text-sm text-[color:var(--portal-muted)] hover:underline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
