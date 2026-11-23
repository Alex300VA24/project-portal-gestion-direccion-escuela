@extends('layouts.cotecu')

@section('title', 'Inicio')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Inicio</h1>
                <p>Coordinación técnica curricular. Desde aquí se administrarán las listas de cotejo, los planes curriculares, los sílabos y sus revisiones.</p>
            </div>
        </header>

        @if ($indicador)
            <section class="quality-summary" aria-label="Sílabos aprobados antes del inicio del semestre">
                <div class="quality-score" style="--score: {{ $indicador['porcentaje'] }}">
                    <div>
                        <strong>{{ number_format($indicador['porcentaje'], 1) }}%</strong>
                        <span>aprobados antes del inicio</span>
                    </div>
                </div>
                <div>
                    <p><strong>{{ $indicador['aTiempo'] }} de {{ $indicador['aprobados'] }} sílabos aprobados antes del inicio del semestre {{ $indicador['periodo']->codigo }}</strong></p>
                    <p>Se compara la fecha de aprobación de cada sílabo Validado con el inicio del semestre.</p>
                </div>
                <dl class="quality-summary-facts">
                    <div><dt>Semestre</dt><dd>{{ $indicador['periodo']->codigo }}</dd></div>
                    <div><dt>Inicio</dt><dd>{{ $indicador['periodo']->fecha_inicio->format('d/m/Y') }}</dd></div>
                    <div><dt>Aprobados</dt><dd>{{ $indicador['aprobados'] }}</dd></div>
                </dl>
            </section>
        @else
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v3H9z"/><path d="M15 4.5h2.5A1.5 1.5 0 0 1 19 6v13.5A1.5 1.5 0 0 1 17.5 21h-11A1.5 1.5 0 0 1 5 19.5V6a1.5 1.5 0 0 1 1.5-1.5H9"/><path d="M9 12l2 2 4-4"/></svg>
                <h2>Aún no hay secciones disponibles</h2>
                <p>Las listas de cotejo, los planes curriculares, los sílabos y las revisiones se irán incorporando de a poco. El contenido aparecerá aquí cuando cada sección esté habilitada.</p>
            </section>
        @endif
    </div>
@endsection
