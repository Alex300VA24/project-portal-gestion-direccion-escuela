<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Estudiante · Formar grupo
        </h2>
    </x-slot>

    <main class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Formar grupo en la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Formar grupo de investigación</h1>
        <p class="mt-2 text-sm text-gray-600">
            El grupo y sus integrantes quedan fijos al guardar. Tú serás el coordinador y el
            único integrante que podrá editar el título.
        </p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                <ul class="list-disc space-y-1 pl-5" role="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            class="mt-6 rounded-lg border border-gray-200 bg-white p-5"
            method="POST"
            action="{{ route('investigacion-formativa.alumno.grupos.store', $seccion) }}"
        >
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="grupo-nombre">Nombre del grupo</label>
                    <input
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="grupo-nombre"
                        maxlength="120"
                        name="nombre"
                        required
                        type="text"
                        value="{{ old('nombre') }}"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700" for="grupo-titulo">Título propuesto</label>
                    <input
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="grupo-titulo"
                        maxlength="250"
                        name="titulo"
                        required
                        type="text"
                        value="{{ old('titulo') }}"
                    >
                </div>
            </div>

            <fieldset class="mt-6">
                <legend class="text-sm font-semibold text-gray-900">Integrantes</legend>
                <p class="mt-1 text-xs text-gray-600">
                    Solo se listan estudiantes del padrón activo de la sección. El coordinador
                    queda incluido automáticamente.
                </p>

                <ul class="mt-3 grid gap-2 sm:grid-cols-2" role="list">
                    @foreach ($roster as $integrante)
                        @php($esCreador = $integrante->id_estudiante === $idEstudiante)
                        <li>
                            <label class="flex gap-3 rounded-md border border-gray-200 p-3 text-sm hover:border-indigo-300" for="integrante-{{ $integrante->id_estudiante }}">
                                <input
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    id="integrante-{{ $integrante->id_estudiante }}"
                                    name="integrante_ids[]"
                                    type="checkbox"
                                    value="{{ $integrante->id_estudiante }}"
                                    @checked($esCreador || in_array($integrante->id_estudiante, array_map('intval', (array) old('integrante_ids', []))))
                                    @disabled($esCreador)
                                >
                                <span>
                                    <span class="block font-medium text-gray-900">{{ $integrante->nombre }}</span>
                                    <span class="block text-xs text-gray-500">{{ $integrante->codigo }}</span>
                                    @if ($esCreador)
                                        <span class="mt-1 block text-xs font-semibold text-indigo-700">Coordinador</span>
                                        <input name="integrante_ids[]" type="hidden" value="{{ $integrante->id_estudiante }}">
                                    @endif
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </fieldset>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700" for="grupo-linea">Línea de investigación</label>
                    <select
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="grupo-linea"
                        name="id_linea"
                        required
                    >
                        <option value="">Selecciona una línea</option>
                        @foreach ($lineas as $linea)
                            <option value="{{ $linea->id_linea }}" @selected((int) old('id_linea') === $linea->id_linea)>
                                {{ $linea->codigo }} · {{ $linea->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700" for="grupo-tipo-producto">Tipo de producto</label>
                    <select
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        id="grupo-tipo-producto"
                        name="id_tipo_producto"
                        required
                    >
                        <option value="">Selecciona un tipo</option>
                        @foreach ($tiposProducto as $tipo)
                            <option value="{{ $tipo->id_tipo_producto }}" @selected((int) old('id_tipo_producto') === $tipo->id_tipo_producto)>
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($lineas === [] || $tiposProducto === [])
                <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    Falta cargar las líneas de investigación activas o los tipos de producto.
                    Solicita la configuración antes de crear el grupo.
                </p>
            @endif

            <div class="mt-6 flex items-center gap-3">
                <button
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-400"
                    type="submit"
                    @disabled($lineas === [] || $tiposProducto === [])
                >
                    Crear grupo
                </button>
                <a class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                    Cancelar
                </a>
            </div>
        </form>
    </main>
</x-app-layout>
