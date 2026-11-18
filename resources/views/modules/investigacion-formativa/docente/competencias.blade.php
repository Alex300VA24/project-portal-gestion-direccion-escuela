@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Competencias')
@section('page-label', 'Competencias')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de configuración">
            <a class="if-link" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Competencias de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Competencias de la sección</h1>
                <p class="if-lead">
                    Selecciona las competencias que se evaluarán en esta sección. La lista proviene
                    de la importación del sílabo aprobado.
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

        @php
            $seleccionadas = array_map(
                static fn (object $competencia): int => (int) $competencia->id_competencia,
                array_values(array_filter($competencias, static fn (object $competencia): bool => $competencia->seleccionada)),
            );
            $marcadas = array_map('intval', (array) old('competencia_ids', $seleccionadas));
        @endphp

        @if ($competencias === [])
            <div class="if-warning-note" style="margin-top: 22px;">
                Esta sección no tiene competencias importadas. Solicita la importación del
                sílabo aprobado antes de continuar.
            </div>
        @else
            <form
                class="if-panel if-stack"
                style="margin-top: 22px; padding: 22px;"
                method="POST"
                action="{{ route('investigacion-formativa.docente.competencias.update', $seccion->id_seccion) }}"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

                <fieldset class="if-fieldset">
                    <legend class="if-legend">Competencias disponibles</legend>

                    <ul class="if-choice-list" role="list">
                        @foreach ($competencias as $competencia)
                            <li>
                                <label class="if-choice" for="competencia-{{ $competencia->id_competencia }}">
                                    <input
                                        id="competencia-{{ $competencia->id_competencia }}"
                                        name="competencia_ids[]"
                                        type="checkbox"
                                        value="{{ $competencia->id_competencia }}"
                                        @checked(in_array((int) $competencia->id_competencia, $marcadas, true))
                                    >
                                    <span>
                                        <strong class="if-mono">{{ $competencia->codigo }}</strong>
                                        <span class="if-card-text">{{ $competencia->descripcion }}</span>
                                        <span class="if-note">Fuente: {{ $competencia->fuente }}</span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>

                <div class="if-actions">
                    <button class="if-button" type="submit">
                        Guardar competencias
                    </button>
                    <a class="if-link" href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}">
                        Continuar con la rúbrica
                    </a>
                </div>
            </form>
        @endif
@endsection
