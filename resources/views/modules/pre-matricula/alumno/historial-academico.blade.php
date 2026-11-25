@extends('modules.pre-matricula.layout')

@section('title', 'Historial académico')
@section('page-label', 'Historial académico')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Historial académico</h1><p>Tus resultados oficiales registrados por la escuela. Con ellos se validan los prerrequisitos de tu prematrícula.</p></div>
    </header>

    <section class="quality-panel" aria-labelledby="historial-titulo">
        <header class="quality-panel-heading"><div><h2 id="historial-titulo">{{ $estudiante->codigo_universitario }}</h2><p>{{ $historial->count() }} {{ Str::plural('resultado', $historial->count()) }}</p></div></header>
        <div class="pm-table-wrap">
            <table class="pm-table">
                <thead><tr><th>Período</th><th>Curso</th><th class="pm-num">Créditos</th><th class="pm-num">Nota</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($historial as $h)
                        <tr>
                            <td>{{ $h->periodo }}</td>
                            <td><span class="pm-code">{{ $h->codigo }}</span><br>{{ $h->nombre }}</td>
                            <td class="pm-num">{{ (float) $h->creditos }}</td>
                            <td class="pm-num">{{ $h->nota_final !== null ? (float) $h->nota_final : '—' }}</td>
                            <td>@include('modules.pre-matricula.partials.estado', ['valor' => $h->estado])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="pm-muted">Aún no tienes resultados registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
