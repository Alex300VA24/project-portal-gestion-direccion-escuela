@extends('modules.gestion-practicas.layout')

@section('title', 'Revisar convocatorias')
@section('page-label', 'Revisar convocatorias')

@section('content')
    @php
        $filtros = ['en_revision' => 'En revisión', 'observada' => 'Observadas', 'publicada' => 'Publicadas', 'cerrada' => 'Cerradas', 'todas' => 'Todas'];
    @endphp
    <header class="quality-page-heading">
        <div><h1>Revisar convocatorias</h1><p>Apruebe las convocatorias conformes o devuélvalas a la empresa con observaciones.</p></div>
    </header>

    <section class="quality-doc-filters" aria-label="Filtros">
        <div>
            <strong id="estado-titulo">Estado</strong>
            <nav class="quality-segment" aria-labelledby="estado-titulo">
                @foreach ($filtros as $valor => $texto)
                    <a href="{{ route('gestion-practicas.revision.convocatorias.index', ['estado' => $valor]) }}" @if ($estado === $valor) aria-current="page" @endif>{{ $texto }}</a>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">{{ $filtros[$estado] ?? 'Convocatorias' }}</h2><p>{{ count($convocatorias) }} {{ count($convocatorias) === 1 ? 'convocatoria' : 'convocatorias' }}</p></div>
        </header>
        @forelse ($convocatorias as $convocatoria)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$convocatoria->id_empresa] ?? '—' }}</span>
                    <strong>{{ $convocatoria->titulo }}</strong>
                    <small>Cierra el {{ $convocatoria->fecha_cierre->format('d/m/Y') }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $convocatoria->estado])</div>
                <a class="quality-open-link" href="{{ route('gestion-practicas.revision.convocatorias.show', $convocatoria) }}">Revisar</a>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'No hay convocatorias en este estado',
                'texto' => 'Cuando una empresa envíe una convocatoria a revisión aparecerá aquí.',
            ])
        @endforelse
    </section>
@endsection
