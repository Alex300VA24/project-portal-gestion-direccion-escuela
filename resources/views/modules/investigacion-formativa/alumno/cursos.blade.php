@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Estudiante · Mis cursos')
@section('page-label', 'Mis cursos')

@section('content')

        <h1 class="text-2xl font-bold text-gray-900">Mis cursos</h1>
        <p class="mt-2 text-sm text-gray-600">
            Secciones donde figuras en el padrón de participación. Forma tu grupo cuando la
            sección esté habilitada y configurada.
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
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

        @if ($secciones === [])
            <p class="mt-6 rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
                No tienes secciones activas con participación registrada.
            </p>
        @else
            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-4 py-3" scope="col">Periodo</th>
                            <th class="px-4 py-3" scope="col">Curso</th>
                            <th class="px-4 py-3" scope="col">Sección</th>
                            <th class="px-4 py-3" scope="col">Estado</th>
                            <th class="px-4 py-3" scope="col">Grupo</th>
                            <th class="px-4 py-3" scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($secciones as $seccion)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ $seccion->periodo }}</td>
                                <td class="px-4 py-3 text-gray-900">
                                    {{ $seccion->curso_codigo }} · {{ $seccion->curso }}
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $seccion->seccion }}</td>
                                <td class="px-4 py-3">
                                    @if (! $seccion->habilitada)
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                            Pendiente de habilitación
                                        </span>
                                    @elseif (! $seccion->configurada)
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
                                            Configuración pendiente
                                        </span>
                                        <p class="mt-1 text-xs text-amber-900">
                                            {{ implode(', ', $seccion->faltantes) }}
                                        </p>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">
                                            Habilitada
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $seccion->id_grupo === null ? 'Sin grupo' : 'Grupo formado' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($seccion->id_grupo !== null)
                                        <a
                                            class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                            href="{{ route('investigacion-formativa.alumno.grupos.show', $seccion->id_seccion) }}"
                                        >
                                            Ver mi grupo
                                        </a>
                                    @elseif ($seccion->habilitada && $seccion->configurada)
                                        <a
                                            class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                                            href="{{ route('investigacion-formativa.alumno.grupos.create', $seccion->id_seccion) }}"
                                        >
                                            Formar grupo
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-500">Disponible al completar la configuración</span>
                                    @endif

                                    <a
                                        class="mt-2 inline-flex rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        href="{{ route('investigacion-formativa.alumno.encuestas.show', $seccion->id_seccion) }}"
                                    >
                                        Ver encuesta
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($retiradas !== [])
            <section class="mt-8" aria-labelledby="titulo-grupos-anteriores">
                <h2 id="titulo-grupos-anteriores" class="text-lg font-semibold text-gray-900">
                    Grupos anteriores (solo lectura)
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Conservas acceso de lectura a los grupos de tu participación retirada.
                </p>

                <ul class="mt-4 grid gap-3 sm:grid-cols-2" role="list">
                    @foreach ($retiradas as $retirada)
                        <li class="rounded-lg border border-gray-200 bg-white p-4">
                            <p class="text-sm font-semibold text-gray-900">{{ $retirada->grupo }}</p>
                            <p class="mt-1 text-sm text-gray-700">
                                {{ $retirada->curso_codigo }} · {{ $retirada->curso }} · Sección {{ $retirada->seccion }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">Periodo {{ $retirada->periodo }}</p>
                            <a
                                class="mt-3 inline-flex rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                href="{{ route('investigacion-formativa.alumno.grupos.show', $retirada->id_seccion) }}"
                            >
                                Ver grupo
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
@endsection
