@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.jurado.proyectos'), 'a' => 'Proyectos asignados'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.jurado.proyectos') }}">Proyectos asignados</a> › {{ $proyecto->codigo }}</p>
            <h1>{{ $modo === 'Intermedia' ? 'Revisión intermedia (referencial)' : ($proyecto->etapa === 'Tesis' ? 'Revisar tesis con la rúbrica' : 'Revisar proyecto con la rúbrica') }}</h1>
            <p>{{ $proyecto->titulo }} · {{ collect($integrantes)->pluck('nombre')->implode(' y ') }}</p>
        </div>
        @if ($entrega)
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $entrega->uuid]) }}" target="_blank" rel="noopener">Ver documento (v{{ $entrega->version }})</a>
        @endif
    </div>

    <div class="tz-grid">
        @foreach ($miembros as $m)
            <article class="tz-card" @if ($m['id_docente'] === $idDocente) style="border-color: var(--tz-green)" @endif>
                <span class="tz-eyebrow">{{ $m['cargo'] }}{{ $m['id_docente'] === $idDocente ? ' · tú' : '' }}</span>
                <strong>{{ $m['nombre'] }}</strong>
                <span class="tz-status is-{{ $m['estado'] === 'Conforme' ? 'success' : ($m['estado'] === 'Sin iniciar' ? 'neutral' : 'warning') }}">
                    {{ $m['estado'] }}@if ($m['estado'] !== 'Sin iniciar') · {{ $m['cumple'] }} de {{ $m['total'] }}@endif
                </span>
            </article>
        @endforeach
    </div>
    <p class="tz-muted tz-small">El detalle de cada miembro es reservado: solo ves su avance.</p>

    @if ($abiertas->isNotEmpty())
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Tus observaciones abiertas</h2></div>
            <ul class="tz-list">
                @foreach ($abiertas as $o)
                    <li>
                        <div class="tz-tags"><span class="tz-status is-{{ $o->estado === 'Respondida' ? 'info' : 'warning' }}">{{ $o->estado }}</span> <span class="tz-muted tz-small">v{{ $o->version_entrega }} · {{ $o->seccion_documento ?? 'General' }}</span></div>
                        <span>{{ $o->descripcion }}</span>
                        @if ($o->estado === 'Respondida')
                            <span class="tz-small"><strong>Respuesta:</strong> {{ $o->respuesta_estudiante }}</span>
                            <form method="POST" action="{{ route('tesis-proyecto-tesis.jurado.observaciones.levantar', [$proyecto, $o->uuid]) }}">@csrf<button class="tz-btn tz-btn-secondary tz-btn-sm" type="submit">Dar por levantada</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($modo !== null && $entrega)
        @include('modules.tesis-proyecto-tesis.partials.rubrica', [
            'accionUrl' => route('tesis-proyecto-tesis.jurado.verificar.guardar', $proyecto),
            'modo' => $modo === 'Intermedia' ? 'intermedia' : 'verificacion',
            'rubricaVersion' => $rubrica->version,
        ])
    @elseif ($ultima && $ultima->conforme)
        <div class="tz-alert is-success">Diste tu conformidad el {{ $ultima->revisado_en->format('d/m/Y') }} (versión v{{ $ultima->version_entrega }}).</div>
    @elseif ($ultima && $proyecto->estado === 'EnRevisionJurado')
        <div class="tz-alert is-info">Registraste observaciones en la versión v{{ $ultima->version_entrega }}. Podrás verificar de nuevo cuando los estudiantes suban la versión corregida.</div>
    @else
        <section class="tz-panel">
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay revisiones pendientes', 'texto' => 'Estado del proyecto: '.\App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado).'. La revisión intermedia se habilita desde la semana '.\App\Modules\TesisProyectoTesis\Support\Parametros::SEMANA_REVISION_INTERMEDIA.' de la etapa de Tesis.'])
        </section>
    @endif
@endsection
