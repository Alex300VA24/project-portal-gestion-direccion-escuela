@php
    use App\Modules\GestionPracticas\Support\Catalogos;

    $editando = $convocatoria->exists;
    $campo = 'mt-1 block w-full rounded-[10px] border-[#bbc2ce] focus:border-[color:var(--portal-green)] focus:ring-[3px] focus:ring-[rgba(13,122,80,.15)]';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-['Fira_Sans'] font-bold text-xl text-[color:var(--portal-ink)] leading-tight">{{ $editando ? 'Corregir convocatoria' : 'Nueva convocatoria' }}</h2>
    </x-slot>

    <div class="py-8 min-h-[calc(100vh-8.75rem)] bg-[color:var(--portal-canvas)] font-['Fira_Sans']">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <form method="POST"
                  action="{{ $editando ? route('gestion-practicas.empresa.convocatorias.update', $convocatoria) : route('gestion-practicas.empresa.convocatorias.store') }}"
                  class="bg-[color:var(--portal-surface)] border border-[color:var(--portal-border)] rounded-[14px] p-6 grid gap-4 sm:grid-cols-2">
                @csrf
                @if ($editando) @method('PUT') @endif

                <div class="sm:col-span-2"><x-input-label for="titulo" value="Título" /><input id="titulo" name="titulo" value="{{ old('titulo', $convocatoria->titulo) }}" required class="{{ $campo }}"></div>
                <div class="sm:col-span-2"><x-input-label for="descripcion" value="Descripción" /><textarea id="descripcion" name="descripcion" rows="4" required class="{{ $campo }}">{{ old('descripcion', $convocatoria->descripcion) }}</textarea></div>
                <div class="sm:col-span-2"><x-input-label for="requisitos" value="Requisitos" /><textarea id="requisitos" name="requisitos" rows="4" required class="{{ $campo }}">{{ old('requisitos', $convocatoria->requisitos) }}</textarea></div>
                <div class="sm:col-span-2"><x-input-label for="enlace_postulacion" value="Enlace de postulación" /><input id="enlace_postulacion" name="enlace_postulacion" type="url" placeholder="https://" value="{{ old('enlace_postulacion', $convocatoria->enlace_postulacion) }}" required class="{{ $campo }}"></div>

                <div>
                    <x-input-label for="tipo_practica" value="Tipo de práctica" />
                    <select id="tipo_practica" name="tipo_practica" required class="{{ $campo }}">
                        @foreach (Catalogos::TIPOS_PRACTICA as $valor => $texto)
                            <option value="{{ $valor }}" @selected(old('tipo_practica', $convocatoria->tipo_practica) === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="modalidad" value="Modalidad" />
                    <select id="modalidad" name="modalidad" required class="{{ $campo }}">
                        @foreach (Catalogos::MODALIDADES as $valor => $texto)
                            <option value="{{ $valor }}" @selected(old('modalidad', $convocatoria->modalidad) === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div><x-input-label for="vacantes" value="Vacantes" /><input id="vacantes" name="vacantes" type="number" min="1" value="{{ old('vacantes', $convocatoria->vacantes ?? 1) }}" required class="{{ $campo }}"></div>
                <div><x-input-label for="fecha_cierre" value="Fecha de cierre" /><input id="fecha_cierre" name="fecha_cierre" type="date" value="{{ old('fecha_cierre', $convocatoria->fecha_cierre?->format('Y-m-d')) }}" required class="{{ $campo }}"></div>

                <div class="sm:col-span-2 flex items-center gap-3">
                    <x-primary-button>{{ $editando ? 'Guardar cambios' : 'Crear borrador' }}</x-primary-button>
                    <a href="{{ route('gestion-practicas.empresa.convocatorias.index') }}" class="text-sm text-[color:var(--portal-muted)] hover:underline">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
