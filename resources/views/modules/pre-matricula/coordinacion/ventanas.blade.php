@extends('modules.pre-matricula.layout')

@section('title', 'Ventanas')
@section('page-label', 'Ventanas')

@php $formato = fn ($fecha) => $fecha?->timezone('America/Lima')->translatedFormat('d M Y, H:i'); @endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Ventanas de prematrícula</h1><p>Una ventana ordinaria por período; las extraordinarias van después y no se solapan. Solo en una extraordinaria los estudiantes pueden corregir.</p></div>
        @if ($periodos->isNotEmpty())@include('modules.pre-matricula.coordinacion._periodo', ['accion' => route('pre-matricula.coordinacion.ventanas')])@endif
    </header>

    <div class="pm-grid">
        <section class="quality-panel" aria-labelledby="lista-titulo">
            <header class="quality-panel-heading"><div><h2 id="lista-titulo">Ventanas del período {{ $periodo?->codigo }}</h2><p>Las ventanas no se editan ni se eliminan: quedan como historial.</p></div></header>
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Situación</th></tr></thead>
                    <tbody>
                        @forelse ($ventanas as $v)
                            <tr>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => $v->tipo])<small>{{ $v->motivo }}</small></td>
                                <td>{{ $formato($v->fecha_inicio) }}</td>
                                <td>{{ $formato($v->fecha_fin) }}</td>
                                <td>
                                    @if ($v->fecha_inicio <= $ahora && $v->fecha_fin > $ahora)<span class="quality-status is-success">Abierta</span>
                                    @elseif ($v->fecha_inicio > $ahora)<span class="quality-status is-neutral">Programada</span>
                                    @else<span class="quality-status is-neutral">Cerrada</span>@endif
                                    <small>{{ $v->autor?->correo_institucional }}</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pm-muted">Este período no tiene ventanas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="quality-panel" aria-labelledby="nueva-titulo">
            <header class="quality-panel-heading"><div><h2 id="nueva-titulo">Nueva ventana</h2><p>Fechas en hora de Lima. El fin no se incluye.</p></div></header>
            <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.ventanas.store') }}">
                @csrf
                <label class="pm-field"><span>Período</span>
                    <select name="id_periodo" required>@foreach ($periodos as $p)<option value="{{ $p->id_periodo }}" @selected((int) old('id_periodo', $periodo?->id_periodo) === $p->id_periodo)>{{ $p->codigo }}</option>@endforeach</select>
                </label>
                <label class="pm-field"><span>Tipo</span>
                    <select name="tipo" required>
                        <option value="ORDINARIA" @selected(old('tipo') === 'ORDINARIA')>Ordinaria</option>
                        <option value="EXTRAORDINARIA" @selected(old('tipo') === 'EXTRAORDINARIA')>Extraordinaria (permite corregir)</option>
                    </select>
                </label>
                <div class="pm-form-row">
                    <label class="pm-field"><span>Inicio</span><input type="datetime-local" name="fecha_inicio" value="{{ old('fecha_inicio') }}" required></label>
                    <label class="pm-field"><span>Fin</span><input type="datetime-local" name="fecha_fin" value="{{ old('fecha_fin') }}" required></label>
                </div>
                <label class="pm-field"><span>Motivo</span><textarea name="motivo" maxlength="500" required>{{ old('motivo') }}</textarea><small>Queda registrado en el historial de la ventana.</small></label>
                <button class="pm-button" type="submit">Crear ventana</button>
            </form>
        </section>
    </div>
@endsection
