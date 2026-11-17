<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Docencia · Rúbrica
        </h2>
    </x-slot>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de configuración">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Rúbrica de la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Rúbrica de avances</h1>
        <p class="mt-2 text-sm text-gray-600">
            Cada criterio debe tener cuatro niveles con su descripción y puntaje. El puntaje
            máximo del criterio se calcula con el mayor puntaje de sus niveles.
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-conflicto>
                {{ $conflicto }} Recarga la página antes de volver a intentar.
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">
                <ul class="list-disc space-y-1 pl-5" role="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6">
            @include('modules.investigacion-formativa.partials.estado-configuracion', ['estado' => $estado])
        </div>

        @if ($congelada)
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                La rúbrica está congelada porque ya existen entregas en esta sección. No se
                puede modificar.
            </p>
        @endif

        <form
            class="mt-6 rounded-lg border border-gray-200 bg-white p-5"
            method="POST"
            action="{{ route('investigacion-formativa.docente.rubrica.update', $seccion->id_seccion) }}"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

            <div>
                <label class="block text-sm font-medium text-gray-700" for="rubrica-nombre">Nombre de la rúbrica</label>
                <input
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="rubrica-nombre"
                    maxlength="150"
                    name="nombre"
                    required
                    type="text"
                    value="{{ old('nombre', $rubrica?->nombre ?? 'Rúbrica de avances') }}"
                >
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700" for="rubrica-descripcion">Descripción (opcional)</label>
                <textarea
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="rubrica-descripcion"
                    maxlength="4000"
                    name="descripcion"
                    rows="2"
                >{{ old('descripcion', $rubrica?->descripcion ?? '') }}</textarea>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Criterios</h2>
                <button
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    id="agregar-criterio"
                    type="button"
                >
                    Agregar criterio
                </button>
            </div>

            <div class="mt-3 space-y-5" id="criterios">
                @foreach ($criterios as $indice => $criterio)
                    <fieldset class="rounded-md border border-gray-200 p-4" data-criterio="{{ $indice }}">
                        <legend class="px-1 text-sm font-semibold text-gray-900">Criterio {{ $indice + 1 }}</legend>

                        <label class="block text-sm font-medium text-gray-700" for="criterio-{{ $indice }}-descripcion">
                            Descripción del criterio
                        </label>
                        <textarea
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            id="criterio-{{ $indice }}-descripcion"
                            maxlength="2000"
                            name="criterios[{{ $indice }}][descripcion]"
                            required
                            rows="2"
                        >{{ $criterio['descripcion'] }}</textarea>

                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @foreach ($criterio['niveles'] as $posicion => $nivel)
                                <div class="rounded-md bg-gray-50 p-3">
                                    <input
                                        name="criterios[{{ $indice }}][niveles][{{ $posicion }}][nivel]"
                                        type="hidden"
                                        value="{{ $nivel['nivel'] }}"
                                    >

                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600" for="nivel-{{ $indice }}-{{ $posicion }}-descripcion">
                                        Nivel {{ $nivel['nivel'] }} · Descripción
                                    </label>
                                    <textarea
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        id="nivel-{{ $indice }}-{{ $posicion }}-descripcion"
                                        maxlength="2000"
                                        name="criterios[{{ $indice }}][niveles][{{ $posicion }}][descripcion]"
                                        required
                                        rows="2"
                                    >{{ $nivel['descripcion'] }}</textarea>

                                    <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-gray-600" for="nivel-{{ $indice }}-{{ $posicion }}-puntaje">
                                        Puntaje
                                    </label>
                                    <input
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        id="nivel-{{ $indice }}-{{ $posicion }}-puntaje"
                                        max="9999.99"
                                        min="0"
                                        name="criterios[{{ $indice }}][niveles][{{ $posicion }}][puntaje]"
                                        required
                                        step="0.01"
                                        type="number"
                                        value="{{ $nivel['puntaje'] }}"
                                    >
                                </div>
                            @endforeach
                        </div>

                        <button
                            class="mt-3 rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                            data-quitar-criterio
                            type="button"
                        >
                            Quitar criterio
                        </button>
                    </fieldset>
                @endforeach
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-400"
                    type="submit"
                    @disabled($congelada)
                >
                    Guardar rúbrica
                </button>
                <a class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}">
                    Continuar con el cronograma
                </a>
            </div>
        </form>

        <template id="plantilla-criterio">
            <fieldset class="rounded-md border border-gray-200 p-4" data-criterio="__INDICE__">
                <legend class="px-1 text-sm font-semibold text-gray-900">Criterio adicional</legend>

                <label class="block text-sm font-medium text-gray-700" for="criterio-__INDICE__-descripcion">
                    Descripción del criterio
                </label>
                <textarea
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    id="criterio-__INDICE__-descripcion"
                    maxlength="2000"
                    name="criterios[__INDICE__][descripcion]"
                    required
                    rows="2"
                ></textarea>

                <div class="mt-4 grid gap-3 lg:grid-cols-2">
                    @foreach ([0 => 1, 1 => 2, 2 => 3, 3 => 4] as $posicion => $numero)
                        <div class="rounded-md bg-gray-50 p-3">
                            <input
                                name="criterios[__INDICE__][niveles][{{ $posicion }}][nivel]"
                                type="hidden"
                                value="{{ $numero }}"
                            >

                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600" for="nivel-__INDICE__-{{ $posicion }}-descripcion">
                                Nivel {{ $numero }} · Descripción
                            </label>
                            <textarea
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                id="nivel-__INDICE__-{{ $posicion }}-descripcion"
                                maxlength="2000"
                                name="criterios[__INDICE__][niveles][{{ $posicion }}][descripcion]"
                                required
                                rows="2"
                            ></textarea>

                            <label class="mt-2 block text-xs font-semibold uppercase tracking-wide text-gray-600" for="nivel-__INDICE__-{{ $posicion }}-puntaje">
                                Puntaje
                            </label>
                            <input
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                id="nivel-__INDICE__-{{ $posicion }}-puntaje"
                                max="9999.99"
                                min="0"
                                name="criterios[__INDICE__][niveles][{{ $posicion }}][puntaje]"
                                required
                                step="0.01"
                                type="number"
                            >
                        </div>
                    @endforeach
                </div>

                <button
                    class="mt-3 rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                    data-quitar-criterio
                    type="button"
                >
                    Quitar criterio
                </button>
            </fieldset>
        </template>

        <script>
            (() => {
                const contenedor = document.getElementById('criterios');
                const plantilla = document.getElementById('plantilla-criterio');
                const agregar = document.getElementById('agregar-criterio');

                if (!contenedor || !plantilla || !agregar) {
                    return;
                }

                agregar.addEventListener('click', () => {
                    const existentes = Array.from(contenedor.querySelectorAll('[data-criterio]'))
                        .map((elemento) => Number(elemento.dataset.criterio))
                        .filter((valor) => Number.isInteger(valor));
                    const indice = existentes.length === 0 ? 0 : Math.max(...existentes) + 1;

                    contenedor.insertAdjacentHTML(
                        'beforeend',
                        plantilla.innerHTML.replaceAll('__INDICE__', String(indice)),
                    );
                });

                contenedor.addEventListener('click', (evento) => {
                    const boton = evento.target.closest('[data-quitar-criterio]');

                    if (!boton || contenedor.querySelectorAll('[data-criterio]').length <= 1) {
                        return;
                    }

                    boton.closest('[data-criterio]')?.remove();
                });
            })();
        </script>
    </main>
</x-app-layout>
