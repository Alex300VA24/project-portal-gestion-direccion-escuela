@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Competencias')
@section('page-label', 'Competencias')

@section('content')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de configuración">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Competencias de la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Competencias de la sección</h1>
        <p class="mt-2 text-sm text-gray-600">
            Selecciona las competencias que se evaluarán en esta sección. La lista proviene
            de la importación del sílabo aprobado.
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

        @php
            $seleccionadas = array_map(
                static fn (object $competencia): int => (int) $competencia->id_competencia,
                array_values(array_filter($competencias, static fn (object $competencia): bool => $competencia->seleccionada)),
            );
            $marcadas = array_map('intval', (array) old('competencia_ids', $seleccionadas));
        @endphp

        @if ($competencias === [])
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Esta sección no tiene competencias importadas. Solicita la importación del
                sílabo aprobado antes de continuar.
            </p>
        @else
            <form
                class="mt-6 rounded-lg border border-gray-200 bg-white p-5"
                method="POST"
                action="{{ route('investigacion-formativa.docente.competencias.update', $seccion->id_seccion) }}"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

                <fieldset>
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
                </fieldset>

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
        @endif
@endsection
