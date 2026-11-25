@extends('modules.pre-matricula.layout')

@section('title', 'Cuentas')
@section('page-label', 'Cuentas')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Cuentas de estudiantes</h1><p>Crea cuentas, cambia el correo, la contraseña o desactiva el acceso. Una cuenta inactiva no puede usar la prematrícula.</p></div>
    </header>

    <form class="pm-filters" method="GET" action="{{ route('pre-matricula.coordinacion.cuentas') }}" role="search">
        <label class="pm-field"><span>Buscar</span><input type="search" name="buscar" value="{{ $buscar }}" maxlength="100" placeholder="Código, nombre o correo"></label>
        <button class="pm-button" type="submit">Buscar</button>
    </form>

    <div class="pm-stack">
        <section class="quality-panel" aria-labelledby="cuentas-titulo">
            <header class="quality-panel-heading"><div><h2 id="cuentas-titulo">Estudiantes con cuenta</h2><p>{{ $cuentas->total() }} {{ Str::plural('cuenta', $cuentas->total()) }}</p></div></header>
            <div class="pm-table-wrap">
                <table class="pm-table">
                    <thead><tr><th>Código</th><th>Estudiante</th><th>Correo</th><th class="pm-num">Créditos máx.</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($cuentas as $c)
                            <tr>
                                <td><span class="pm-code">{{ $c->codigo_universitario }}</span></td>
                                <td>{{ trim($c->apellido_paterno.' '.$c->apellido_materno) }}, {{ $c->nombres }}</td>
                                <td>{{ $c->correo_institucional }}</td>
                                <td class="pm-num">{{ $c->creditos_maximos }}</td>
                                <td>@include('modules.pre-matricula.partials.estado', ['valor' => mb_strtoupper($c->estado)])</td>
                                <td><a class="pm-button is-secondary is-small" href="{{ route('pre-matricula.coordinacion.cuentas.edit', $c->id_estudiante) }}" aria-label="Editar la cuenta de {{ $c->nombres }}">Editar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="pm-muted">No hay cuentas para esta búsqueda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($cuentas->hasPages())
                <div class="pm-pagination">
                    <span>{{ $cuentas->firstItem() }}–{{ $cuentas->lastItem() }} de {{ $cuentas->total() }}</span>
                    <span class="pm-actions">
                        @if ($cuentas->previousPageUrl())<a class="pm-button is-secondary is-small" href="{{ $cuentas->previousPageUrl() }}">Anterior</a>@endif
                        @if ($cuentas->nextPageUrl())<a class="pm-button is-secondary is-small" href="{{ $cuentas->nextPageUrl() }}">Siguiente</a>@endif
                    </span>
                </div>
            @endif
        </section>

        <section class="quality-panel" aria-labelledby="nueva-titulo">
            <header class="quality-panel-heading"><div><h2 id="nueva-titulo">Nueva cuenta</h2><p>Se crea con el plan de estudios predeterminado.</p></div></header>
            <form class="pm-form pm-body" method="POST" action="{{ route('pre-matricula.coordinacion.cuentas.store') }}">
                @csrf
                <div class="pm-form-row">
                    <label class="pm-field"><span>Código universitario</span><input name="codigo_universitario" maxlength="30" value="{{ old('codigo_universitario') }}" required></label>
                    <label class="pm-field"><span>Correo institucional</span><input type="email" name="correo_institucional" maxlength="255" value="{{ old('correo_institucional') }}" required></label>
                </div>
                <div class="pm-form-row">
                    <label class="pm-field"><span>Nombres</span><input name="nombres" maxlength="100" value="{{ old('nombres') }}" required></label>
                    <label class="pm-field"><span>Apellidos</span><input name="apellidos" maxlength="150" value="{{ old('apellidos') }}" required></label>
                </div>
                <div class="pm-form-row">
                    <label class="pm-field"><span>Contraseña</span><input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
                    <label class="pm-field"><span>Créditos máximos</span><input type="number" name="creditos_maximos" min="1" value="{{ old('creditos_maximos') }}" placeholder="22"><small>Si lo dejas vacío se usan 22.</small></label>
                </div>
                <button class="pm-button" type="submit">Crear cuenta</button>
            </form>
        </section>
    </div>
@endsection
