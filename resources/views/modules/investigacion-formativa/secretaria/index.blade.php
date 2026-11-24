@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Secretaría de escuela')
@section('page-label', 'Secretaría')

@section('content')

        <h1 class="text-2xl font-bold text-gray-900">Secciones asignadas</h1>
        <p class="mt-2 max-w-3xl text-sm text-gray-600">
            Prepare y publique la encuesta de las secciones habilitadas de sus escuelas. El alcance se define por
            asignación explícita y no por el correo del usuario.
        </p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($escuelas !== [])
            <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-escuelas">
                <h2 id="titulo-escuelas" class="text-lg font-semibold text-gray-900">Escuelas asignadas</h2>
                <ul class="mt-3 flex flex-wrap gap-2" role="list">
                    @foreach ($escuelas as $escuela)
                        <li class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                            {{ $escuela->nombre }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="mt-6 rounded-lg border border-gray-200 bg-white p-5" aria-labelledby="titulo-secciones">
            <h2 id="titulo-secciones" class="text-lg font-semibold text-gray-900">Secciones habilitadas</h2>

            @if ($secciones === [])
                <p class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                    No hay secciones habilitadas en sus escuelas asignadas. Si necesita revisar una sección,
                    solicite a la dirección que la habilite y confirme la asignación de su escuela.
                </p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">Secciones habilitadas con acceso a la encuesta</caption>
                        <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th scope="col" class="py-2 pr-4">Escuela</th>
                                <th scope="col" class="py-2 pr-4">Curso</th>
                                <th scope="col" class="py-2 pr-4">Sección</th>
                                <th scope="col" class="py-2 pr-4">Periodo</th>
                                <th scope="col" class="py-2">Encuesta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($secciones as $item)
                                <tr>
                                    <td class="py-3 pr-4 text-gray-700">{{ $item->escuela }}</td>
                                    <td class="py-3 pr-4 text-gray-700">
                                        <span class="font-semibold">{{ $item->curso_codigo }}</span>
                                        <span class="block text-gray-500">{{ $item->curso }}</span>
                                    </td>
                                    <td class="py-3 pr-4 text-gray-700">{{ $item->seccion }}</td>
                                    <td class="py-3 pr-4 text-gray-700">{{ $item->periodo }}</td>
                                    <td class="py-3">
                                        <a
                                            class="font-semibold text-indigo-700 hover:text-indigo-900"
                                            href="{{ route('investigacion-formativa.secretaria.encuestas.edit', $item->id_seccion) }}"
                                        >
                                            Preparar encuesta
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
@endsection
