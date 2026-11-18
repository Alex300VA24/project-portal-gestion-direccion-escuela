@extends('modules.investigacion-formativa.layouts.app')
@section('title', 'Investigación Formativa')
@section('page-label', 'Inicio')

@section('content')
        <div class="if-page-heading">
            <div>
                <p class="if-eyebrow">Módulo activo</p>
                <h1 class="if-heading">Elige tu espacio de trabajo</h1>
                <p class="if-lead">
                    Tu cuenta tiene más de un rol en este módulo. Los permisos y el alcance de cada
                    espacio se verifican por separado.
                </p>
            </div>
        </div>

        <ul class="if-grid-wide" role="list">
            @foreach ($workspaces as $workspace)
                <li>
                    <a class="if-card-link" href="{{ route($workspace['route']) }}">
                        <span>
                            <strong class="if-card-title">{{ $workspace['label'] }}</strong>
                            <span class="if-card-text">{{ $workspace['description'] }}</span>
                        </span>
                        <span class="if-card-action">Ingresar</span>
                    </a>
                </li>
            @endforeach
        </ul>
@endsection
