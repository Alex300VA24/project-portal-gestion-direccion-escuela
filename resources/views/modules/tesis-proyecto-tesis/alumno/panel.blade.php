@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Inicio</p>
            <h1>Mi proyecto de tesis</h1>
        </div>
    </div>

    @if ($proyecto === null)
        <section class="tz-panel">
            <div class="tz-empty">
                <div>
                    <svg class="tz-i" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M12 12v6M9 15h6"/></svg>
                    <strong>Aún no registras tu proyecto de tesis</strong>
                    <p>Registra los datos generales del formato F1 y adjunta el documento. Puedes presentarlo solo o con un compañero.</p>
                    <p style="margin-top: 16px"><a class="tz-btn tz-btn-primary" href="{{ route('tesis-proyecto-tesis.alumno.proyecto.registrar') }}">Registrar proyecto (F1)</a></p>
                </div>
            </div>
        </section>
    @else
        @include('modules.tesis-proyecto-tesis.partials.ficha')

        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Avance del proceso</h2></div>
            <div class="tz-panel-body">
                <ol class="tz-steps">
                    @foreach ($pasos as $i => $paso)
                        <li class="{{ $paso['clase'] }}" @if ($paso['clase'] === 'is-now') aria-current="step" @endif>
                            <span class="tz-dot">{{ $paso['clase'] === 'is-done' ? '✓' : $i + 1 }}</span>
                            <b>{{ $paso['titulo'] }}</b>
                            <span>{{ $paso['detalle'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <div class="tz-grid">
            <article class="tz-card">
                <span class="tz-eyebrow">Semana del ciclo</span>
                <strong style="font-size: 1.6rem; color: var(--tz-navy)">Semana {{ $semana }}</strong>
                @if (in_array($proyecto->estado, \App\Modules\TesisProyectoTesis\Support\Estados::PERMITEN_ENTREGA, true) && app('router')->has('tesis-proyecto-tesis.alumno.avances'))
                    <a href="{{ route('tesis-proyecto-tesis.alumno.avances') }}">Subir avance →</a>
                @elseif ($proyecto->estado === 'Registrado')
                    <span class="tz-muted">Podrás subir avances cuando se registre la resolución de tu asesor.</span>
                @endif
            </article>
            <article class="tz-card">
                <span class="tz-eyebrow">Observaciones pendientes</span>
                <strong style="font-size: 1.6rem; color: {{ $pendientes > 0 ? 'var(--tz-warning)' : 'var(--tz-navy)' }}">{{ $pendientes }}</strong>
                @if ($pendientes > 0 && app('router')->has('tesis-proyecto-tesis.alumno.observaciones'))
                    <a href="{{ route('tesis-proyecto-tesis.alumno.observaciones') }}">Levantar observaciones →</a>
                @else
                    <span class="tz-muted">No tienes observaciones por levantar.</span>
                @endif
            </article>
            <article class="tz-card">
                <span class="tz-eyebrow">Sustentación</span>
                @php($ultima = $sustentaciones->last())
                @if ($ultima)
                    <strong style="font-size: 1.2rem; color: var(--tz-navy)">{{ $ultima->fecha_hora->format('d/m/Y H:i') }}</strong>
                    <span class="tz-muted">Intento {{ $ultima->numero_intento }} · {{ $ultima->resultado ? ($ultima->resultado === 'Aprobado' ? 'Aprobada por '.($ultima->modo_resultado === 'Unanimidad' ? 'unanimidad' : 'mayoría') : 'Desaprobada') : $ultima->estado }}</span>
                @else
                    <strong style="font-size: 1.2rem; color: var(--tz-navy)">Sin programar</strong>
                    <span class="tz-muted">La Secretaría la programa cuando el jurado da su conformidad final.</span>
                @endif
            </article>
        </div>

        <div class="tz-grid-2">
            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Últimas entregas</h2></div>
                @if ($entregas->isEmpty())
                    @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin entregas todavía', 'texto' => 'Tus avances semanales aparecerán aquí con el estado de su revisión.'])
                @else
                    <div class="tz-table-wrap">
                        <table class="tz-table">
                            <thead><tr><th scope="col">Versión</th><th scope="col">Semana</th><th scope="col">Fecha</th><th scope="col">Estado</th></tr></thead>
                            <tbody>
                                @foreach ($entregas as $e)
                                    <tr>
                                        <td>v{{ $e->version }} · {{ $e->tipo === 'Final' ? 'Versión final' : 'Avance' }}</td>
                                        <td>{{ $e->numero_semana ?? '—' }}</td>
                                        <td>{{ $e->entregado_en->format('d/m/Y') }}</td>
                                        <td>@include('modules.tesis-proyecto-tesis.partials.entrega-estado', ['estado' => $e->estado, 'n' => $e->observaciones_count])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Historial</h2></div>
                <div class="tz-panel-body">@include('modules.tesis-proyecto-tesis.partials.linea-tiempo')</div>
            </section>
        </div>
    @endif
@endsection
