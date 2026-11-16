@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Jurado</p>
            <h1>Proyectos asignados</h1>
            <p>Cada miembro revisa por separado con la rúbrica única. Se aprueba cuando los tres dan su conformidad.</p>
        </div>
    </div>

    <section class="tz-panel">
        @if ($filas->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin proyectos asignados', 'texto' => 'Aparecerán aquí cuando el Director te asigne como jurado.'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Proyecto</th><th scope="col">Tu cargo</th><th scope="col">Etapa</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @foreach ($filas as $f)
                        @php($p = $f['proyecto'])
                        <tr>
                            <td><strong>{{ $p->titulo }}</strong><div class="tz-muted tz-small tz-code">{{ $p->codigo }}</div></td>
                            <td>{{ $f['cargo'] }}</td>
                            <td>{{ $p->etapa === 'Tesis' ? 'Tesis · F2' : 'Proyecto · F1' }}</td>
                            <td>@include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $p->estado])</td>
                            <td>
                                @if ($f['modo'] === 'Verificacion')
                                    <a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ route('tesis-proyecto-tesis.jurado.verificar', $p) }}">{{ $p->etapa === 'Tesis' ? 'Revisión final' : 'Revisar con la rúbrica' }}</a>
                                @elseif ($f['modo'] === 'Intermedia')
                                    <a class="tz-btn tz-btn-secondary tz-btn-sm" href="{{ route('tesis-proyecto-tesis.jurado.verificar', $p) }}">Revisión intermedia</a>
                                @else
                                    <a href="{{ route('tesis-proyecto-tesis.jurado.verificar', $p) }}">{{ $f['conforme'] ? 'Conforme · ver' : 'Ver' }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
