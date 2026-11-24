<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Lotes de Secretaría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>

        @if ($ambitos->isNotEmpty())
            <form method="POST" enctype="multipart/form-data" action="{{ route('seguimiento-estudiante.secretaria.lotes.guardar') }}"
                  class="space-y-4 rounded-lg bg-white p-5">
                @csrf
                <h2 class="font-semibold text-gray-900">Validar y guardar una revisión</h2>
                <p>Usa la misma referencia de fuente, tipo, escuela y período para añadir una revisión al mismo lote.</p>
                <x-input-error :messages="$errors->all()" />
                <div>
                    <x-input-label for="ambito" value="Escuela y período" />
                    <select id="ambito" name="ambito" required class="rounded-md border-gray-300">
                        @foreach ($ambitos as $ambito)
                            @php($valorAmbito = $ambito->id_escuela.'-'.$ambito->id_periodo)
                            <option value="{{ $valorAmbito }}" @selected(old('ambito', $lote ? $lote->id_escuela.'-'.$lote->id_periodo : null) === $valorAmbito)>
                                {{ $ambito->escuela }} · {{ $ambito->periodo }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="tipo" value="Tipo de fuente" />
                    <select id="tipo" name="tipo" required class="rounded-md border-gray-300">
                        <option value="matriculas" @selected(old('tipo', $lote?->tipo) === 'matriculas')>Matrículas</option>
                        <option value="notas" @selected(old('tipo', $lote?->tipo) === 'notas')>Evaluaciones e historial</option>
                        <option value="asistencia" @selected(old('tipo', $lote?->tipo) === 'asistencia')>Asistencia</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="referencia" value="Referencia de origen del lote" />
                    <x-text-input id="referencia" name="referencia" class="w-full" required maxlength="150"
                                  :value="is_string(old('referencia')) ? old('referencia') : $lote?->referencia" />
                </div>
                <div>
                    <x-input-label for="fecha_corte" value="Fecha de corte" />
                    <x-text-input id="fecha_corte" name="fecha_corte" type="date" required
                                  :value="is_string(old('fecha_corte')) ? old('fecha_corte') : $revision?->fecha_corte" />
                </div>
                <div>
                    <x-input-label for="archivo" value="Archivo CSV de hasta 2 MB" />
                    <input id="archivo" name="archivo" type="file" accept=".csv,text/csv" required>
                </div>
                <x-primary-button>Validar y guardar borrador</x-primary-button>
            </form>
        @else
            <p class="rounded-lg bg-white p-5">Tu cuenta aún no tiene un ámbito asignado de Secretaría.</p>
        @endif

        @if ($lote && $revision)
            <article class="space-y-3 rounded-lg bg-white p-5">
                <h2 class="font-semibold text-gray-900">{{ $lote->referencia }} · Revisión {{ $revision->version }}</h2>
                <p>{{ $lote->escuela }} · {{ $lote->periodo }} · Corte {{ $revision->fecha_corte }}</p>
                <p>{{ $resultado['aceptadas'] }} de {{ $resultado['filas'] }} filas con referencias verificadas.</p>
                @if ($revision->confirmado_en)
                    <p>Validación confirmada para entrega. Aplicación institucional pendiente.</p>
                @else
                    <p>Borrador en Seguimiento. La aplicación institucional está pendiente.</p>
                @endif
                @if ($resultado['puedeConfirmar'])
                    <form method="POST" action="{{ route('seguimiento-estudiante.secretaria.lotes.confirmar', [$lote->id_lote, $revision->version]) }}">
                        @csrf
                        <x-primary-button>Confirmar validación</x-primary-button>
                    </form>
                @endif
                @if ($resultado['errores'])
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach (array_slice($resultado['errores'], 0, 50, true) as $fila => $mensajes)
                            <li>Fila {{ $fila }} · {{ implode(' ', $mensajes) }}</li>
                        @endforeach
                    </ul>
                    @if (count($resultado['errores']) > 50)
                        <p>Se muestran las primeras 50 de {{ count($resultado['errores']) }} filas con errores.</p>
                    @endif
                @endif
                <h3 class="font-medium">Historial de revisiones</h3>
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($versiones as $anterior)
                        <li>
                            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.secretaria.lotes.mostrar', [$lote->id_lote, $anterior->version]) }}">
                                Revisión {{ $anterior->version }} · Corte {{ $anterior->fecha_corte }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                {{ $versiones->links() }}
            </article>
        @endif

        <div class="space-y-3 rounded-lg bg-white p-5">
            <h2 class="font-semibold text-gray-900">Borradores disponibles</h2>
            <ul class="divide-y">
                @forelse ($lotes as $registro)
                    <li class="py-2">
                        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.secretaria.lotes.mostrar', $registro->id_lote) }}">
                            {{ $registro->referencia }} · {{ $registro->escuela }} · {{ $registro->periodo }}
                        </a>
                    </li>
                @empty
                    <li>Aún no hay lotes guardados en tu ámbito.</li>
                @endforelse
            </ul>
            {{ $lotes->links() }}
        </div>
    </section>
</x-app-layout>
