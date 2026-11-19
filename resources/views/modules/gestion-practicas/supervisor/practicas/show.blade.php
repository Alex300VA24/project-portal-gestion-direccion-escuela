@php
    use App\Modules\GestionPracticas\Models\Practica;

    $enCurso = $practica->estado === Practica::EN_CURSO;
@endphp
@extends('modules.gestion-practicas.layout')

@section('title', 'Práctica de '.$estudiante)
@section('page-label', 'Práctica de '.$estudiante)

@section('content')
    <header class="quality-page-heading">
        <div><h1>Práctica de {{ $estudiante }}</h1><p>{{ $empresa }}</p></div>
        <div class="gp-actions">
            @if ($notaFinal !== null)
                <dl class="quality-inline-stats" aria-label="Nota calculada"><div><dt>Nota final calculada</dt><dd>{{ number_format($notaFinal, 2) }}</dd></div></dl>
            @endif
            @include('modules.gestion-practicas.partials.estado', ['estado' => $practica->estado])
        </div>
    </header>

    <div class="gp-stack">
        <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
            <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Datos de la práctica</h2></div></header>
            <div class="quality-compact-list">
                <article><div><small>Área</small><strong>{{ $practica->area }}</strong></div></article>
                <article><div><small>Horas planificadas</small><strong>{{ $practica->horas_totales }}</strong></div></article>
                <article><div><small>Inicio</small><strong>{{ $practica->fecha_inicio->format('d/m/Y') }}</strong></div></article>
                <article><div><small>Fin</small><strong>{{ $practica->fecha_fin->format('d/m/Y') }}</strong></div></article>
                @if ($practica->estado === Practica::FINALIZADA)
                    <article><div><small>Nota final</small><strong>{{ $practica->nota_final }} ({{ $practica->resultado }})</strong></div></article>
                @endif
            </div>
            <div class="gp-body"><h3>Funciones</h3><p class="gp-text">{{ $practica->funciones }}</p></div>
        </section>

        <section class="quality-panel" aria-labelledby="docs-titulo">
            <header class="quality-panel-heading"><div><h2 id="docs-titulo">Documentos</h2><p>Carta de aceptación e informes. Apruebe el informe final para poder cerrar la práctica.</p></div></header>
            @include('modules.gestion-practicas.partials.documentos', ['documentos' => $documentos, 'puedeRevisar' => true])
        </section>

        <section class="quality-panel" aria-labelledby="seg-titulo">
            <header class="quality-panel-heading"><div><h2 id="seg-titulo">Seguimiento</h2><p>Reuniones, llamadas y visitas con el estudiante.</p></div></header>
            @if ($enCurso)
                <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.seguimiento', $practica) }}" class="gp-form is-two">
                    @csrf
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'fecha', 'label' => 'Fecha', 'tipo' => 'date', 'valor' => now()->toDateString(), 'extra' => ['max' => now()->toDateString()]])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'tipo', 'label' => 'Tipo', 'tipo' => 'select', 'opciones' => $tiposSeguimiento])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'descripcion', 'label' => 'Descripción', 'tipo' => 'textarea', 'filas' => 3, 'span' => true])
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'observaciones', 'label' => 'Observaciones (opcional)', 'tipo' => 'textarea', 'filas' => 2, 'requerido' => false, 'span' => true])
                    <div class="gp-span"><button class="institutional-primary">Registrar seguimiento</button></div>
                </form>
            @endif
            @if ($seguimientos->isEmpty())
                @include('modules.gestion-practicas.partials.vacio', [
                    'titulo' => 'Todavía no hay seguimientos registrados',
                    'texto' => 'Cada reunión o contacto con el estudiante que registre aparecerá aquí.',
                ])
            @else
                <ul class="gp-timeline">
                    @foreach ($seguimientos as $seguimiento)
                        <li>
                            <p>{{ $seguimiento->descripcion }}</p>
                            @if ($seguimiento->observaciones)<p>{{ $seguimiento->observaciones }}</p>@endif
                            <small>{{ $seguimiento->fecha->format('d/m/Y') }} · {{ str_replace('_', ' ', $seguimiento->tipo) }}</small>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="quality-panel" aria-labelledby="eval-titulo">
            <header class="quality-panel-heading"><div><h2 id="eval-titulo">Evaluación final</h2><p>Califique cada criterio de 0 a 20; la nota final es el promedio ponderado.</p></div></header>
            @if ($criterios->isEmpty())
                @include('modules.gestion-practicas.partials.vacio', [
                    'titulo' => 'No hay criterios de evaluación configurados',
                    'texto' => 'Pida al administrador del portal que cargue los criterios del módulo.',
                ])
            @else
                <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.evaluar', $practica) }}" class="gp-form">
                    @csrf
                    @foreach ($criterios as $criterio)
                        @php($campoNota = 'notas.'.$criterio->id_criterio)
                        <div class="gp-field">
                            <div class="gp-grade">
                                <label for="nota-{{ $criterio->id_criterio }}">{{ $criterio->nombre }} <small>({{ rtrim(rtrim($criterio->peso, '0'), '.') }}%)</small></label>
                                <input id="nota-{{ $criterio->id_criterio }}" name="notas[{{ $criterio->id_criterio }}]" type="number" step="0.01" min="0" max="20" class="gp-control"
                                       value="{{ old($campoNota, $notasActuales[$criterio->id_criterio] ?? '') }}" @disabled(! $enCurso) required
                                       @error($campoNota) aria-invalid="true" aria-describedby="nota-{{ $criterio->id_criterio }}-error" @enderror>
                            </div>
                            @error($campoNota)<p class="gp-error" id="nota-{{ $criterio->id_criterio }}-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    @include('modules.gestion-practicas.partials.campo', ['name' => 'comentario', 'label' => 'Observación final', 'tipo' => 'textarea', 'filas' => 3, 'valor' => $evaluacion?->comentario, 'requerido' => false, 'extra' => $enCurso ? [] : ['disabled' => true]])
                    @if ($enCurso)<div><button class="institutional-primary">Registrar nota</button></div>@endif
                </form>
            @endif
            @if ($enCurso)
                <form method="POST" action="{{ route('gestion-practicas.supervisor.practicas.cerrar', $practica) }}" class="gp-body gp-actions">
                    @csrf
                    <button class="institutional-secondary">Cerrar práctica</button>
                    <p class="gp-lead">Requiere la nota registrada y el informe final aprobado.</p>
                </form>
            @endif
        </section>
    </div>
@endsection
