@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Investigación Formativa')
@section('page-label', 'Inicio')

@section('content')
        <p class="text-sm font-medium text-indigo-700">Módulo activo</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">Elige tu espacio de trabajo</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-600">
            Tu cuenta tiene más de un rol en este módulo. Los permisos y el alcance de cada
            espacio se verifican por separado.
        </p>

        <ul class="mt-6 grid gap-4 md:grid-cols-3" role="list">
            @foreach ($workspaces as $workspace)
                <li>
                    <a
                        class="flex h-full flex-col justify-between rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        href="{{ route($workspace['route']) }}"
                    >
                        <span>
                            <strong class="block text-lg font-semibold text-gray-900">
                                {{ $workspace['label'] }}
                            </strong>
                            <span class="mt-2 block text-sm text-gray-600">
                                {{ $workspace['description'] }}
                            </span>
                        </span>
                        <span class="mt-4 text-sm font-semibold text-indigo-700">Ingresar</span>
                    </a>
                </li>
            @endforeach
        </ul>
@endsection
