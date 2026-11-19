@extends('modules.gestion-practicas.layout')

@section('title', 'Validar prácticas')
@section('page-label', 'Validar prácticas')

@section('content')
    @php
        $filtros = ['en_revision' => 'En revisión', 'observada' => 'Observadas', 'en_curso' => 'En curso', 'finalizada' => 'Finalizadas', 'todas' => 'Todas'];
    @endphp
    <header class="quality-page-heading">
        <div><h1>Validar prácticas</h1><p>Valide la información de cada práctica y asigne al docente supervisor.</p></div>
    </header>

    <section class="quality-doc-filters" aria-label="Filtros">
        <div>
            <strong id="estado-titulo">Estado</strong>
            <nav class="quality-segment" aria-labelledby="estado-titulo">
                @foreach ($filtros as $valor => $texto)
                    <a href="{{ route('gestion-practicas.revision.practicas.index', ['estado' => $valor]) }}" @if ($estado === $valor) aria-current="page" @endif>{{ $texto }}</a>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">{{ $filtros[$estado] ?? 'Prácticas' }}</h2><p>{{ count($practicas) }} {{ count($practicas) === 1 ? 'práctica' : 'prácticas' }}</p></div>
        </header>
        @forelse ($practicas as $practica)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$practica->id_empresa] ?? '—' }}</span>
                    <strong>{{ $estudiantes[$practica->id_estudiante] ?? '—' }}</strong>
                    <small>{{ $practica->area }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])</div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.revision.practicas.show', $practica) }}">Revisar</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'No hay prácticas en este estado',
                'texto' => 'Cuando un estudiante envíe su práctica a revisión aparecerá aquí.',
            ])
        @endforelse
    </section>
@endsection
