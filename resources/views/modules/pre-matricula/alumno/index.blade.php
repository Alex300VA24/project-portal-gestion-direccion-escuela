@extends('modules.pre-matricula.layout')

@section('title', 'Mi prematrícula')
@section('page-label', 'Mi prematrícula')

@php
    $solicitud = $solicitud ?? null;
    $ventana = $ventana ?? null;
    $editable = $editable ?? false;
    $seleccion = $seleccion ?? collect();
    $disponibles = $disponibles ?? collect();
    $creditos = $creditos ?? 0;
    $maximos = $creditos_maximos ?? 22;
    $validados = collect($solicitud?->validacion?->cursos ?? [])->keyBy('id_curso_plan');
    $persona = $estudiante->persona;
    $formato = fn ($fecha) => $fecha?->timezone('America/Lima')->translatedFormat('d M Y, H:i');
    $num = fn ($valor) => rtrim(rtrim(number_format((float) $valor, 1, '.', ''), '0'), '.');
@endphp

@section('content')
    <header class="quality-page-heading">
        <div>
            <span class="quality-eyebrow">{{ $estudiante->codigo_universitario }}</span>
            <h1>Mi prematrícula</h1>
            <p>{{ trim(($persona?->nombres ?? '').' '.($persona?->apellido_paterno ?? '').' '.($persona?->apellido_materno ?? '')) }}. Elige los cursos que piensas llevar el próximo período. No es la matrícula definitiva: sirve para planificar la oferta.</p>
        </div>
        @if ($periodo)<span class="quality-period">Período {{ $periodo->codigo }}</span>@endif
    </header>

    @if ($aviso ?? null)
        <div class="pm-flash is-warning" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><div><strong>No se puede abrir la prematrícula</strong>{{ $aviso }}</div></div>
    @elseif ($periodo === null)
        <section class="quality-panel"><div class="quality-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg><h2>No hay un período habilitado</h2><p>Cuando la escuela habilite el próximo período y abra la ventana de prematrícula, podrás registrar tus cursos aquí.</p></div></section>
    @else
        @if ($ventana)
            <div class="pm-flash is-neutral" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><div><strong>Ventana {{ Str::lower($ventana->tipo) }} abierta</strong>Hasta el {{ $formato($ventana->fecha_fin) }} (hora de Lima).</div></div>
        @endif

        @if ($solicitud === null)
            <section class="quality-panel">
                <div class="quality-empty">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>
                    <h2>La ventana de prematrícula está cerrada</h2>
                    <p>La escuela publicará las fechas de la ventana ordinaria del período {{ $periodo->codigo }}.</p>
                </div>
            </section>
        @elseif ($solicitud->estado === 'ENVIADA')
            <section class="quality-panel">
                <header class="quality-panel-heading"><div><h2>Prematrícula registrada</h2><p>Registrada el {{ $formato($solicitud->enviada_en) }} · revisión {{ $solicitud->revisionVigente?->numero_revision }}</p></div>@include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->validacion?->estado ?? 'ENVIADA'])</header>
                @include('modules.pre-matricula.alumno.partials.seleccion', ['editable' => false])
                <div class="pm-body pm-actions">
                    @if ($solicitud->revisionVigente)
                        <a class="pm-button" href="{{ route('pre-matricula.constancia', $solicitud->revisionVigente->id_revision) }}" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Descargar constancia</a>
                    @endif
                    @if ($puede_corregir)
                        <form method="POST" action="{{ route('pre-matricula.alumno.corregir') }}" x-data @submit="if (! confirm('Se abrirá una corrección. Tu constancia actual seguirá vigente hasta que vuelvas a registrar. ¿Continuar?')) $event.preventDefault()">@csrf<button class="pm-button is-secondary" type="submit">Corregir prematrícula</button></form>
                    @else
                        <span class="pm-muted">Las correcciones solo se pueden hacer durante una ventana extraordinaria.</span>
                    @endif
                </div>
            </section>
            @if ($solicitud->validacion?->estado && $solicitud->validacion->estado !== 'VALIDA')
                <div class="pm-flash is-warning pm-mt" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg><div><strong>Revisa tu selección</strong>Con los últimos resultados oficiales, algunos cursos ya no cumplen sus prerrequisitos o siguen pendientes de notas. Si se abre una ventana extraordinaria, podrás corregir.</div></div>
            @endif
        @elseif (! $editable)
            <section class="quality-panel">
                <header class="quality-panel-heading"><div><h2>Prematrícula en borrador</h2><p>La ventana está cerrada, así que no puedes modificarla ni registrarla.</p></div>@include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->id_cronograma_correccion ? 'CORRECCION' : 'BORRADOR'])</header>
                @include('modules.pre-matricula.alumno.partials.seleccion', ['editable' => false])
                @if ($solicitud->revisionVigente)
                    <div class="pm-body"><a class="pm-button is-secondary" href="{{ route('pre-matricula.constancia', $solicitud->revisionVigente->id_revision) }}" target="_blank" rel="noopener">Ver constancia vigente</a></div>
                @endif
            </section>
        @else
            <dl class="pm-facts">
                <div><dt>Créditos elegidos</dt><dd>{{ $num($creditos) }} <small>de {{ $maximos }}</small></dd></div>
                <div><dt>Cursos</dt><dd>{{ $seleccion->count() }}</dd></div>
                <div><dt>Estado</dt><dd>@include('modules.pre-matricula.partials.estado', ['valor' => $solicitud->id_cronograma_correccion ? 'CORRECCION' : 'BORRADOR'])</dd></div>
            </dl>
            <div class="pm-meter {{ $creditos > $maximos ? 'is-over' : '' }}" role="meter" aria-label="Créditos elegidos" aria-valuemin="0" aria-valuemax="{{ $maximos }}" aria-valuenow="{{ $num($creditos) }}"><i style="width: {{ min(100, $maximos > 0 ? $creditos / $maximos * 100 : 0) }}%"></i></div>

            <div class="pm-grid pm-mt">
                <section class="quality-panel" x-data="{ ciclo: 'todos', buscar: '' }" aria-labelledby="disponibles-titulo">
                    <header class="quality-panel-heading"><div><h2 id="disponibles-titulo">Cursos disponibles</h2><p>Solo aparecen cursos cuyos prerrequisitos aprobaste o estás llevando.</p></div></header>
                    <div class="pm-body">
                        <label class="pm-field"><span>Buscar curso</span><input type="search" x-model="buscar" placeholder="Código o nombre"></label>
                        @if ($ciclos->count() > 1)
                            <div class="pm-tabs pm-mt" role="group" aria-label="Filtrar por ciclo">
                                <button type="button" @click="ciclo = 'todos'" :aria-pressed="(ciclo === 'todos').toString()">Todos</button>
                                @foreach ($ciclos as $c)<button type="button" @click="ciclo = '{{ $c }}'" :aria-pressed="(ciclo === '{{ $c }}').toString()">Ciclo {{ $c }}</button>@endforeach
                            </div>
                        @endif
                    </div>
                    @forelse ($disponibles as $curso)
                        <article class="pm-course" x-show="(ciclo === 'todos' || ciclo === '{{ $curso->ciclo }}') && (buscar === '' || @js(Str::lower($curso->codigo.' '.$curso->nombre)).includes(buscar.toLowerCase()))">
                            <div>
                                <span class="pm-code">{{ $curso->codigo }}</span><strong>{{ $curso->nombre }}</strong>
                                <div class="pm-meta">
                                    <span class="pm-chip">Ciclo {{ $curso->ciclo }}</span><span class="pm-chip">{{ $num($curso->creditos) }} créditos</span>
                                    @if (($curso->evaluacion['estado'] ?? null) === 'PENDIENTE_RESULTADOS')@include('modules.pre-matricula.partials.estado', ['valor' => 'PENDIENTE_RESULTADOS'])@endif
                                </div>
                                @if ($curso->prerrequisitos)<small>Prerrequisitos: {{ collect($curso->prerrequisitos)->pluck('codigo')->join(', ') }}</small>@endif
                            </div>
                            <form method="POST" action="{{ route('pre-matricula.alumno.agregar') }}">@csrf<input type="hidden" name="id_curso_plan" value="{{ $curso->id_curso_plan }}"><button class="pm-button is-secondary is-small" type="submit" @disabled($creditos + $curso->creditos > $maximos) aria-label="Agregar {{ $curso->nombre }}">Agregar</button></form>
                        </article>
                    @empty
                        <p class="quality-list-empty pm-body">No tienes más cursos disponibles para este período.</p>
                    @endforelse
                </section>

                <section class="quality-panel" aria-labelledby="seleccion-titulo" x-data="{ confirmar: false }">
                    <header class="quality-panel-heading"><div><h2 id="seleccion-titulo">Tu selección</h2><p>{{ $seleccion->count() }} cursos · {{ $num($creditos) }} créditos</p></div></header>
                    @include('modules.pre-matricula.alumno.partials.seleccion', ['editable' => true])
                    <div class="pm-body">
                        <button class="pm-button" type="button" style="width: 100%" @click="confirmar = true" @disabled($seleccion->isEmpty())>Registrar prematrícula</button>
                        <div x-cloak x-show="confirmar" class="pm-flash is-neutral pm-mt" role="alertdialog" aria-labelledby="confirmar-titulo">
                            <div>
                                <strong id="confirmar-titulo">¿Registrar {{ $seleccion->count() }} cursos ({{ $num($creditos) }} créditos)?</strong>
                                Se generará tu constancia en PDF. Después solo podrás cambiarla en una ventana extraordinaria.
                                <div class="pm-actions pm-mt">
                                    <form method="POST" action="{{ route('pre-matricula.alumno.registrar') }}">@csrf<button class="pm-button" type="submit">Sí, registrar</button></form>
                                    <button class="pm-button is-secondary" type="button" @click="confirmar = false">Cancelar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @endif
    @endif
@endsection
