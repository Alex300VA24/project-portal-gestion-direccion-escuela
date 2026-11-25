<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Prevalidación de CSV de Secretaría</h1>
    </x-slot>

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        <nav aria-label="Plantillas CSV" class="space-y-2 rounded-lg bg-white p-5">
            <h2 class="font-semibold">Descargar plantilla CSV</h2>
            @foreach (['matriculas' => 'Matrículas', 'notas' => 'Notas', 'asistencia' => 'Asistencia'] as $tipo => $nombre)
                <a class="mr-4 text-indigo-700 underline" href="{{ route('seguimiento-estudiante.secretaria.plantilla', $tipo) }}">{{ $nombre }}</a>
            @endforeach
        </nav>
        <form method="POST" enctype="multipart/form-data" action="{{ route('seguimiento-estudiante.secretaria.prevalidar') }}"
              class="space-y-4 rounded-lg bg-white p-5">
            @csrf
            <p>Comprueba columnas, formatos y duplicados dentro del archivo. La prevalidación deja el CSV y sus filas fuera de la base de datos.</p>
            <div>
                <x-input-label for="tipo" value="Tipo de fuente" />
                <select id="tipo" name="tipo" required class="rounded-md border-gray-300">
                    <option value="matriculas" @selected(old('tipo') === 'matriculas')>Matrículas</option>
                    <option value="notas" @selected(old('tipo') === 'notas')>Evaluaciones e historial</option>
                    <option value="asistencia" @selected(old('tipo') === 'asistencia')>Asistencia</option>
                </select>
                <x-input-error :messages="$errors->get('tipo')" />
            </div>
            <div>
                <x-input-label for="archivo" value="Archivo CSV de hasta 2 MB" />
                <input id="archivo" name="archivo" type="file" accept=".csv,text/csv" required>
                <x-input-error :messages="$errors->get('archivo')" />
            </div>
            <x-primary-button>Prevalidar archivo</x-primary-button>
        </form>

        @isset($resultado)
            <div role="status" class="space-y-2 rounded-lg bg-white p-5">
                <h2 class="font-semibold text-gray-900">Resultado de la prevalidación</h2>
                <p>{{ $resultado['filas'] }} filas revisadas · {{ count($resultado['errores']) }} filas con errores</p>
                @if ($resultado['errores'])
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach (array_slice($resultado['errores'], 0, 50, true) as $fila => $mensajes)
                            <li>Fila {{ $fila }} · {{ implode(' ', $mensajes) }}</li>
                        @endforeach
                    </ul>
                    @if (count($resultado['errores']) > 50)
                        <p>Se muestran las primeras 50 filas con errores.</p>
                    @endif
                @else
                    <p>El archivo pasó la revisión de formato. La comprobación de referencias institucionales queda pendiente.</p>
                @endif
            </div>
        @endisset
    </section>
</x-app-layout>
