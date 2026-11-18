@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Docencia · Cronograma')
@section('page-label', 'Cronograma')

@section('content')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de configuración">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.docente.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>Cronograma de la sección {{ $seccion->codigo }}</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900">Cronograma de las tres unidades</h1>
        <p class="mt-2 text-sm text-gray-600">
            Registra la fecha y hora límite de cada unidad en hora de Lima (America/Lima).
            Las fechas deben respetar el orden de las unidades.
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

        @if ($estado->id_rubrica === null)
            <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Primero guarda la rúbrica.
                <a class="font-semibold underline" href="{{ route('investigacion-formativa.docente.rubrica.edit', $seccion->id_seccion) }}">
                    Ir a la rúbrica
                </a>
            </p>
        @endif

        <form
            class="mt-6 rounded-lg border border-gray-200 bg-white p-5"
            method="POST"
            action="{{ route('investigacion-formativa.docente.cronograma.update', $seccion->id_seccion) }}"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="version" value="{{ old('version', $estado->version) }}">

            <div class="space-y-4">
                @foreach ([1, 2, 3] as $indice => $unidad)
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="fecha-{{ $indice }}">
                            Unidad {{ $unidad }} · Fecha y hora límite
                        </label>
                        <input
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            id="fecha-{{ $indice }}"
                            name="fechas[{{ $indice }}]"
                            required
                            type="datetime-local"
                            value="{{ $fechas[$indice] }}"
                        >
                    </div>
                @endforeach
            </div>

            <div class="mt-5">
                <button
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    type="submit"
                >
                    Guardar cronograma
                </button>
            </div>
        </form>
@endsection
