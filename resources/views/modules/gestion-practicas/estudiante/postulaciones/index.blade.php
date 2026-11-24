@php use App\Modules\GestionPracticas\Models\Postulacion; @endphp
@extends('modules.gestion-practicas.layout')

@section('title', 'Mis postulaciones')
@section('page-label', 'Mis postulaciones')

@section('content')
    <header class="quality-page-heading">
        <div><h1>Mis postulaciones</h1><p>Cuando la empresa le responda, informe aquí si fue seleccionado para continuar con su práctica.</p></div>
    </header>

    @if ($practicaActiva)
        <div class="quality-alert gp-aviso" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
            <div>
                <strong>Tiene una práctica activa en {{ $empresaActiva ?? 'otra empresa' }}</strong>
                <span>
                    Mientras no la finalice no puede registrarse como seleccionado en otra convocatoria ni registrar otra práctica.
                    <a class="gp-link" href="{{ route('gestion-practicas.estudiante.practicas.show', $practicaActiva) }}">Ver mi práctica</a>
                </span>
            </div>
        </div>
    @endif

    <section class="quality-panel" aria-labelledby="lista-titulo">
        <header class="quality-panel-heading">
            <div><h2 id="lista-titulo">Postulaciones registradas</h2><p>{{ count($postulaciones) }} {{ count($postulaciones) === 1 ? 'postulación' : 'postulaciones' }}</p></div>
        </header>
        @forelse ($postulaciones as $postulacion)
            @php($convocatoria = $convocatorias[$postulacion->id_convocatoria] ?? null)
            <article class="quality-indicator-row">
                <div>
                    <span class="quality-code">{{ $empresas[$convocatoria?->id_empresa] ?? '—' }}</span>
                    <strong>{{ $convocatoria?->titulo ?? '—' }}</strong>
                    <small>Postulada el {{ $postulacion->postulado_en->format('d/m/Y') }}</small>
                </div>
                <div>@include('modules.gestion-practicas.partials.estado', ['estado' => $postulacion->estado])</div>
                <div class="gp-row-actions">
                    @if ($postulacion->estado === Postulacion::POSTULADO)
                        <form method="POST" action="{{ route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion) }}">
                            @csrf
                            <button class="quality-open-link" name="resultado" value="seleccionado" @disabled($practicaActiva !== null) @if ($practicaActiva) title="No disponible: tiene una práctica activa" @endif>Fui seleccionado</button>
                            <button class="quality-open-link" name="resultado" value="no_seleccionado">No fui seleccionado</button>
                            <button class="quality-open-link" name="resultado" value="desistido">Desistí</button>
                        </form>
                    @elseif ($postulacion->estado === Postulacion::SELECCIONADO && $postulacion->id_practica === null)
                        @if ($practicaActiva)
                            <button class="quality-open-link" type="button" disabled title="No disponible: tiene una práctica activa">Registrar mi práctica</button>
                        @else
                            <a class="quality-open-link" href="{{ route('gestion-practicas.estudiante.practicas.create', $postulacion) }}">Registrar mi práctica</a>
                        @endif
                    @elseif ($postulacion->id_practica !== null)
                        <a class="quality-open-link" href="{{ route('gestion-practicas.estudiante.practicas.show', $postulacion->id_practica) }}">Ver mi práctica</a>
                    @endif
                </div>
            </article>
        @empty
            @include('modules.gestion-practicas.partials.vacio', [
                'titulo' => 'Aún no tiene postulaciones',
                'texto' => 'Revise las convocatorias vigentes y registre su postulación para verla aquí.',
            ])
        @endforelse
    </section>
@endsection
