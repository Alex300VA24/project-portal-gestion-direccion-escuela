@extends('modules.pre-matricula.layout')

@section('title', 'Período '.$periodo->codigo)
@section('page-label', 'Períodos')

@php $etiquetas = ['PLANIFICACION' => 'Planificación', 'PREMATRICULA' => 'Prematrícula', 'EN_CURSO' => 'En curso', 'CERRADO' => 'Cerrado']; @endphp

@section('content')
    <header class="quality-page-heading">
        <div><h1>Período {{ $periodo->codigo }}</h1><p>@if ($referenciado) Tiene prematrículas o resultados: solo puedes cambiar su estado. @else Puedes cambiar código, fechas y estado. @endif</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.periodos') }}">Volver</a>
    </header>

    <div class="pm-grid">
        <section class="quality-panel" aria-labelledby="editar-titulo">
            <header class="quality-panel-heading"><div><h2 id="editar-titulo">Datos del período</h2></div>@include('modules.pre-matricula.partials.estado', ['valor' => $periodo->estado])</header>
            <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.periodos.update', $periodo->id_periodo) }}">
                @csrf @method('PUT')
                <label class="pm-field"><span>Código</span><input name="codigo" maxlength="20" value="{{ old('codigo', $periodo->codigo) }}" @readonly($referenciado) required></label>
                <div class="pm-form-row">
                    <label class="pm-field"><span>Inicio de clases</span><input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $periodo->fecha_inicio->toDateString()) }}" @readonly($referenciado) required></label>
                    <label class="pm-field"><span>Fin de clases</span><input type="date" name="fecha_fin" value="{{ old('fecha_fin', $periodo->fecha_fin->toDateString()) }}" @readonly($referenciado) required></label>
                </div>
                <label class="pm-field"><span>Estado</span>
                    <select name="estado" required>@foreach ($estados as $e)<option value="{{ $e }}" @selected(old('estado', $periodo->estado) === $e)>{{ $etiquetas[$e] }}</option>@endforeach</select>
                    <small>Prematrícula abre la ventana ordinaria si el período aún no tiene ventanas.</small>
                </label>
                <button class="pm-button" type="submit">Guardar cambios</button>
            </form>
        </section>

        @unless ($referenciado)
            <section class="quality-panel" aria-labelledby="eliminar-titulo">
                <header class="quality-panel-heading"><div><h2 id="eliminar-titulo">Eliminar período</h2><p>Solo se puede eliminar un período sin ventanas, prematrículas ni resultados.</p></div></header>
                <form class="pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.periodos.destroy', $periodo->id_periodo) }}">
                    @csrf @method('DELETE')
                    <button class="pm-button is-danger" type="submit">Eliminar {{ $periodo->codigo }}</button>
                </form>
            </section>
        @endunless
    </div>
@endsection
