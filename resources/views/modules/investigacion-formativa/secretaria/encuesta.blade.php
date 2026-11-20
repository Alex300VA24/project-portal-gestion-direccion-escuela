@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Secretaría · Encuestas')
@section('page-label', 'Secretaría')

@section('content')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.secretaria.index') }}">
                Mis secciones
            </a>
            <span aria-hidden="true">/</span>
            <span>Encuesta de la sección {{ $seccion->codigo }}</span>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Encuesta de la sección {{ $seccion->codigo }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Prepare las preguntas y publíquelas una sola vez. El cierre de la encuesta y los resultados
                    confidenciales quedan a cargo de la dirección de la escuela.
                </p>
            </div>

            @php
                $estadoEncuesta = match ($encuesta->estado) {
                    'publicada' => 'Publicada',
                    'cerrada' => 'Cerrada',
                    default => 'Borrador',
                };
                $estadoClase = match ($encuesta->estado) {
                    'publicada' => 'bg-emerald-100 text-emerald-800',
                    'cerrada' => 'bg-gray-200 text-gray-700',
                    default => 'bg-amber-100 text-amber-800',
                };
            @endphp

            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $estadoClase }}">
                {{ $estadoEncuesta }}
            </span>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-conflicto>
                <p>{{ $conflicto }}</p>
                @if ($versionIntentada !== null)
                    <p class="mt-2" data-version-intentada>
                        Versión enviada: {{ $versionIntentada }}.
                        Versión guardada actualmente: {{ $encuesta->version }}.
                    </p>
                @endif
                <p class="mt-2">Recarga la página antes de volver a intentarlo. Los cambios enviados no reemplazan el borrador actual.</p>
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

        @include('modules.investigacion-formativa.partials.encuesta-gestion')
@endsection
