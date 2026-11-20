@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Asesor</p>
            <h1>Mis proyectos asesorados</h1>
        </div>
        <form method="GET" class="tz-actions">
            <label for="etapa" class="tz-label">Etapa</label>
            <select id="etapa" name="etapa" class="tz-select" onchange="this.form.submit()" style="width: auto">
                <option value="">Todas las etapas</option>
                <option value="Proyecto" @selected($etapa === 'Proyecto')>Proyecto (F1)</option>
                <option value="Tesis" @selected($etapa === 'Tesis')>Tesis (F2)</option>
            </select>
            <noscript><button class="tz-btn tz-btn-secondary tz-btn-sm">Filtrar</button></noscript>
        </form>
    </div>

    <div class="tz-grid">
        <article class="tz-card tz-stat"><strong>{{ $cifras['proyectos'] }}</strong><span class="tz-muted">Proyectos asesorados</span></article>
        <article class="tz-card tz-stat"><strong>{{ $cifras['porRevisar'] }}</strong><span class="tz-muted">Avances por revisar</span></article>
        <article class="tz-card tz-stat"><strong>{{ $cifras['paraVerificar'] }}</strong><span class="tz-muted">Listos para verificar con la rúbrica</span></article>
        <article class="tz-card tz-stat"><strong>{{ $cifras['noAptos'] }}</strong><span class="tz-muted">Turnitin por encima del {{ \App\Modules\TesisProyectoTesis\Support\Parametros::SIMILITUD_MAXIMA }} %</span></article>
    </div>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Proyectos</h2></div>
        @if ($filas->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin proyectos asesorados', 'texto' => 'Aparecerán aquí cuando la Secretaría registre tu designación.'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Proyecto</th><th scope="col">Estudiantes</th><th scope="col">Etapa</th><th scope="col">Último avance</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @foreach ($filas as $f)
                        @php($p = $f['proyecto'])
                        <tr>
                            <td><strong>{{ $p->titulo }}</strong><div class="tz-muted tz-small"><span class="tz-code">{{ $p->codigo }}</span> · {{ $f['linea'] }}</div></td>
                            <td>@foreach ($f['estudiantes'] as $n)<div>{{ $n }}</div>@endforeach</td>
                            <td>{{ $p->etapa === 'Tesis' ? 'Tesis · F2' : 'Proyecto · F1' }}</td>
                            <td>
                                @if ($f['ultima'])
                                    {{ $f['ultima']->tipo === 'Final' ? 'Versión final' : 'Semana '.$f['ultima']->numero_semana }}
                                    <div class="tz-muted tz-small">{{ $f['ultima']->entregado_en->diffForHumans() }}</div>
                                @else
                                    <span class="tz-muted">Sin entregas</span>
                                @endif
                            </td>
                            <td>
                                @if ($f['porRevisar'] > 0)
                                    <span class="tz-status is-warning">Por revisar · {{ $f['porRevisar'] }}</span>
                                @elseif ($f['paraVerificar'])
                                    <span class="tz-status is-info">Culminado</span>
                                @else
                                    @include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $p->estado])
                                @endif
                            </td>
                            <td>
                                @if ($f['pendiente'])
                                    <a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ route('tesis-proyecto-tesis.asesor.revisar', [$p, $f['pendiente']]) }}">Revisar avance</a>
                                @elseif ($f['paraVerificar'] && app('router')->has('tesis-proyecto-tesis.asesor.verificar'))
                                    <a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ route('tesis-proyecto-tesis.asesor.verificar', $p) }}">Verificar con rúbrica</a>
                                @else
                                    <a href="{{ route('tesis-proyecto-tesis.asesor.proyecto', $p) }}">Ver detalle</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
