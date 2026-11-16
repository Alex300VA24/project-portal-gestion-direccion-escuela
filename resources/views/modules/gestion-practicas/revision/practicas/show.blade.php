@php
    use App\Modules\GestionPracticas\Models\Practica;

    $campo = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600';
@endphp
<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">Práctica de {{ $estudiante }}</h2></x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('modules.gestion-practicas.partials.menu')
            @include('modules.gestion-practicas.partials.mensajes')

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-gray-900">{{ $empresa }}</p>
                    @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
                </div>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Área</dt><dd>{{ $practica->area }}</dd></div>
                    <div><dt class="text-gray-500">Horas planificadas</dt><dd>{{ $practica->horas_totales }}</dd></div>
                    <div><dt class="text-gray-500">Inicio</dt><dd>{{ $practica->fecha_inicio->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-gray-500">Fin</dt><dd>{{ $practica->fecha_fin->format('d/m/Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Funciones</dt><dd class="whitespace-pre-line">{{ $practica->funciones }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-gray-500">Docente supervisor</dt><dd>{{ $supervisor ?? 'Sin asignar' }}</dd></div>
                    @if ($practica->estado === Practica::FINALIZADA)
                        <div><dt class="text-gray-500">Nota final</dt><dd class="font-semibold">{{ $practica->nota_final }} ({{ $practica->resultado }})</dd></div>
                    @endif
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-900 mb-3">Documentos</h3>
                @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => true])
            </div>

            @if ($practica->estado === Practica::EN_REVISION)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">
                    <h3 class="font-semibold text-gray-900">¿La información es válida?</h3>

                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.validar', $practica) }}" class="space-y-2">
                        @csrf
                        <x-input-label for="id_docente" value="Sí: validar y asignar docente supervisor" />
                        <select id="id_docente" name="id_docente" required class="{{ $campo }}">
                            <option value="">Seleccione un docente…</option>
                            @foreach ($docentes as $id => $nombre)
                                <option value="{{ $id }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <x-primary-button>Validar y poner en curso</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('gestion-practicas.revision.practicas.observar', $practica) }}" class="space-y-2">
                        @csrf
                        <x-input-label for="descripcion" value="No: registrar observaciones" />
                        <textarea id="descripcion" name="descripcion" rows="3" required class="{{ $campo }}" placeholder="Indique qué debe corregir el estudiante"></textarea>
                        <button class="px-4 py-2 bg-red-700 text-white rounded-md text-sm font-semibold hover:bg-red-800">Devolver con observaciones</button>
                    </form>
                </div>
            @endif

            @if ($observaciones->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Historial de observaciones</h3>
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
