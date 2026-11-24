@extends('modules.pre-matricula.layout')

@section('title', 'Plan de estudios')
@section('page-label', 'Plan de estudios')

@php $reporte = session('reporte_malla'); @endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Plan de estudios</h1><p>@if ($plan) Malla del plan {{ $plan->codigo }}. Un curso usado en historial o prematrícula conserva sus datos académicos, salvo una corrección confirmada. @else {{ $aviso }} @endif</p></div>
        @if ($plan)
            <div class="pm-actions">
                <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.plan.plantilla') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Plantilla</a>
                <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.plan.exportar') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Exportar malla</a>
            </div>
        @endif
    </header>

    @if ($plan)
        <form class="pm-filters" method="GET" action="{{ route('pre-matricula.coordinacion.plan') }}" role="search">
            <label class="pm-field"><span>Ciclo</span><select name="ciclo"><option value="">Todos</option>@foreach ($ciclos as $c)<option value="{{ $c }}" @selected((int) $ciclo === $c)>Ciclo {{ $c }}</option>@endforeach</select></label>
            <label class="pm-field"><span>Buscar</span><input type="search" name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="Código o nombre"></label>
            <button class="pm-button is-secondary" type="submit">Filtrar</button>
        </form>

        <div class="pm-stack">
            <section class="quality-panel" aria-labelledby="malla-titulo">
                <header class="quality-panel-heading"><div><h2 id="malla-titulo">Cursos</h2><p>{{ $cursos->count() }} {{ Str::plural('curso', $cursos->count()) }}</p></div></header>
                <div class="pm-table-wrap">
                    <table class="pm-table">
                        <thead><tr><th>Curso</th><th>Ciclo</th><th class="pm-num">Créditos</th><th class="pm-num">T / P / L</th><th>Prerrequisitos</th><th>Estado</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($cursos as $c)
                                <tr>
                                    <td><span class="pm-code">{{ $c->codigo }}</span><br>{{ $c->nombre }}@if ($c->en_uso)<br><small class="pm-muted">En uso</small>@endif</td>
                                    <td>{{ $c->ciclo }}</td>
                                    <td class="pm-num">{{ $c->creditos }}</td>
                                    <td class="pm-num">{{ $c->horas_teoria }} / {{ $c->horas_practica }} / {{ $c->horas_laboratorio }}</td>
                                    <td>{{ $c->prerrequisitos === [] ? '—' : implode(', ', $c->prerrequisitos) }}</td>
                                    <td>@include('modules.pre-matricula.partials.estado', ['valor' => $c->activo ? 'ACTIVO' : 'INACTIVO'])</td>
                                    <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.plan.cursos.edit', $c->codigo) }}" aria-label="Editar {{ $c->nombre }}">Editar</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="pm-muted">No hay cursos para estos filtros.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="pm-grid">
                <section class="quality-panel" aria-labelledby="importar-titulo">
                    <header class="quality-panel-heading"><div><h2 id="importar-titulo">Importar malla</h2><p>Columnas: {{ implode(', ', \App\Modules\PreMatricula\Services\PlanEstudios::COLUMNAS) }}. Prerrequisitos separados por punto y coma.</p></div></header>
                    <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.plan.validar') }}" enctype="multipart/form-data" x-data="{ enviando: false }" @submit="enviando = true">
                        @csrf
                        <label class="pm-field"><span>Archivo .xlsx (máximo 5 MB)</span><input type="file" name="archivo" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
                        <button class="pm-button" type="submit" :disabled="enviando" x-text="enviando ? 'Validando…' : 'Validar'">Validar</button>
                    </form>
                </section>

                @if ($reporte)
                    <section class="quality-panel" aria-labelledby="reporte-titulo">
                        <header class="quality-panel-heading"><div><h2 id="reporte-titulo">Revisar y confirmar</h2><p>{{ $reporte['archivo'] }}</p></div>@if ($reporte['valido'])<span class="quality-status is-success">Sin errores</span>@else<span class="quality-status is-danger">{{ $reporte['total_errores'] }} errores</span>@endif</header>
                        <div class="pm-body">
                            <dl class="pm-facts">
                                <div><dt>Nuevos</dt><dd>{{ $reporte['resumen']['crear'] }}</dd></div>
                                <div><dt>Actualizados</dt><dd>{{ $reporte['resumen']['actualizar'] }}</dd></div>
                                <div><dt>Sin cambios</dt><dd>{{ $reporte['resumen']['sin_cambios'] }}</dd></div>
                            </dl>
                            @if (! $reporte['valido'])
                                <p>Corrige el archivo y vuelve a validarlo. No se guardará nada mientras tenga errores.</p>
                                <div class="pm-table-wrap">
                                    <table class="pm-table">
                                        <thead><tr><th>Fila</th><th>Columna</th><th>Problema</th></tr></thead>
                                        <tbody>@foreach ($reporte['errores'] as $error)<tr><td class="pm-num">{{ $error['fila'] }}</td><td><span class="pm-code">{{ $error['columna'] }}</span></td><td>{{ $error['mensaje'] }}</td></tr>@endforeach</tbody>
                                    </table>
                                </div>
                            @else
                                <div class="pm-table-wrap">
                                    <table class="pm-table">
                                        <thead><tr><th>Fila</th><th>Curso</th><th>Cambia</th><th>Acción</th></tr></thead>
                                        <tbody>
                                            @forelse ($reporte['cambios'] as $c)
                                                <tr><td class="pm-num">{{ $c['fila'] }}</td><td><span class="pm-code">{{ $c['codigo'] }}</span></td><td>{{ implode(', ', $c['campos']) }}</td><td>@include('modules.pre-matricula.partials.estado', ['valor' => $c['accion']])</td></tr>
                                            @empty
                                                <tr><td colspan="4" class="pm-muted">El archivo coincide con la malla actual.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <form class="pm-actions pm-mt" method="POST" action="{{ route('pre-matricula.coordinacion.plan.confirmar') }}" x-data="{ enviando: false }" @submit="enviando = true">
                                    @csrf
                                    <input type="hidden" name="sha256" value="{{ $reporte['sha256'] }}">
                                    <input type="hidden" name="huella" value="{{ $reporte['huella'] }}">
                                    <button class="pm-button" type="submit" :disabled="enviando">Confirmar importación</button>
                                    <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.plan') }}">Descartar</a>
                                </form>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif
@endsection
