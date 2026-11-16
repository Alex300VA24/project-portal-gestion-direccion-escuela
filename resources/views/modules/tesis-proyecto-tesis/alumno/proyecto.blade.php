@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Inicio</a> › Mi proyecto</p>
            <h1>Datos generales del proyecto (F1)</h1>
        </div>
        @if ($proyecto->id_archivo_documento)
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $proyecto->uuid]) }}" target="_blank" rel="noopener">Ver documento F1 (PDF)</a>
        @endif
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Datos de la investigación</h2></div>
        <div class="tz-panel-body">
            <p>{{ $proyecto->resumen }}</p>
            <dl class="tz-facts">
                <div><dt>Enfoque</dt><dd>{{ $proyecto->enfoque ?? '—' }}</dd></div>
                <div><dt>Tipo por el fin</dt><dd>{{ $proyecto->tipo_investigacion ?? '—' }}</dd></div>
                <div><dt>Diseño</dt><dd>{{ $proyecto->diseno ?? '—' }}</dd></div>
                <div><dt>Lugar de ejecución</dt><dd>{{ $proyecto->lugar_ejecucion ?? '—' }}</dd></div>
                <div><dt>Institución</dt><dd>{{ $proyecto->institucion ?? '—' }}</dd></div>
                <div><dt>Duración</dt><dd>{{ $proyecto->duracion_meses ? $proyecto->duracion_meses.' meses' : '—' }}</dd></div>
                <div><dt>Financiamiento</dt><dd>{{ $proyecto->financiamiento ?? '—' }}</dd></div>
                <div><dt>Registrado</dt><dd>{{ $proyecto->registrado_en?->format('d/m/Y') }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Cronograma de trabajo</h2></div>
        @if ($proyecto->actividades->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin actividades'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Etapa</th><th scope="col">Inicio</th><th scope="col">Término</th><th scope="col">Horas/sem.</th></tr></thead>
                <tbody>
                    @foreach ($proyecto->actividades as $a)
                        <tr><td>{{ $a->descripcion }}</td><td>{{ $a->fecha_inicio->format('d/m/Y') }}</td><td>{{ $a->fecha_fin->format('d/m/Y') }}</td><td>{{ $a->horas_semanales ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>

    <div class="tz-grid-2">
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Presupuesto por partidas</h2></div>
            @if ($proyecto->partidas->isEmpty())
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin partidas registradas'])
            @else
                <div class="tz-table-wrap"><table class="tz-table">
                    <thead><tr><th scope="col">Partida</th><th scope="col">Concepto</th><th scope="col" style="text-align:right">Monto (S/)</th></tr></thead>
                    <tbody>
                        @foreach ($proyecto->partidas as $p)
                            <tr><td class="tz-code">{{ $p->codigo_clasificador ?? '—' }}</td><td>{{ $p->descripcion }}</td><td style="text-align:right">{{ number_format($p->monto(), 2) }}</td></tr>
                        @endforeach
                        <tr><td></td><td><strong>Total</strong></td><td style="text-align:right"><strong>{{ number_format($proyecto->partidas->sum(fn ($p) => $p->monto()), 2) }}</strong></td></tr>
                    </tbody>
                </table></div>
            @endif
        </section>
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Recursos y colaboradores</h2></div>
            <div class="tz-panel-body" style="display: grid; gap: 12px">
                @forelse ($proyecto->recursos as $r)
                    <div><span class="tz-status is-neutral">{{ $r->tipo }}</span> {{ $r->descripcion }} @if ($r->cantidad) <span class="tz-muted">({{ rtrim(rtrim($r->cantidad, '0'), '.') }})</span> @endif</div>
                @empty
                    <p class="tz-muted">Sin recursos registrados.</p>
                @endforelse
                @foreach ($proyecto->colaboradores as $c)
                    <div><span class="tz-status is-info">Colaborador</span> {{ $c->nombres_completos }} · {{ $c->rol_colaboracion }} @if ($c->institucion) <span class="tz-muted">({{ $c->institucion }})</span> @endif</div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
