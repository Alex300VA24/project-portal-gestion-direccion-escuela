@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Cronograma')
@section('page-label', 'Cronograma')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de configuración">
            <a class="if-link" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Cronograma de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Cronograma de las tres unidades</h1>
                <p class="if-lead">
                    Registra la fecha y hora límite de cada unidad en hora de Lima (America/Lima).
                    Las fechas deben respetar el orden de las unidades.
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

        @if ($estado->id_rubrica === null)
            <div class="if-warning-note" style="margin-top: 22px;">
                Primero guarda la rúbrica.
                <a class="if-link" href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}">
                    Ir a la rúbrica
                </a>
            </div>
        @endif

        <form
            class="if-panel if-stack"
            style="margin-top: 22px; padding: 22px;"
            method="POST"
            action="{{ route('investigacion-formativa.docente.cronograma.update', $seccion->id_seccion) }}"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

            <div class="if-form-grid">
                @foreach ([1, 2, 3] as $indice => $unidad)
                    <div class="if-field">
                        <label for="fecha-{{ $indice }}">
                            Unidad {{ $unidad }} · Fecha y hora límite
                        </label>
                        <input
                            id="fecha-{{ $indice }}"
                            name="fechas[{{ $indice }}]"
                            required
                            type="datetime-local"
                            value="{{ $fechas[$indice] }}"
                        >
                    </div>
                @endforeach
            </div>

            <div class="if-actions">
                <button class="if-button" type="submit">
                    Guardar cronograma
                </button>
            </div>
        </form>
@endsection
