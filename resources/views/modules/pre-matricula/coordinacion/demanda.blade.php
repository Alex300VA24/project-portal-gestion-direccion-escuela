@extends('modules.pre-matricula.layout')

@section('title', 'Demanda')
@section('page-label', 'Demanda')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Demanda por curso</h1><p>{{ \App\Modules\PreMatricula\Services\DemandaAcademica::AVISO }} Solo cuenta la constancia vigente de cada estudiante.</p></div>
        @if ($periodos->isNotEmpty())@include('modules.pre-matricula.coordinacion._periodo', ['accion' => route('pre-matricula.coordinacion.demanda')])@endif
    </header>

    @if ($periodo === null)
        <section class="quality-panel"><div class="quality-empty"><h2>No hay períodos con prematrículas</h2></div></section>
    @else
        <dl class="pm-facts">
            <div><dt>Estudiantes</dt><dd>{{ $resumen['total_estudiantes'] }}</dd></div>
            <div><dt>Constancias vigentes</dt><dd>{{ $resumen['total_registros'] }}</dd></div>
            @foreach ($resumen['ciclos'] as $c)<div><dt>Ciclo {{ $c->ciclo }}</dt><dd>{{ $c->estudiantes }} <small>est.</small></dd></div>@endforeach
        </dl>

        <form class="pm-filters" method="GET" action="{{ route('pre-matricula.coordinacion.demanda') }}">
            <input type="hidden" name="periodo" value="{{ $periodo->id_periodo }}">
            <label class="pm-field"><span>Ciclo</span><select name="ciclo"><option value="">Todos</option>@foreach ($resumen['ciclos'] as $c)<option value="{{ $c->ciclo }}" @selected($ciclo === (int) $c->ciclo)>Ciclo {{ $c->ciclo }}</option>@endforeach</select></label>
            <label class="pm-field"><span>Curso</span><input type="search" name="buscar" value="{{ $buscar }}" placeholder="Código o nombre"></label>
            <button class="pm-button" type="submit">Filtrar</button>
            <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.demanda.exportar', $periodo->id_periodo) }}">Exportar CSV</a>
        </form>

        <section class="quality-panel">
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Curso</th><th>Ciclo</th><th class="pm-num">Créditos</th><th class="pm-num">Estudiantes</th><th class="pm-num">Válida</th><th class="pm-num">Pendiente</th><th class="pm-num">Requiere ajuste</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($cursos as $c)
                            <tr>
                                <td><span class="pm-code">{{ $c->codigo }}</span><br>{{ $c->nombre }}</td><td>{{ $c->ciclo }}</td><td class="pm-num">{{ (float) $c->creditos }}</td>
                                <td class="pm-num"><strong>{{ $c->estudiantes }}</strong></td><td class="pm-num">{{ $c->valida }}</td><td class="pm-num">{{ $c->pendiente }}</td><td class="pm-num">{{ $c->requiere_ajuste }}</td>
                                <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.demanda.interesados', [$periodo->id_periodo, $c->id_curso_plan]) }}">Estudiantes</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="pm-muted">No hay cursos con demanda para estos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
