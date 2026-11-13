@extends('layouts.cotecu')

@section('title', 'Sílabos')
@section('page-label', 'Sílabos')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Sílabos</h1>
                <p>Listado de sílabos del módulo COTECU, filtrado por semestre académico.</p>
            </div>
            @if ($periodos->isNotEmpty())
                <form class="quality-period-form" method="GET" action="{{ route('cotecu.silabos.index') }}">
                    <label for="periodo">Semestre</label>
                    <select id="periodo" name="periodo" onchange="this.form.submit()">
                        @foreach ($periodos as $periodo)
                            <option value="{{ $periodo->codigo }}" @selected($periodoSeleccionado?->codigo === $periodo->codigo)>{{ $periodo->codigo }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </header>

        <section class="quality-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
            <h2>Aún no hay sílabos</h2>
            <p>Los sílabos que los docentes suban para el semestre seleccionado aparecerán aquí junto con su estado de revisión.</p>
        </section>
    </div>
@endsection
