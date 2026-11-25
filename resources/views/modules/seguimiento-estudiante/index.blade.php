<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Seguimiento al Estudiante</h1>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.avisos.index') }}">Avisos de tutoría</a>
    </div>

    @can('seguimiento-estudiante.nivelacion.agenda-docente')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.nivelacion.agenda-docente') }}">Mis sesiones de Nivelación</a>
        </div>
    @endcan

    @can('seguimiento-estudiante.nivelacion.documentos')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.nivelacion.documentos') }}">Documentos de Nivelación</a>
        </div>
    @endcan

    @can('seguimiento-estudiante.tutoria.asignar')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.asignaciones.index') }}">Asignaciones de tutoría</a>
        </div>
    @endcan

    @if (auth()->user()->can('seguimiento-estudiante.tutoria.view') && auth()->user()->can('seguimiento-estudiante.plan-tutoria.editar'))
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.planes.index') }}">Mis aportes al plan de tutoría</a>
        </div>
    @endif

    @can('seguimiento-estudiante.plan-tutoria.revisar')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.planes.coordinacion') }}">Revisar aportes de Tutoría</a>
        </div>
    @endcan

    @canany(['seguimiento-estudiante.plan-general.publicar', 'seguimiento-estudiante.tutoria.view'])
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.plan-general.index') }}">Plan general de Tutoría</a>
        </div>
    @endcanany

    @can('seguimiento-estudiante.secretaria.lotes')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.secretaria.lotes.index') }}">Lotes de Secretaría</a>
        </div>
    @endcan

    @can('seguimiento-estudiante.secretaria.prevalidar')
        <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.secretaria.recepcion') }}">Prevalidar un CSV de Secretaría</a>
        </div>
    @endcan

    <section class="mx-auto max-w-5xl space-y-4 px-4 py-8 sm:px-6" aria-labelledby="expedientes">
        <h2 id="expedientes" class="text-lg font-semibold text-gray-900">Expedientes disponibles</h2>
        <form method="GET" action="{{ route('seguimiento-estudiante.index') }}" class="flex flex-wrap items-end gap-3">
            <label>Nombre o código universitario
                <input type="search" name="q" maxlength="100" value="{{ request('q') }}" class="block rounded border-gray-300">
            </label>
            <button type="submit" class="text-indigo-700 underline">Buscar</button>
        </form>
        @can('seguimiento-estudiante.tutoria.view')
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.directorio.exportar', ['q' => request('q')]) }}">Exportar resultados en CSV</a>
        @endcan
        <ul class="divide-y divide-gray-200 rounded-lg bg-white px-4">
            @forelse ($estudiantes as $estudiante)
                <li class="py-3">
                    <a class="font-medium text-indigo-700 underline"
                       href="{{ route('seguimiento-estudiante.expediente', $estudiante->id_estudiante) }}">
                        {{ $estudiante->nombres }} {{ $estudiante->apellido_paterno }}
                        {{ $estudiante->apellido_materno }}
                        <span class="text-sm text-gray-600">({{ $estudiante->codigo_universitario }})</span>
                    </a>
                </li>
            @empty
                <li class="py-4 text-gray-700">{{ request()->filled('q') ? 'Sin coincidencias.' : 'Tu cuenta no tiene expedientes habilitados.' }}</li>
            @endforelse
        </ul>
        {{ $estudiantes->links() }}
    </section>
</x-app-layout>
