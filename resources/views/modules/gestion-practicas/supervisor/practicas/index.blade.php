@extends('modules.gestion-practicas.layout')

@section('title', 'Prácticas asignadas')
@section('page-label', 'Prácticas asignadas')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Prácticas asignadas</h1><p>Haga el seguimiento de cada estudiante, revise sus informes y registre la evaluación final.</p></div>
    </header>

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">Mis prácticas supervisadas</h2><p>{{ count($practicas) }} {{ count($practicas) === 1 ? 'práctica' : 'prácticas' }}</p></div>
        </header>
        @forelse ($practicas as $practica)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$practica->id_empresa] ?? '—' }}</span>
                    <strong>{{ $estudiantes[$practica->id_estudiante] ?? '—' }}</strong>
                    <small>{{ $practica->area }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])</div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.supervisor.practicas.show', $practica) }}">Abrir</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Aún no tiene prácticas asignadas',
                'texto' => 'Cuando el administrador valide una práctica y lo asigne como supervisor aparecerá aquí.',
            ])
        @endforelse
    </section>
@endsection
