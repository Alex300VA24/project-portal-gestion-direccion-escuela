@extends('modules.pre-matricula.layout')

@section('title', 'Períodos')
@section('page-label', 'Períodos')

@php
    $etiquetas = ['PLANIFICACION' => 'Planificación', 'PREMATRICULA' => 'Prematrícula', 'EN_CURSO' => 'En curso', 'CERRADO' => 'Cerrado'];
    $formato = fn ($fecha) => $fecha?->translatedFormat('d M Y');
@endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Períodos académicos</h1><p>Al pasar un período a Prematrícula se abre automáticamente su ventana ordinaria hasta el fin de clases. Un período con prematrículas o resultados conserva su código y fechas.</p></div>
    </header>

    <div class="pm-grid">
        <section class="quality-panel" aria-labelledby="periodos-titulo">
            <header class="quality-panel-heading"><div><h2 id="periodos-titulo">Períodos registrados</h2><p>{{ $periodos->count() }} {{ Str::plural('período', $periodos->count()) }}</p></div></header>
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Código</th><th>Clases</th><th>Estado</th><th class="pm-num">Ventanas</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($periodos as $p)
                            <tr>
                                <td><span class="pm-code">{{ $p->codigo }}</span></td>
                                <td>{{ $formato($p->fecha_inicio) }} – {{ $formato($p->fecha_fin) }}</td>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $p->estado])</td>
                                <td class="pm-num">{{ $p->ventanas->count() }}</td>
                                <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.periodos.edit', $p->id_periodo) }}" aria-label="Editar {{ $p->codigo }}">Editar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pm-muted">Aún no hay períodos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="quality-panel" aria-labelledby="nuevo-titulo">
            <header class="quality-panel-heading"><div><h2 id="nuevo-titulo">Nuevo período</h2><p>Ejemplo de código: 2027-I.</p></div></header>
            <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.periodos.store') }}">
                @csrf
                <label class="pm-field"><span>Código</span><input name="codigo" maxlength="20" value="{{ old('codigo') }}" required></label>
                <div class="pm-form-row">
                    <label class="pm-field"><span>Inicio de clases</span><input type="date" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required></label>
                    <label class="pm-field"><span>Fin de clases</span><input type="date" name="fecha_fin" value="{{ old('fecha_fin') }}" required></label>
                </div>
                <label class="pm-field"><span>Estado</span>
                    <select name="estado" required>@foreach ($estados as $e)<option value="{{ $e }}" @selected(old('estado', 'PLANIFICACION') === $e)>{{ $etiquetas[$e] }}</option>@endforeach</select>
                </label>
                <button class="pm-button" type="submit">Crear período</button>
            </form>
        </section>
    </div>
@endsection
