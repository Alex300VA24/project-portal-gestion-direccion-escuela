@php use App\Modules\GestionPracticas\Support\Catalogos; @endphp
@extends('modules.gestion-practicas.layout')

@section('title', $convocatoria->titulo)
@section('page-label', $convocatoria->titulo)

@section('content')
    <header class="quality-page-heading">
        <div><h1>{{ $convocatoria->titulo }}</h1><p>{{ $empresa }}</p></div>
        @include('modules.gestion-practicas.partials.volver', ['ruta' => route('gestion-practicas.estudiante.convocatorias.index'), 'texto' => 'convocatorias'])
    </header>

    <div class="gp-stack">
        <section class="quality-panel gp-compact" aria-labelledby="detalle-titulo">
            <header class="quality-panel-heading"><div><h2 id="detalle-titulo">Detalle de la convocatoria</h2></div></header>
            <div class="quality-compact-list">
                <article><div><small>Tipo</small><strong>{{ Catalogos::TIPOS_PRACTICA[$convocatoria->tipo_practica] ?? $convocatoria->tipo_practica }}</strong></div></article>
                <article><div><small>Modalidad</small><strong>{{ Catalogos::MODALIDADES[$convocatoria->modalidad] ?? $convocatoria->modalidad }}</strong></div></article>
                <article><div><small>Vacantes</small><strong>{{ $convocatoria->vacantes }}</strong></div></article>
                <article><div><small>Cierre de postulación</small><strong>{{ $convocatoria->fecha_cierre->format('d/m/Y') }}</strong></div></article>
            </div>
            <div class="gp-body">
                <h3>Descripción</h3><p class="gp-text">{{ $convocatoria->descripcion }}</p>
                <h3>Requisitos</h3><p class="gp-text">{{ $convocatoria->requisitos }}</p>
            </div>
        </section>

        @if (! $vigente)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div><strong>Esta convocatoria ya no admite postulaciones</strong><span>Está cerrada o venció su fecha de cierre. Revise otras convocatorias vigentes.</span></div>
            </div>
        @elseif ($yaPostulo)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>Ya registró su postulación a esta convocatoria</strong>
                    <span>Cuando la empresa le responda, informe el resultado en <a class="gp-link" href="{{ route('gestion-practicas.estudiante.postulaciones.index') }}">Mis postulaciones</a>.</span>
                </div>
            </div>
        @elseif ($practicaActiva)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>No puede postular: ya tiene una práctica activa</strong>
                    <span>
                        Su práctica en {{ $empresaActiva ?? 'otra empresa' }} está «{{ str_replace('_', ' ', $practicaActiva->estado) }}». Podrá postular a otra convocatoria cuando la finalice.
                        <a class="gp-link" href="{{ route('gestion-practicas.estudiante.practicas.show', $practicaActiva) }}">Ver mi práctica</a>
                    </span>
                </div>
            </div>
            <div><button class="institutional-primary" type="button" disabled>Registrar mi postulación</button></div>
        @else
            <section class="quality-panel" x-data="{ postulo: false }" aria-labelledby="postular-titulo">
                <header class="quality-panel-heading">
                    <div><h2 id="postular-titulo">Cómo postular</h2><p>Son dos pasos: primero en la web de la empresa y después aquí.</p></div>
                </header>
                <ol class="gp-steps">
                    <li>
                        <span class="gp-paso" aria-hidden="true">1</span>
                        <div>
                            <h3>Postule en la web de la empresa</h3>
                            <p>La empresa recibe las postulaciones en su propio enlace; este portal no las envía por usted.</p>
                            <a class="institutional-primary" href="{{ $convocatoria->enlace_postulacion }}" target="_blank" rel="noopener">Ir a la web de la empresa<span class="sr-only"> (se abre en una pestaña nueva)</span></a>
                        </div>
                    </li>
                    <li class="is-pendiente">
                        <span class="gp-paso" aria-hidden="true">2</span>
                        <div>
                            <h3>Registre su postulación aquí</h3>
                            <p>Cuando haya postulado, regístrelo para llevar el control del proceso y avisar el resultado.</p>
                            <form method="POST" action="{{ route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria) }}">
                                @csrf
                                <label class="gp-check"><input type="checkbox" x-model="postulo"> Ya postulé en la web de la empresa</label>
                                <button class="institutional-secondary" x-bind:disabled="! postulo">Registrar mi postulación</button>
                            </form>
                        </div>
                    </li>
                </ol>
            </section>
        @endif
    </div>
@endsection
