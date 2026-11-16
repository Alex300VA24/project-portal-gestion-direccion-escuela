@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $convocatoria->titulo }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <p class="font-medium text-gray-900">{{ $empresa }}</p>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Tipo</dt><dd>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</dd></div>
                    <div><dt class="text-gray-500">Modalidad</dt><dd>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</dd></div>
                    <div><dt class="text-gray-500">Vacantes</dt><dd>{{ $convocatoria->vacantes }}</dd></div>
                    <div><dt class="text-gray-500">Cierre de postulación</dt><dd>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Descripción</dt><dd class="whitespace-pre-line">{{ $convocatoria->descripcion }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Requisitos</dt><dd class="whitespace-pre-line">{{ $convocatoria->requisitos }}</dd></div>
                </dl>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <a href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50">Acceder al enlace de postulación</a>

                    @if (! $vigente)
                        <span class="text-sm text-gray-500">La convocatoria ya no está vigente.</span>
                    @elseif ($yaPostulo)
                        <span class="text-sm font-semibold text-emerald-700">Ya registró su postulación.</span>
                    @else
                        <form method="POST" action="{{ route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria) }}">
                            @csrf
                            <x-primary-button>Registrar mi postulación</x-primary-button>
                        </form>
                    @endif
                </div>
                <p class="text-xs text-gray-500">Postule primero en el enlace de la empresa y luego registre aquí su postulación para llevar el control del proceso.</p>
            </div>
        </div>
    </div>
</x-app-layout>
