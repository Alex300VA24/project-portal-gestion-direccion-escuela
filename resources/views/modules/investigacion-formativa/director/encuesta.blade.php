@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Dirección · Encuesta')
@section('page-label', 'Encuestas')

@section('content')

        <nav class="if-actions if-note" aria-label="Ruta de navegación">
            <a class="if-link" href="{{ route('investigacion-formativa.director.cursos.index') }}">Mis cursos</a>
            <span aria-hidden="true">/</span>
            <span>Encuesta de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="if-page-heading">
            <div>
                <h1 class="if-heading">Encuesta de la sección {{ $seccion->codigo }}</h1>
                <p class="if-lead">
                    Prepare las preguntas, publíquelas una sola vez y cierre la encuesta cuando termine el periodo de respuesta.
                </p>
            </div>

            @php
                $estadoEncuesta = match ($encuesta->estado) {
                    'publicada' => 'Publicada',
                    'cerrada' => 'Cerrada',
                    default => 'Borrador',
                };
                $estadoClase = match ($encuesta->estado) {
                    'publicada' => 'is-success',
                    'cerrada' => 'is-neutral',
                    default => 'is-warning',
                };
            @endphp

            <span class="if-status {{ $estadoClase }}">{{ $estadoEncuesta }}</span>
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
                <div>
                    <p>{{ $conflicto }}</p>
                    @if ($versionIntentada !== null)
                        <p class="if-small" style="margin-top: 6px;" data-version-intentada>
                            Versión enviada: {{ $versionIntentada }}.
                            Versión guardada actualmente: {{ $encuesta->version }}.
                        </p>
                    @endif
                    <p class="if-small" style="margin-top: 6px;">
                        Recarga la página antes de volver a intentarlo. Los cambios enviados no reemplazan el borrador actual.
                    </p>
                </div>
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

        @include('modules.investigacion-formativa.partials.encuesta-gestion')
@endsection
