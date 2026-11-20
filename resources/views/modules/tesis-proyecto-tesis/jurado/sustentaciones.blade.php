@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Jurado</p>
            <h1>Sustentaciones</h1>
            <p>Al terminar la sustentación, registra tu resultado: aprobatorio o desaprobatorio (sin nota).</p>
        </div>
    </div>

    <section class="tz-panel">
        @if ($sustentaciones->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin sustentaciones programadas'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Proyecto</th><th scope="col">Fecha</th><th scope="col">Aula</th><th scope="col">Estado</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                    @foreach ($sustentaciones as $s)
                        <tr>
                            <td><strong>{{ $s->proyecto->titulo }}</strong><div class="tz-code tz-small">{{ $s->proyecto->codigo }} · intento {{ $s->numero_intento }}</div></td>
                            <td>{{ $s->fecha_hora->format('d/m/Y H:i') }}</td>
                            <td>{{ $s->lugar }}</td>
                            <td>
                                @if ($s->resultado)
                                    <span class="tz-status is-{{ $s->resultado === 'Aprobado' ? 'success' : 'danger' }}">{{ $s->resultado }}</span>
                                @else
                                    <span class="tz-status is-{{ $s->registrado ? 'success' : 'warning' }}">{{ $s->registrado ? 'Registraste tu resultado' : 'Por registrar' }}</span>
                                @endif
                            </td>
                            <td><a class="tz-btn tz-btn-sm {{ $s->estado === 'Programada' && ! $s->registrado ? 'tz-btn-navy' : 'tz-btn-secondary' }}" href="{{ route('tesis-proyecto-tesis.jurado.resultado', $s) }}">{{ $s->estado === 'Programada' && ! $s->registrado ? 'Registrar resultado' : 'Ver' }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
