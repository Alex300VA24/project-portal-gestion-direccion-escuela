<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Seguimiento al Estudiante</h1>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 pt-6 sm:px-6">
        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.avisos.index') }}">Avisos de tutoría</a>
    </div>

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
        <ul class="divide-y divide-gray-200 rounded-lg bg-white px-4">
            @forelse ($estudiantes as $estudiante)
                <li class="py-3">
                    <a class="font-medium text-indigo-700 underline"
                       href="{{ route('seguimiento-estudiante.expediente', $estudiante->id_estudiante) }}">
                        {{ $estudiante->persona->nombres }} {{ $estudiante->persona->apellido_paterno }}
                        {{ $estudiante->persona->apellido_materno }}
                        <span class="text-sm text-gray-600">({{ $estudiante->codigo_universitario }})</span>
                    </a>
                </li>
            @empty
                <li class="py-4 text-gray-700">Tu cuenta no tiene expedientes habilitados.</li>
            @endforelse
        </ul>
        {{ $estudiantes->links() }}
    </section>
</x-app-layout>
