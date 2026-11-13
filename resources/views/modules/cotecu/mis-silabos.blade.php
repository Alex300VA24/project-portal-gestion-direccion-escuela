@extends('layouts.cotecu')

@section('title', 'Mis sílabos')
@section('page-label', 'Mis sílabos')

@section('content')
    <div class="quality-page" x-data="{ subirOpen: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Mis sílabos</h1>
                <p>Sílabos subidos, en revisión, aprobados y observados, con el detalle de cada revisión.</p>
            </div>
            @if ($periodos->isNotEmpty())
                <form class="quality-period-form" method="GET" action="{{ route('cotecu.mis-silabos') }}">
                    <label for="periodo">Semestre</label>
                    <select id="periodo" name="periodo" onchange="this.form.submit()">
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->codigo }}" @selected($periodoSeleccionado?->codigo === $periodo->codigo)>{{ $periodo->codigo }}</option>
                        @endforeach
                    </select>
                    <button class="quality-open-link" type="button" @disabled(! $hayPeriodoActivo) @click="subirOpen = true">
                        <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" style="margin-right:7px"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                        Subir sílabo
                    </button>
                </form>
            @endif
        </header>

        @if ($hayPeriodoActivo)
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay sílabos en {{ $periodoSeleccionado?->codigo }}</h2>
                <p>Usa el botón <strong>Subir sílabo</strong> para enviar tu primer sílabo de este semestre. El estado de cada envío y el detalle de sus revisiones aparecerán aquí.</p>
            </section>
        @else
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                <h2>No hay semestre académico activo</h2>
                <p>La subida de sílabos se habilitará cuando el semestre académico pase a estado <strong>Activo</strong>. Puedes seguir consultando tu histórico en el selector de semestre.</p>
            </section>
        @endif

        <div class="quality-modal" x-cloak x-show="subirOpen" x-transition.opacity @keydown.escape.window="subirOpen = false">
            <div class="quality-modal-backdrop" @click="subirOpen = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="subir-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="subir-title">Sección en construcción</h2></div>
                    <button class="quality-icon-button" type="button" @click="subirOpen = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <p>Esta pantalla se habilitará en la siguiente entrega del módulo COTECU.</p>
                <p><span class="quality-period">{{ $periodoActivo?->codigo }}</span></p>
            </section>
        </div>
    </div>
@endsection
