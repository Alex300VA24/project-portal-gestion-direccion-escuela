@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.asesor.proyectos'), 'a' => 'Mis proyectos'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.asesor.proyectos') }}">Mis proyectos</a> › <a href="{{ route('tesis-proyecto-tesis.asesor.proyecto', $proyecto) }}">{{ $proyecto->codigo }}</a> › Verificación</p>
            <h1>Verificar con la rúbrica</h1>
            <p>{{ $proyecto->titulo }} · {{ collect($integrantes)->pluck('nombre')->implode(' y ') }}
                @if ($entrega) · versión v{{ $entrega->version }} ({{ $entrega->tipo === 'Final' ? 'versión final' : 'culminada' }}) @endif</p>
        </div>
        @if ($entrega)
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $entrega->uuid]) }}" target="_blank" rel="noopener">Abrir documento v{{ $entrega->version }}</a>
        @endif
    </div>

    @if ($abiertas->isNotEmpty())
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Tus observaciones abiertas</h2><span class="tz-muted tz-small">Deben quedar levantadas antes de pasar Turnitin</span></div>
            <ul class="tz-list">
                @foreach ($abiertas as $o)
                    <li>
                        <div class="tz-tags"><span class="tz-status is-{{ $o->estado === 'Respondida' ? 'info' : 'warning' }}">{{ $o->estado }}</span> <span class="tz-muted tz-small">v{{ $o->version_entrega }} · {{ $o->seccion_documento ?? 'General' }}</span></div>
                        <span>{{ $o->descripcion }}</span>
                        @if ($o->estado === 'Respondida')
                            <span class="tz-small"><strong>Respuesta:</strong> {{ $o->respuesta_estudiante }}</span>
                            <form method="POST" action="{{ route('tesis-proyecto-tesis.asesor.observaciones.levantar', [$proyecto, $o->uuid]) }}">@csrf<button class="tz-btn tz-btn-secondary tz-btn-sm" type="submit">Dar por levantada</button></form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (! $habilitado)
        <section class="tz-panel">
            @include('modules.tesis-proyecto-tesis.partials.vacio', [
                'titulo' => $entrega ? 'El proyecto no está en desarrollo con el asesor' : 'Aún no hay una versión final para verificar',
                'texto' => $entrega ? 'Estado actual: '.\App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado).'.' : 'Cuando el estudiante suba la versión final, o marques un avance como «proyecto culminado», podrás aplicar la rúbrica.',
            ])
        </section>
    @elseif ($registrada)
        @include('modules.tesis-proyecto-tesis.partials.rubrica-registrada')
        @if ($registrada->conforme)
            <div class="tz-actions"><a class="tz-btn tz-btn-primary" href="{{ route('tesis-proyecto-tesis.asesor.similitud', $proyecto) }}">Continuar con Turnitin y conformidad</a></div>
        @else
            <div class="tz-alert is-info">Esperando la nueva versión del estudiante para volver a verificar.</div>
        @endif
    @else
        @include('modules.tesis-proyecto-tesis.partials.rubrica', [
            'accionUrl' => route('tesis-proyecto-tesis.asesor.verificar.guardar', $proyecto),
            'modo' => 'verificacion',
            'rubricaVersion' => $rubrica->version,
        ])
    @endif
@endsection
