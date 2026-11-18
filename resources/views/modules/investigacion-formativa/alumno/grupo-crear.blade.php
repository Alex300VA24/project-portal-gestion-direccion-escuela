@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Estudiante · Formar grupo')
@section('page-label', 'Formar grupo')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Formar grupo en la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Formar grupo de investigación</h1>
                <p class="if-lead">
                    El grupo y sus integrantes quedan fijos al guardar. Tú serás el coordinador y el
                    único integrante que podrá editar el título.
                </p>
            </div>
        </div>

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

        <form
            class="if-panel if-stack"
            style="margin-top: 22px; padding: 22px;"
            method="POST"
            action="{{ route('investigacion-formativa.alumno.grupos.store', $seccion) }}"
        >
            @csrf

            <div class="if-form-grid">
                <div class="if-field">
                    <label for="grupo-nombre">Nombre del grupo</label>
                    <input
                        id="grupo-nombre"
                        maxlength="120"
                        name="nombre"
                        required
                        type="text"
                        value="{{ old('nombre') }}"
                    >
                </div>

                <div class="if-field">
                    <label for="grupo-titulo">Título propuesto</label>
                    <input
                        id="grupo-titulo"
                        maxlength="250"
                        name="titulo"
                        required
                        type="text"
                        value="{{ old('titulo') }}"
                    >
                </div>
            </div>

            <fieldset>
                <legend class="if-legend">Integrantes</legend>
                <p class="if-note" style="margin-top: 4px;">
                    Solo se listan estudiantes del padrón activo de la sección. El coordinador
                    queda incluido automáticamente.
                </p>

                <ul class="if-choice-list" role="list">
                    @foreach ($roster as $integrante)
                        @php($esCreador = $integrante->id_estudiante === $idEstudiante)
                        <li>
                            <label class="if-choice" for="integrante-{{ $integrante->id_estudiante }}">
                                <input
                                    id="integrante-{{ $integrante->id_estudiante }}"
                                    name="integrante_ids[]"
                                    type="checkbox"
                                    value="{{ $integrante->id_estudiante }}"
                                    @checked($esCreador || in_array($integrante->id_estudiante, array_map('intval', (array) old('integrante_ids', []))))
                                    @disabled($esCreador)
                                >
                                <span>
                                    <strong>{{ $integrante->nombre }}</strong>
                                    <span class="if-note if-mono">{{ $integrante->codigo }}</span>
                                    @if ($esCreador)
                                        <span class="if-text-success">Coordinador</span>
                                        <input name="integrante_ids[]" type="hidden" value="{{ $integrante->id_estudiante }}">
                                    @endif
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </fieldset>

            <div class="if-form-grid">
                <div class="if-field">
                    <label for="grupo-linea">Línea de investigación</label>
                    <select
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

                <div class="if-field">
                    <label for="grupo-tipo-producto">Tipo de producto</label>
                    <select
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
                <div class="if-warning-note">
                    Falta cargar las líneas de investigación activas o los tipos de producto.
                    Solicita la configuración antes de crear el grupo.
                </div>
            @endif

            <div class="if-actions">
                <button
                    class="if-button"
                    type="submit"
                    @disabled($lineas === [] || $tiposProducto === [])
                >
                    Crear grupo
                </button>
                <a class="if-link" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                    Cancelar
                </a>
            </div>
        </form>
@endsection
