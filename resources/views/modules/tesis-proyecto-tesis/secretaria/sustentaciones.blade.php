@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Sustentaciones</p>
            <h1>Sustentaciones</h1>
            <p>Solo se programan las tesis con la conformidad de los tres jurados en la revisión final, o las que deben volver a sustentarse.</p>
        </div>
    </div>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Por programar</h2></div>
        @if ($pendientes->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay tesis por programar'])
        @else
            <ul class="tz-list">
                @foreach ($pendientes as $p)
                    <li>
                        <div class="tz-tags"><strong class="tz-code">{{ $p->codigo }}</strong> @include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $p->estado])</div>
                        <span>{{ $p->titulo }}</span>
                        <div><a class="tz-btn tz-btn-navy tz-btn-sm" href="{{ route('tesis-proyecto-tesis.secretaria.sustentaciones.programar', $p) }}">Programar<span class="sr-only"> · {{ $p->codigo }}</span></a></div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Programadas y realizadas</h2></div>
        @if ($recientes->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin sustentaciones registradas'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Proyecto</th><th scope="col">Fecha y hora</th><th scope="col">Intento</th><th scope="col">Estado</th><th scope="col">Resultado</th></tr></thead>
                <tbody>
                    @foreach ($recientes as $s)
                        <tr>
                            <td class="tz-code">{{ $s->proyecto->codigo }}</td>
                            <td>{{ $s->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td>{{ $s->numero_intento }}</td>
                            <td><span class="tz-status is-{{ $s->estado === 'Programada' ? 'info' : 'neutral' }}">{{ $s->estado }}</span></td>
                            <td>
                                @if ($s->resultado)
                                    <span class="tz-status is-{{ $s->resultado === 'Aprobado' ? 'success' : 'danger' }}">
                                        {{ $s->resultado === 'Aprobado' ? 'Aprobado por '.($s->modo_resultado === 'Unanimidad' ? 'unanimidad' : 'mayoría').($s->mencion_honrosa ? ' · mención honrosa' : '') : 'Desaprobado' }}
                                        ({{ $s->total_aprobatorios }}/3)
                                    </span>
                                @else
                                    <span class="tz-muted">Pendiente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
