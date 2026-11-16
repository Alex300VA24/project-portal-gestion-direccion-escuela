@php
    use App\Modules\GestionPracticas\Models\Practica;

    $corregible = in_array($practica->estado, [Practica::REGISTRADA, Practica::OBSERVADA], true);
    $tiposSubida = $practica->estado === Practica::EN_CURSO
        ? \Illuminate\Support\Arr::only($tiposDocumento, ['informe_parcial', 'informe_final', 'constancia', 'otro'])
        : \Illuminate\Support\Arr::only($tiposDocumento, ['carta_aceptacion', 'plan_practica', 'otro']);
    $puedeSubir = $corregible || $practica->estado === Practica::EN_CURSO;
    $campo = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600';
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi práctica en {{ $empresa }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
                    @if ($practica->estado === Practica::FINALIZADA)
                        <span class="text-sm font-semibold">Nota final: {{ $practica->nota_final }} ({{ $practica->resultado }})</span>
                    @endif
                </div>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Área</dt><dd>{{ $practica->area }}</dd></div>
                    <div><dt class="text-gray-500">Horas planificadas</dt><dd>{{ $practica->horas_totales }}</dd></div>
                    <div><dt class="text-gray-500">Inicio</dt><dd>{{ $practica->fecha_inicio->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">Fin</dt><dd>{{ $practica->fecha_fin->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Funciones</dt><dd class="whitespace-pre-line">{{ $practica->funciones }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Docente supervisor</dt><dd>{{ $supervisor ?? 'Aún sin asignar' }}</dd></div>
                </dl>

                @if ($corregible)
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('gestion-practicas.estudiante.practicas.edit', $practica) }}" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-semibold hover:bg-gray-50">Corregir datos</a>
                        <form method="POST" action="{{ route('gestion-practicas.estudiante.practicas.enviar', $practica) }}">@csrf<x-primary-button>Enviar información para revisión</x-primary-button></form>
                    </div>
                @endif
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

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <h3 class="font-semibold text-gray-900">Documentos</h3>
                @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => false])

                @if ($puedeSubir)
                    <form method="POST" enctype="multipart/form-data" action="{{ route('gestion-practicas.documentos.store', $practica) }}" class="grid gap-3 sm:grid-cols-3 items-end border-t pt-4">
                        @csrf
                        <div>
                            <x-input-label for="tipo_documento" value="Tipo de documento" />
                            <select id="tipo_documento" name="tipo_documento" required class="{{ $campo }}">
                                @foreach ($tiposSubida as $valor => $texto)
                                    <option value="{{ $valor }}">{{ $texto }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="archivo" value="Archivo (máx. 10 MB)" />
                            <input id="archivo" name="archivo" type="file" required class="mt-1 block w-full text-sm">
                        </div>
                        <x-primary-button>Subir documento</x-primary-button>
                    </form>
                @endif
            </div>

            @if ($seguimientos->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Seguimiento del supervisor</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach ($seguimientos as $seguimiento)
                            <li class="border-s-4 border-blue-300 ps-3">
                                <p class="text-xs text-gray-500">{{ $seguimiento->fecha->format('d/m/Y') }} · {{ str_replace('_', ' ', $seguimiento->tipo) }}</p>
                                <p>{{ $seguimiento->descripcion }}</p>
                                @if ($seguimiento->observaciones)<p class="text-gray-600">{{ $seguimiento->observaciones }}</p>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
