@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.asesor.proyectos'), 'a' => 'Mis proyectos'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.asesor.proyectos') }}">Mis proyectos</a> › {{ $proyecto->codigo }}</p>
            <h1>Detalle del proyecto</h1>
        </div>
        <div class="tz-actions">
            @if (app('router')->has('tesis-proyecto-tesis.asesor.verificar') && in_array($proyecto->estado, \App\Modules\TesisProyectoTesis\Support\Estados::DESARROLLO, true))
                <a class="tz-btn tz-btn-navy" href="{{ route('tesis-proyecto-tesis.asesor.verificar', $proyecto) }}">Verificar con rúbrica</a>
            @endif
            @if (app('router')->has('tesis-proyecto-tesis.asesor.similitud') && in_array($proyecto->estado, \App\Modules\TesisProyectoTesis\Support\Estados::DESARROLLO, true))
                <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.asesor.similitud', $proyecto) }}">Turnitin y conformidad</a>
            @endif
        </div>
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    <div class="tz-grid-2">
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Entregas</h2></div>
            @if ($entregas->isEmpty())
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin entregas', 'texto' => 'Los estudiantes aún no suben avances.'])
            @else
                <div class="tz-table-wrap"><table class="tz-table">
                    <thead><tr><th scope="col">Versión</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                    <tbody>
                        @foreach ($entregas as $e)
                            <tr>
                                <td>v{{ $e->version }} · {{ $e->etapa }} · {{ $e->tipo === 'Final' ? 'Versión final' : 'Semana '.$e->numero_semana }}</td>
                                <td>{{ $e->entregado_en->format('d/m/Y H:i') }}</td>
                                <td>@include('modules.tesis-proyecto-tesis.partials.entrega-estado', ['estado' => $e->estado, 'n' => $e->observaciones_count])</td>
                                <td><a href="{{ route('tesis-proyecto-tesis.asesor.revisar', [$proyecto, $e]) }}">{{ $e->estado === 'Pendiente' ? 'Revisar' : 'Ver revisión' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Historial</h2></div>
            <div class="tz-panel-body">@include('modules.tesis-proyecto-tesis.partials.linea-tiempo')</div>
        </section>
    </div>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Observaciones del proyecto</h2></div>
        @if ($observaciones->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin observaciones registradas'])
        @else
            <ul class="tz-list">
                @foreach ($observaciones as $o)
                    <li>
                        @include('modules.tesis-proyecto-tesis.partials.observacion')
                        @if ($o->estado === 'Respondida' && $o->rol_revisor === 'Asesor')
                            <form method="POST" action="{{ route('tesis-proyecto-tesis.asesor.observaciones.levantar', [$proyecto, $o->uuid]) }}">
                                @csrf
                                <button class="tz-btn tz-btn-secondary tz-btn-sm" type="submit">Dar por levantada</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @include('modules.tesis-proyecto-tesis.partials.detalle-f1')
@endsection
