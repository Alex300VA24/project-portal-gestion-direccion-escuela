@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.director.panel') }}">Panel general</a> › Todos los proyectos</p>
            <h1>Todos los proyectos</h1>
        </div>
    </div>

    <form method="GET" class="tz-panel tz-panel-body tz-fields" style="align-items: end">
        <div class="tz-field">
            <label for="buscar">Buscar por código o título</label>
            <input id="buscar" name="buscar" class="tz-input" type="search" value="{{ $filtros['buscar'] ?? '' }}" maxlength="80">
        </div>
        <div class="tz-field">
            <label for="estado">Estado</label>
            <select id="estado" name="estado" class="tz-select">
                <option value="">Todos</option>
                @foreach (\App\Modules\TesisProyectoTesis\Support\Estados::ETIQUETAS as $clave => $etiqueta)
                    <option value="{{ $clave }}" @selected(($filtros['estado'] ?? '') === $clave)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="tz-actions"><button class="tz-btn tz-btn-navy" type="submit">Filtrar</button></div>
    </form>

    <section class="tz-panel">
        @if ($filas->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay proyectos con esos filtros'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Proyecto</th><th scope="col">Estudiantes</th><th scope="col">Asesor</th><th scope="col">Etapa</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acción</span></th></tr></thead>
                <tbody>
                    @foreach ($filas as $f)
                        @php($p = $f['proyecto'])
                        <tr>
                            <td><strong>{{ $p->titulo }}</strong><div class="tz-muted tz-small"><span class="tz-code">{{ $p->codigo }}</span> · {{ $f['linea'] }}</div></td>
                            <td>@foreach ($f['estudiantes'] as $n)<div>{{ $n }}</div>@endforeach</td>
                            <td>{{ $f['asesor'] ?? 'Sin asesor' }}</td>
                            <td>{{ $p->etapa }}</td>
                            <td>@include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $p->estado])</td>
                            <td>
                                @if ($p->estado === 'Registrado')
                                    <a href="{{ route('tesis-proyecto-tesis.director.asesor.solicitar', $p) }}">Solicitar asesor</a>
                                @elseif ($p->estado === 'VerificadoAsesor' && app('router')->has('tesis-proyecto-tesis.director.jurado'))
                                    <a href="{{ route('tesis-proyecto-tesis.director.jurado', $p) }}">Asignar jurado</a>
                                @elseif (app('router')->has('tesis-proyecto-tesis.director.jurado') && in_array($p->estado, ['EnRevisionJurado', 'Aprobado', 'EnTesis', 'ListoSustentar'], true))
                                    <a href="{{ route('tesis-proyecto-tesis.director.jurado', $p) }}">Ver jurado</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
