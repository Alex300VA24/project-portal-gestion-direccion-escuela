<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Plan general de Tutoría</h1>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
        @isset($ambitos)
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.index') }}">Volver a Seguimiento al Estudiante</a>
            <ul class="divide-y divide-gray-200 rounded-lg bg-white px-4">
                @forelse ($ambitos as $item)
                    <li class="py-3">
                        <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.plan-general.mostrar', [$item->id_escuela, $item->id_periodo]) }}">
                            {{ $item->escuela }} · {{ $item->periodo }}
                        </a>
                    </li>
                @empty
                    <li class="py-3 text-gray-700">Tu cuenta no tiene escuelas y períodos habilitados para el plan general.</li>
                @endforelse
            </ul>
        @else
            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.plan-general.index') }}">Cambiar escuela o período</a>
            <h2 class="text-lg font-semibold text-gray-900">{{ $ambito->escuela }} · {{ $ambito->periodo }}</h2>
            <p class="text-gray-700">Estado del plan: {{ $estado }}@if ($vigente) · {{ $vigente['title'] }}@endif</p>
            @if (session('status'))
                <p role="status" class="rounded-md bg-green-50 p-3 text-green-800">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <ul role="alert" class="list-disc rounded-md bg-red-50 py-3 pl-8 text-red-800">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($ambito->gestion)
                <section class="space-y-4 rounded-lg bg-white p-4" aria-labelledby="publicar">
                    <h3 id="publicar" class="font-semibold text-gray-900">Registrar un plan aprobado</h3>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('seguimiento-estudiante.plan-general.publicar', [$ambito->id_escuela, $ambito->id_periodo]) }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="version" value="{{ $revision }}">
                        <input type="hidden" name="request_id" value="{{ $requestId }}">
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (['title' => 'Título', 'approval_reference' => 'Referencia de aprobación', 'approved_by' => 'Autoridad que aprobó'] as $campo => $etiqueta)
                                <label class="block text-sm text-gray-800">
                                    {{ $etiqueta }}
                                    <input type="text" name="{{ $campo }}" value="{{ old($campo) }}" maxlength="200" required class="mt-1 block w-full rounded-md border-gray-300">
                                </label>
                            @endforeach
                            @foreach (['approved_on' => 'Fecha de aprobación', 'valid_from' => 'Vigente desde', 'valid_until' => 'Vigente hasta'] as $campo => $etiqueta)
                                <label class="block text-sm text-gray-800">
                                    {{ $etiqueta }}
                                    <input type="date" name="{{ $campo }}" value="{{ old($campo) }}" required class="mt-1 block w-full rounded-md border-gray-300">
                                </label>
                            @endforeach
                        </div>
                        <label class="block text-sm text-gray-800">
                            PDF aprobado (máximo 5 MB)
                            <input type="file" name="pdf" accept="application/pdf,.pdf" required class="mt-1 block w-full">
                        </label>
                        <label class="block text-sm text-gray-800">
                            <input type="checkbox" name="approval_confirmed" value="1" required class="rounded border-gray-300">
                            Confirmo que el PDF corresponde al plan aprobado.
                        </label>
                        <x-primary-button>Registrar publicación</x-primary-button>
                    </form>
                </section>
            @endif

            <section class="space-y-4 rounded-lg bg-white p-4" aria-labelledby="historial">
                <h3 id="historial" class="font-semibold text-gray-900">Historial de publicaciones</h3>
                <ul class="divide-y divide-gray-200">
                    @forelse ($documentos as $documento)
                        <li class="space-y-2 py-3">
                            <p class="font-medium">Versión {{ $documento['version'] }} · {{ $documento['title'] }}</p>
                            <p class="text-sm text-gray-700">
                                {{ $documento['status'] === 'PUBLICADO' ? 'Publicada' : 'Retirada' }} · Vigencia del {{ $documento['valid_from'] }} al {{ $documento['valid_until'] }}
                            </p>
                            <p class="text-sm text-gray-700">
                                {{ $documento['approval_reference'] }} · {{ $documento['approved_by'] }} · {{ $documento['approved_on'] }}
                            </p>
                            <a class="text-indigo-700 underline" href="{{ route('seguimiento-estudiante.plan-general.descargar', [$ambito->id_escuela, $ambito->id_periodo, $documento['id']]) }}">Descargar PDF</a>
                            @if ($documento['status'] === 'RETIRADO')
                                <p class="text-sm text-gray-700">Motivo de retiro: {{ $documento['withdrawal']['reason'] }}</p>
                            @elseif ($ambito->gestion)
                                <form method="POST" action="{{ route('seguimiento-estudiante.plan-general.retirar', [$ambito->id_escuela, $ambito->id_periodo, $documento['id']]) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $revision }}">
                                    <label class="text-sm text-gray-800">
                                        Motivo de retiro
                                        <input type="text" name="reason" minlength="10" maxlength="1000" required class="block rounded-md border-gray-300">
                                    </label>
                                    <x-secondary-button type="submit">Retirar publicación</x-secondary-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="py-3 text-sm text-gray-700">Aún no hay publicaciones registradas.</li>
                    @endforelse
                </ul>
            </section>
        @endisset
    </div>
</x-app-layout>
