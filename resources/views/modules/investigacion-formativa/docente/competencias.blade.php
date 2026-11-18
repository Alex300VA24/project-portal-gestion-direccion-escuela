@extends('layouts.app')
@section('title', 'Competencias · Investigación Formativa')
@section('page-label', 'Competencias')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Preferencias</span>
            <h2>Competencias de la sección</h2>
            <p>
                Selecciona las competencias que se evaluarán en esta sección. La lista proviene
                de la importación del sílabo aprobado.
            </p>
        </div>
    </div>

    @if (session('status'))
        <div class="if-flash-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($conflicto !== null)
        <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
            {{ $conflicto }} Recarga la página antes de volver a intentar.
        </div>
    @endif

    @if ($errors->any())
        <div class="if-flash-danger" role="alert" style="margin-bottom:16px;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 17h.01"/></svg>
            <span>
                <strong>Revisa los siguientes errores</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </span>
        </div>
    @endif

    @if ($competencias === [])
        <div class="if-empty" style="margin:16px 0;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <h3>Esta sección no tiene competencias importadas</h3>
            <p>Solicita la importación del sílabo aprobado antes de continuar.</p>
        </div>
    @else
        <div class="if-panel" style="margin:16px 0;">
            <div class="if-panel-heading" style="border-bottom:1px solid var(--portal-border);padding:20px 22px;">
                <h2 class="text-sm font-semibold text-gray-900" style="margin:0 0 8px 0;">Competencias disponibles</h2>
                <p class="text-sm text-gray-600" style="margin:6px 0 0 0;">
                    Selecciona las competencias que se evaluarán en esta sección. La lista proviene
                    de la importación del sílabo aprobado.
                </p>
            </div>

            <div style="padding:20px 22px;">
                <form method="POST" action="{{ route('investigacion-formativa.docente.competencias.update', $seccion->id_seccion) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

                    <div>
                        <legend class="text-sm font-semibold text-gray-900">Competencias disponibles</legend>
                        <ul class="mt-3 space-y-3" role="list">
                            @foreach ($competencias as $competencia)
                                <li>
                                    <label class="flex gap-3 rounded-md border border-gray-200 p-3 hover:border-indigo-300" for="competencia-{{ $competencia->id_competencia }}">
                                        <input
                                            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            id="competencia-{{ $competencia->id_competencia }}"
                                            name="competencia_ids[]"
                                            type="checkbox"
                                            value="{{ $competencia->id_competencia }}"
                                            @checked(in_array((int) $competencia->id_competencia, $marcadas, true))
                                        >
                                        <span>
                                            <strong class="block text-sm text-gray-900">{{ $competencia->codigo }}</strong>
                                            <span class="mt-1 block text-sm text-gray-700">{{ $competencia->descripcion }}</span>
                                            <span class="mt-1 block text-xs text-gray-500">Fuente: {{ $competencia->fuente }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <button
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            type="submit"
                        >
                            Guardar competencias
                        </button>
                        <a class="text-sm font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}">
                            Continuar con la rúbrica
                        </a>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif