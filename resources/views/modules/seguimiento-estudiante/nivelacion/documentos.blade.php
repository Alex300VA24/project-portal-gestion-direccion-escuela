<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Documentos de Nivelación</h1>
    </x-slot>
    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a expedientes</a>
        @if (session('status'))
            <p role="status" class="rounded-md bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
        @endif
        <x-input-error :messages="$errors->all()" />
        <ul class="divide-y rounded-lg bg-white px-5">
            @forelse ($programas as $programa)
                <li class="space-y-2 py-4">
                    <h2 class="font-medium">{{ $programa->nombre }} · {{ $programa->periodo }}</h2>
                    <div class="flex flex-wrap gap-4">
                        <a class="text-indigo-700 underline"
                           href="{{ route('seguimiento-estudiante.nivelacion.gestion', [$programa->id_programa, 'programa']) }}">Descargar programa PDF</a>
                        @if ($programa->coordina && $programa->informe_disponible)
                            <a class="text-indigo-700 underline"
                               href="{{ route('seguimiento-estudiante.nivelacion.gestion', [$programa->id_programa, 'informe']) }}">Descargar informe final PDF</a>
                        @elseif ($programa->coordina)
                            <span class="text-sm text-gray-700">Informe final pendiente de publicación.</span>
                        @endif
                    </div>
                    @if ($programa->materiales->isNotEmpty())
                        <h3 class="font-medium">Materiales del programa</h3>
                        <ul class="space-y-2">
                            @foreach ($programa->materiales as $material)
                                <li class="flex flex-wrap items-center gap-2 text-sm">
                                    <a class="text-indigo-700 underline"
                                       href="{{ route('seguimiento-estudiante.nivelacion.material-gestion', [$programa->id_programa, $material->id_archivo]) }}">
                                        {{ $material->titulo ?? 'Material de nivelación' }} (PDF)
                                    </a>
                                    <span>{{ $material->visible_desde ? 'Publicado' : 'Privado' }}</span>
                                    @can('seguimiento-estudiante.nivelacion.materiales.gestionar')
                                        <form method="POST" action="{{ route('seguimiento-estudiante.nivelacion.visibilidad-material', [$programa->id_programa, $material->id_archivo]) }}">
                                            @csrf
                                            <input type="hidden" name="visible" value="{{ $material->visible_desde ? 0 : 1 }}">
                                            <button type="submit" class="text-indigo-700 underline">{{ $material->visible_desde ? 'Ocultar a estudiantes' : 'Publicar' }}</button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($programa->coordina && auth()->user()->can('seguimiento-estudiante.nivelacion.materiales.gestionar'))
                        <details>
                            <summary class="cursor-pointer font-medium text-indigo-700">Adjuntar material PDF</summary>
                            <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-3"
                                  action="{{ route('seguimiento-estudiante.nivelacion.adjuntar', $programa->id_programa) }}">
                                @csrf
                                <div>
                                    <x-input-label for="titulo-{{ $programa->id_programa }}" value="Título" />
                                    <x-text-input id="titulo-{{ $programa->id_programa }}" name="titulo" required maxlength="150" />
                                </div>
                                <div>
                                    <x-input-label for="archivo-{{ $programa->id_programa }}" value="Archivo PDF de hasta 5 MB" />
                                    <input type="file" id="archivo-{{ $programa->id_programa }}" name="archivo" accept=".pdf,application/pdf" required>
                                </div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="visible" value="1">
                                    Publicar para estudiantes inscritos
                                </label>
                                <x-primary-button>Guardar material</x-primary-button>
                            </form>
                        </details>
                    @endif
                </li>
            @empty
                <li class="py-4">Sin programas publicados en tu ámbito.</li>
            @endforelse
        </ul>
        {{ $programas->links() }}
    </section>
</x-app-layout>
