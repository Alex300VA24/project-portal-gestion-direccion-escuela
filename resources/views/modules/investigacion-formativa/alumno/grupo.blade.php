<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Estudiante · Mi grupo
        </h2>
    </x-slot>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @include('modules.investigacion-formativa.partials.sidebar')

        <nav class="mb-4 text-sm text-gray-600" aria-label="Ruta de navegación">
            <a class="font-semibold text-indigo-700 hover:text-indigo-900" href="{{ route('investigacion-formativa.alumno.cursos.index') }}">
                Mis cursos
            </a>
            <span aria-hidden="true">/</span>
            <span>{{ $detalle?->grupo->nombre ?? 'Mi grupo' }}</span>
        </nav>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
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

        @if ($detalle === null)
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                No se encontró información del grupo.
            </p>
        @else
            @if (! $participa)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    Tu participación está retirada. Puedes consultar el grupo en modo lectura.
                </p>
            @endif

            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-grupo">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 id="titulo-grupo" class="text-xl font-bold text-gray-900">{{ $detalle->grupo->nombre }}</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Sección {{ $seccion->codigo }} · {{ $detalle->entregados }} de 3 avances entregados
                        </p>
                    </div>

                    @if ($esCoordinador)
                        <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">
                            Eres coordinador
                        </span>
                    @endif
                </div>

                @if ($detalle->proyecto !== null)
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-medium text-gray-700">Línea de investigación</dt>
                            <dd class="text-gray-900">{{ $detalle->proyecto->linea }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-700">Tipo de producto</dt>
                            <dd class="text-gray-900">{{ $detalle->proyecto->tipo_producto }}</dd>
                        </div>
                    </dl>
                @endif

                <h2 class="mt-6 text-sm font-semibold text-gray-900">Integrantes</h2>
                <ul class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200" role="list">
                    @foreach ($detalle->miembros as $miembro)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm">
                            <span>
                                <span class="font-medium text-gray-900">{{ $miembro->nombre }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $miembro->codigo }}</span>
                            </span>
                            @if ($miembro->es_coordinador)
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800">
                                    Coordinador
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-propuesta">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titulo-propuesta" class="text-lg font-semibold text-gray-900">Título propuesto</h2>

                    @if ($detalle->proyecto !== null)
                        @php
                            $estado = $detalle->proyecto->estado;
                            $clasesEstado = match ($estado) {
                                'Aceptado' => 'bg-emerald-100 text-emerald-800',
                                'Cambio solicitado' => 'bg-rose-100 text-rose-800',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $clasesEstado }}">
                            {{ $estado }} · versión {{ $detalle->proyecto->version }}
                        </span>
                    @endif
                </div>

                @if ($detalle->proyecto === null)
                    <p class="mt-3 text-sm text-amber-900">El grupo todavía no tiene un proyecto registrado.</p>
                @else
                    <p class="mt-3 whitespace-pre-line text-sm text-gray-900">{{ $detalle->proyecto->titulo }}</p>

                    @if ($esCoordinador && $motivoBloqueo !== null)
                        <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                            {{ $motivoBloqueo }}
                        </p>
                    @elseif ($esCoordinador)
                        <form
                            class="mt-4 flex flex-wrap items-end gap-3"
                            method="POST"
                            action="{{ route('investigacion-formativa.alumno.titulos.update', $seccion) }}"
                        >
                            @csrf
                            @method('PATCH')
                            <input name="version" type="hidden" value="{{ $detalle->proyecto->version }}">

                            <div class="min-w-[16rem] flex-1">
                                <label class="block text-sm font-medium text-gray-700" for="titulo-propuesto">
                                    Editar título
                                </label>
                                <input
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    id="titulo-propuesto"
                                    maxlength="250"
                                    name="titulo"
                                    required
                                    type="text"
                                    value="{{ old('titulo', $detalle->proyecto->titulo) }}"
                                >
                            </div>

                            <button
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                type="submit"
                            >
                                Guardar título
                            </button>
                        </form>
                    @endif
                @endif

                @if ($detalle->historial !== [])
                    <h3 class="mt-6 text-sm font-semibold text-gray-900">Historial de revisiones</h3>
                    <div class="mt-2 overflow-x-auto rounded-md border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                                <tr>
                                    <th class="px-3 py-2" scope="col">Título</th>
                                    <th class="px-3 py-2" scope="col">Estado</th>
                                    <th class="px-3 py-2" scope="col">Motivo</th>
                                    <th class="px-3 py-2" scope="col">Autor</th>
                                    <th class="px-3 py-2" scope="col">Fecha</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($detalle->historial as $revision)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900">{{ $revision->titulo }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->estado }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->motivo ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $revision->autor }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-700">
                                            {{ \Carbon\CarbonImmutable::parse($revision->registrado_en)->setTimezone('America/Lima')->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </main>
</x-app-layout>
