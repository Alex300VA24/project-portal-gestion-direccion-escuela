@php
    use App\Modules\GestionPracticas\Models\Convocatoria;
    use App\Modules\GestionPracticas\Support\Catalogos;

    $editable = in_array($convocatoria->estado, [Convocatoria::BORRADOR, Convocatoria::OBSERVADA], true);
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $convocatoria->titulo }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])
                    <span class="text-sm text-gray-500">{{ $postulantes }} postulante(s)</span>
                </div>

                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Tipo</dt><dd>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</dd></div>
                    <div><dt class="text-gray-500">Modalidad</dt><dd>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</dd></div>
                    <div><dt class="text-gray-500">Vacantes</dt><dd>{{ $convocatoria->vacantes }}</dd></div>
                    <div><dt class="text-gray-500">Cierre</dt><dd>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Descripción</dt><dd class="whitespace-pre-line">{{ $convocatoria->descripcion }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Requisitos</dt><dd class="whitespace-pre-line">{{ $convocatoria->requisitos }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Enlace de postulación</dt><dd><a class="text-emerald-700 hover:underline break-all" href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener">{{ $convocatoria->enlace_postulacion }}</a></dd></div>
                </dl>

                <div class="flex flex-wrap gap-3 pt-2">
                    @if ($editable)
                        <a href="{{ route('gestion-practicas.empresa.convocatorias.edit', $convocatoria) }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50">Corregir</a>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.enviar', $convocatoria) }}">@csrf<x-primary-button>Enviar a revisión</x-primary-button></form>
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.anular', $convocatoria) }}">@csrf<button class="px-4 py-2 text-sm text-red-700 hover:underline">Anular</button></form>
                    @endif
                    @if ($convocatoria->estado === Convocatoria::PUBLICADA)
                        <form method="POST" action="{{ route('gestion-practicas.empresa.convocatorias.cerrar', $convocatoria) }}">@csrf<button class="px-4 py-2 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50">Cerrar convocatoria</button></form>
                    @endif
                </div>
            </div>

            @if ($observaciones->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Observaciones del administrador</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach ($observaciones as $observacion)
                            <li class="border-s-4 {{ $observacion->estado === 'pendiente' ? 'border-red-400' : 'border-emerald-400' }} ps-3">
                                <p>{{ $observacion->descripcion }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $observacion->registrado_en->format('d/m/Y H:i') }} · @include('modules.gestion-practicas.partials.estado', ['estado' => $observacion->estado])</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
