@extends('modules.pre-matricula.layout')

@section('title', 'Curso '.$curso->codigo)
@section('page-label', 'Plan de estudios')

@section('content')
    <header class="quality-page-heading">
        <div><h1><span class="pm-code">{{ $curso->codigo }}</span> {{ $curso->nombre }}</h1><p>@if ($curso->en_uso) Ya se usa en historial o prematrícula: para cambiar sus datos académicos debes confirmar la corrección. El estado se puede cambiar siempre. @else Los cambios se aplican a la malla y revalidan las prematrículas que incluyen el curso. @endif</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.plan') }}">Volver</a>
    </header>

    <section class="quality-panel" aria-labelledby="curso-titulo">
        <header class="quality-panel-heading"><div><h2 id="curso-titulo">Datos del curso</h2></div>@include('modules.pre-matricula.partials.estado', ['valor' => $curso->activo ? 'ACTIVO' : 'INACTIVO'])</header>
        <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.plan.cursos.update', $curso->codigo) }}">
            @csrf @method('PUT')
            <label class="pm-field"><span>Nombre</span><input name="nombre" maxlength="150" value="{{ old('nombre', $curso->nombre) }}" required></label>
            <div class="pm-form-row">
                <label class="pm-field"><span>Créditos</span><input type="number" name="creditos" min="1" value="{{ old('creditos', $curso->creditos) }}" required></label>
                <label class="pm-field"><span>Ciclo</span><input type="number" name="ciclo" min="1" value="{{ old('ciclo', $curso->ciclo) }}" required></label>
            </div>
            <div class="pm-form-row">
                <label class="pm-field"><span>Horas de teoría</span><input type="number" name="horas_teoria" min="0" max="999" value="{{ old('horas_teoria', $curso->horas_teoria) }}" required></label>
                <label class="pm-field"><span>Horas de práctica</span><input type="number" name="horas_practica" min="0" max="999" value="{{ old('horas_practica', $curso->horas_practica) }}" required></label>
                <label class="pm-field"><span>Horas de laboratorio</span><input type="number" name="horas_laboratorio" min="0" max="999" value="{{ old('horas_laboratorio', $curso->horas_laboratorio) }}" required></label>
            </div>
            <label class="pm-field"><span>Prerrequisitos</span><input name="prerrequisitos" maxlength="2000" value="{{ old('prerrequisitos', implode(';', $curso->prerrequisitos)) }}" placeholder="Ej.: INF101;MAT101"><small>Códigos separados por punto y coma. Déjalo vacío si no tiene.</small></label>
            <label class="pm-field"><span>Estado</span>
                <select name="estado">
                    <option value="Activo" @selected($curso->activo)>Activo</option>
                    <option value="Inactivo" @selected(! $curso->activo)>Inactivo</option>
                </select>
            </label>
            @if ($curso->en_uso)
                <label class="pm-check"><input type="hidden" name="confirmar_correccion" value="0"><input type="checkbox" name="confirmar_correccion" value="1"> Confirmo la corrección de datos académicos de un curso en uso. Las constancias ya emitidas no cambian.</label>
            @endif
            <button class="pm-button" type="submit">Guardar cambios</button>
        </form>
    </section>
@endsection
