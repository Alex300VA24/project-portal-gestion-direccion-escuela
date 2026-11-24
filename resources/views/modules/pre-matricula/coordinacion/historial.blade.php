@extends('modules.pre-matricula.layout')

@section('title', 'Historial académico')
@section('page-label', 'Historial académico')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Historial académico</h1><p>Resultados oficiales registrados por estudiante, curso y período. Se cargan desde <a href="{{ route('pre-matricula.coordinacion.resultados') }}">Resultados oficiales</a>.</p></div>
    </header>

    <section class="quality-panel" aria-labelledby="historial-titulo">
        <header class="quality-panel-heading"><div><h2 id="historial-titulo">Resultados</h2><p>{{ $historial->total() }} {{ Str::plural('resultado', $historial->total()) }}</p></div></header>
        <form class="pm-filters pm-body" method="GET" action="{{ route('pre-matricula.coordinacion.historial') }}" role="search">
            <label class="pm-field"><span>Estudiante</span><input type="search" name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="Código o nombre"></label>
            <label class="pm-field"><span>Período</span><select name="periodo"><option value="">Todos</option>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}" @selected($idPeriodo === $p->id_periodo)>{{ $p->codigo }}</option>@endforeach</select></label>
            <button class="pm-button" type="submit">Filtrar</button>
        </form>
        <div class="pm-table-wrap">
            <table class="pm-table">
                <thead><tr><th>Estudiante</th><th>Curso</th><th>Período</th><th class="pm-num">Nota</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($historial as $h)
                        <tr>
                            <td><span class="pm-code">{{ $h->codigo_universitario }}</span><br>{{ trim($h->apellido_paterno.' '.$h->apellido_materno) }}, {{ $h->nombres }}</td>
                            <td><span class="pm-code">{{ $h->codigo }}</span><br>{{ $h->nombre }}</td>
                            <td>{{ $h->periodo }}</td>
                            <td class="pm-num">{{ $h->nota_final !== null ? (float) $h->nota_final : '—' }}</td>
                            <td>@include('modules.pre-matricula.partials.estado', ['valor' => $h->estado])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pm-muted">No hay resultados para estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($historial->hasPages())
            <div class="pm-pagination">
                <span>{{ $historial->firstItem() }}–{{ $historial->lastItem() }} de {{ $historial->total() }}</span>
                <span class="pm-actions">
                    @if ($historial->previousPageUrl())<a class="pm-button is-secondary is-small" href="{{ $historial->previousPageUrl() }}">Anterior</a>@endif
                    @if ($historial->nextPageUrl())<a class="pm-button is-secondary is-small" href="{{ $historial->nextPageUrl() }}">Siguiente</a>@endif
                </span>
            </div>
        @endif
    </section>
@endsection
