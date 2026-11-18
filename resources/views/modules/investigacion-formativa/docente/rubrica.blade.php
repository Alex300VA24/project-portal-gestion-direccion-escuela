@extends('layouts.app')
@section('title', 'Rúbrica · Investigación Formativa')
@section('page-label', 'Rúbrica')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Preferencias</span>
            <h2>Rúbrica de avances</h1>
            <p class="text-sm text-gray-600">
                Cada criterio debe tener cuatro niveles con su descripción y puntaje. El puntaje
                máximo del criterio se calcula con el mayor puntaje de sus niveles.
            </p>
        </div>

        @if (session('status'))
            <div class="if-flash-success" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @if ($conflicto !== null)
            <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
                {{ $conflicto }} Recarga la página antes de volver a intentar.
            </div>
        @endif

        @if ($errors->any())
            <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="if-panel" style="margin:16px 0;">
            <div class="if-panel-heading" style="border-bottom:1px solid var(--portal-border);padding:20px 22px;">
                <h2 class="text-xl font-semibold text-gray-900" style="margin:0 0 8px 0;">Rúbrica de avances</h1>
                <p class="text-sm text-gray-600" style="margin:6px 0 0 0;">
                    Cada criterio debe tener cuatro niveles con su descripción y puntaje. El puntaje
                    máximo del criterio se calcula con el mayor puntaje de sus niveles.
                </p>
            </div>

            <div style="padding:20px 22px;">
                <div class="mt-6 flex items-center gap-3">
                    <button
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        type="submit"
                    >
                        Guardar rúbrica
                    </button>
                    <a class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cronograma.edit', $seccion->id_seccion) }}">
                        Continuar con el cronograma
                    </a>
                </div>
            </div>
        </div>
    @endif
@endsection