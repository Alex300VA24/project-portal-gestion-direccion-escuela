@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Rúbrica')
@section('page-label', 'Rúbrica')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de configuración">
            <a class="if-link" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Rúbrica de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Rúbrica de avances</h1>
                <p class="if-lead">
                    Cada criterio debe tener cuatro niveles con su descripción y puntaje. El puntaje
                    máximo del criterio se calcula con el mayor puntaje de sus niveles.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="if-flash-success" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="if-alert" role="alert" data-conflicto>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                <span>{{ $conflicto }} Recarga la página antes de volver a intentar.</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="if-flash-danger" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                <ul class="if-list" role="list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('modules.investigacion-formativa.partials.estado-configuracion', ['estado' => $estado])

        @if ($congelada)
            <div class="if-warning-note" style="margin-top: 22px;">
                La rúbrica está congelada porque ya existen entregas en esta sección. No se
                puede modificar.
            </div>
        @endif

        <form
            class="if-panel if-stack"
            style="margin-top: 22px; padding: 22px;"
            method="POST"
            action="{{ route('investigacion-formativa.docente.rubrica.update', $seccion->id_seccion) }}"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

            <div class="if-field">
                <label for="rubrica-nombre">Nombre de la rúbrica</label>
                <input
                    id="rubrica-nombre"
                    maxlength="150"
                    name="nombre"
                    required
                    type="text"
                    value="{{ old('nombre', $rubrica?->nombre ?? 'Rúbrica de avances') }}"
                >
            </div>

            <div class="if-field">
                <label for="rubrica-descripcion">Descripción (opcional)</label>
                <textarea
                    id="rubrica-descripcion"
                    maxlength="4000"
                    name="descripcion"
                    rows="2"
                >{{ old('descripcion', $rubrica?->descripcion ?? '') }}</textarea>
            </div>

            <div class="if-section-heading" style="margin: 0;">
                <h2 class="if-section-title">Criterios</h2>
                <button
                    class="if-button-secondary if-button-sm"
                    id="agregar-criterio"
                    type="button"
                >
                    Agregar criterio
                </button>
            </div>

            <div class="if-stack" id="criterios">
                @foreach ($criterios as $indice => $criterio)
                    <fieldset class="if-fieldset" data-criterio="{{ $indice }}">
                        <legend class="if-legend">Criterio {{ $indice + 1 }}</legend>

                        <div class="if-field" style="margin-top: 8px;">
                            <label for="criterio-{{ $indice }}-descripcion">
                                Descripción del criterio
                            </label>
                            <textarea
                                id="criterio-{{ $indice }}-descripcion"
                                maxlength="2000"
                                name="criterios[{{ $indice }}][descripcion]"
                                required
                                rows="2"
                            >{{ $criterio['descripcion'] }}</textarea>
                        </div>

                        <div class="if-split" style="margin-top: 16px;">
                            @foreach ($criterio['niveles'] as $posicion => $nivel)
                                <div class="if-card if-stack">
                                    <input
                                        name="criterios[{{ $indice }}][niveles][{{ $posicion }}][nivel]"
                                        type="hidden"
                                        value="{{ $nivel['nivel'] }}"
                                    >

                                    <div class="if-field">
                                        <label for="nivel-{{ $indice }}-{{ $posicion }}-descripcion">
                                            Nivel {{ $nivel['nivel'] }} · Descripción
                                        </label>
                                        <textarea
                                            id="nivel-{{ $indice }}-{{ $posicion }}-descripcion"
                                            maxlength="2000"
                                            name="criterios[{{ $indice }}][niveles][{{ $posicion }}][descripcion]"
                                            required
                                            rows="2"
                                        >{{ $nivel['descripcion'] }}</textarea>
                                    </div>

                                    <div class="if-field">
                                        <label for="nivel-{{ $indice }}-{{ $posicion }}-puntaje">
                                            Puntaje
                                        </label>
                                        <input
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
                                </div>
                            @endforeach
                        </div>

                        <button
                            class="if-button-secondary if-button-sm if-button-ghost-danger"
                            style="margin-top: 12px;"
                            data-quitar-criterio
                            type="button"
                        >
                            Quitar criterio
                        </button>
                    </fieldset>
                @endforeach
            </div>

            <div class="if-actions">
                <button
                    class="if-button"
                    type="submit"
                    @disabled($congelada)
                >
                    Guardar rúbrica
                </button>
                <a class="if-link" href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}">
                    Continuar con el cronograma
                </a>
            </div>
        </form>

        <template id="plantilla-criterio">
            <fieldset class="if-fieldset" data-criterio="__INDICE__">
                <legend class="if-legend">Criterio adicional</legend>

                <div class="if-field" style="margin-top: 8px;">
                    <label for="criterio-__INDICE__-descripcion">
                        Descripción del criterio
                    </label>
                    <textarea
                        id="criterio-__INDICE__-descripcion"
                        maxlength="2000"
                        name="criterios[__INDICE__][descripcion]"
                        required
                        rows="2"
                    ></textarea>
                </div>

                <div class="if-split" style="margin-top: 16px;">
                    @foreach ([0 => 1, 1 => 2, 2 => 3, 3 => 4] as $posicion => $numero)
                        <div class="if-card if-stack">
                            <input
                                name="criterios[__INDICE__][niveles][{{ $posicion }}][nivel]"
                                type="hidden"
                                value="{{ $numero }}"
                            >

                            <div class="if-field">
                                <label for="nivel-__INDICE__-{{ $posicion }}-descripcion">
                                    Nivel {{ $numero }} · Descripción
                                </label>
                                <textarea
                                    id="nivel-__INDICE__-{{ $posicion }}-descripcion"
                                    maxlength="2000"
                                    name="criterios[__INDICE__][niveles][{{ $posicion }}][descripcion]"
                                    required
                                    rows="2"
                                ></textarea>
                            </div>

                            <div class="if-field">
                                <label for="nivel-__INDICE__-{{ $posicion }}-puntaje">
                                    Puntaje
                                </label>
                                <input
                                    id="nivel-__INDICE__-{{ $posicion }}-puntaje"
                                    max="9999.99"
                                    min="0"
                                    name="criterios[__INDICE__][niveles][{{ $posicion }}][puntaje]"
                                    required
                                    step="0.01"
                                    type="number"
                                >
                            </div>
                        </div>
                    @endforeach
                </div>

                <button
                    class="if-button-secondary if-button-sm if-button-ghost-danger"
                    style="margin-top: 12px;"
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
@endsection
