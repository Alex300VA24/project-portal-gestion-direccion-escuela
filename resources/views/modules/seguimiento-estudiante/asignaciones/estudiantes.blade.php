<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Asignaciones de tutoría</h1>
    </x-slot>
    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.asignaciones.index') }}">Volver a escuelas y períodos</a>
        <h2 class="font-semibold">{{ $ambito->escuela }} · {{ $ambito->periodo }}</h2>
        @if (session('status'))
            <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />
        @if ($tutores->isEmpty())
            <p class="rounded-lg bg-white p-5">Todavía no hay docentes habilitados para esta escuela y período.</p>
        @endif
        <ul class="divide-y rounded-lg bg-white px-5">
            @forelse ($estudiantes as $estudiante)
                <li class="py-4">
                    <p class="font-medium">{{ $estudiante->nombres }} {{ $estudiante->apellido_paterno }} {{ $estudiante->apellido_materno }}</p>
                    <p>{{ $estudiante->codigo_universitario }} · {{ $estudiante->codigo_docente ? 'Tutor '.$estudiante->codigo_docente : 'Sin tutor asignado' }}</p>
                    @if ($tutores->isNotEmpty())
                        <details class="mt-2" @if ($errors->any() && old('id_estudiante') === (string) $estudiante->id_estudiante) open @endif>
                            <summary class="cursor-pointer font-medium text-indigo-700">{{ $estudiante->id_asignacion ? 'Reasignar tutor' : 'Asignar tutor' }}</summary>
                            <form method="POST" action="{{ route('seguimiento-estudiante.asignaciones.guardar', [$idEscuela, $idPeriodo]) }}" class="mt-3 space-y-3">
                                @csrf
                                <input type="hidden" name="id_estudiante" value="{{ $estudiante->id_estudiante }}">
                                <input type="hidden" name="id_asignacion" value="{{ $estudiante->id_asignacion ?? 0 }}">
                                <div>
                                    <x-input-label for="tutor_{{ $estudiante->id_estudiante }}" value="Docente habilitado" />
                                    <select id="tutor_{{ $estudiante->id_estudiante }}" name="id_docente" required class="rounded-md border-gray-300">
                                        <option value="">Selecciona un docente</option>
                                        @foreach ($tutores as $tutor)
                                            <option value="{{ $tutor->id_docente }}" @disabled($tutor->codigo_docente === $estudiante->codigo_docente)>
                                                {{ $tutor->apellido_paterno }} {{ $tutor->apellido_materno }}, {{ $tutor->nombres }} · {{ $tutor->codigo_docente }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="motivo_{{ $estudiante->id_estudiante }}" value="Motivo" />
                                    <textarea id="motivo_{{ $estudiante->id_estudiante }}" name="motivo" required minlength="5" maxlength="1000"
                                              class="block w-full rounded-md border-gray-300">{{ old('id_estudiante') === (string) $estudiante->id_estudiante && is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                                </div>
                                <x-primary-button>Guardar asignación</x-primary-button>
                            </form>
                        </details>
                    @endif
                </li>
            @empty
                <li class="py-4">Esta escuela no tiene estudiantes activos registrados.</li>
            @endforelse
        </ul>
        {{ $estudiantes->links() }}
    </section>
</x-app-layout>
