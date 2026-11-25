@extends('modules.pre-matricula.layout')

@section('title', 'Cuenta '.$cuenta->codigo_universitario)
@section('page-label', 'Cuentas')

@section('content')
    <header class="quality-page-heading">
        <div><h1>{{ $cuenta->nombres }} {{ trim($cuenta->apellido_paterno.' '.$cuenta->apellido_materno) }}</h1><p>Código <span class="pm-code">{{ $cuenta->codigo_universitario }}</span> · {{ $cuenta->creditos_maximos }} créditos máximos. Los datos del estudiante no se editan desde aquí.</p></div>
        <a class="pm-button is-secondary" href="{{ route('pre-matricula.coordinacion.cuentas') }}">Volver</a>
    </header>

    <section class="quality-panel" aria-labelledby="cuenta-titulo">
        <header class="quality-panel-heading"><div><h2 id="cuenta-titulo">Acceso</h2></div>@include('modules.pre-matricula.partials.estado', ['valor' => mb_strtoupper($cuenta->estado)])</header>
        <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.cuentas.update', $cuenta->id_estudiante) }}">
            @csrf @method('PUT')
            <label class="pm-field"><span>Correo institucional</span><input type="email" name="correo_institucional" maxlength="255" value="{{ old('correo_institucional', $cuenta->correo_institucional) }}" required></label>
            <label class="pm-field"><span>Nueva contraseña</span><input type="password" name="password" minlength="8" autocomplete="new-password"><small>Déjala vacía para no cambiarla.</small></label>
            <label class="pm-field"><span>Estado</span>
                <select name="estado">
                    <option value="Activo" @selected(mb_strtoupper($cuenta->estado) !== 'INACTIVO')>Activa</option>
                    <option value="Inactivo" @selected(mb_strtoupper($cuenta->estado) === 'INACTIVO')>Inactiva</option>
                </select>
            </label>
            <button class="pm-button" type="submit">Guardar cambios</button>
        </form>
    </section>
@endsection
