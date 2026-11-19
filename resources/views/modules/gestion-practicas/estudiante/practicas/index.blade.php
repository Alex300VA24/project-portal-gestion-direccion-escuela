@extends('modules.gestion-practicas.layout')

@section('title', 'Mis prácticas')
@section('page-label', 'Mis prácticas')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Mis prácticas</h1><p>Registre su práctica cuando sea seleccionado, suba los documentos y siga su avance.</p></div>
    </header>

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">Prácticas registradas</h2><p>{{ count($practicas) }} {{ count($practicas) === 1 ? 'práctica' : 'prácticas' }}</p></div>
        </header>
        @forelse ($practicas as $practica)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$practica->id_empresa] ?? '—' }}</span>
                    <strong>{{ $practica->area }}</strong>
                    <small>{{ $practica->fecha_inicio->format('d/m/Y') }} – {{ $practica->fecha_fin->format('d/m/Y') }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])</div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.estudiante.practicas.show', $practica) }}">Ver práctica</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Todavía no registró una práctica',
                'texto' => 'Cuando sea seleccionado por una empresa, regístrela desde «Mis postulaciones».',
            ])
        @endforelse
    </section>
@endsection
