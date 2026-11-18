@extends('layouts.app')
@section('title', 'Investigación Formativa')
@section('page-label', 'Inicio')

@section('content')
    <div class="if-page-heading">
        <div>
            <span class="if-eyebrow">Módulo</span>
            <h1>Elige tu espacio de trabajo</h1>
            <p>
                Tu cuenta tiene más de un rol en este módulo. Los permisos y el alcance de cada
                espacio se verifican por separado.
            </p>
        </div>
    </div>

    <ul class="grid gap-4 md:grid-cols-3" role="list" style="list-style:none;margin:0;padding:0;">
        @foreach ($workspaces as $workspace)
            <li>
                <a
                    class="if-panel"
                    style="display:flex;flex-direction:column;justify-content:space-between;padding:20px 22px;min-height:120px;text-decoration:none;color:inherit;"
                    href="{{ route($workspace['route']) }}"
                >
                    <div>
                        <strong class="block text-lg font-semibold" style="margin:0 0 8px 0;">{{ $workspace['label'] }}</strong>
                        <span class="block text-sm" style="color:var(--portal-muted);">{{ $workspace['description'] }}</span>
                    </div>
                    <span class="if-link" style="margin-top:16px;">Ingresar</span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection