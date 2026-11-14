@extends('layouts.cotecu')

@section('title', 'Revisar sílabos')
@section('page-label', 'Revisar sílabos')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Revisar sílabos</h1>
                <p>Sílabos enviados a revisión junto con su lista de cotejo.</p>
            </div>
            <div class="quality-period-form">
                <span>Semestre Académico:</span>
                <span class="quality-period">{{ $periodoActual?->codigo ?? '—' }}</span>
            </div>
        </header>

        @if (session('exito'))
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>
                <div>
                    <strong>{{ session('exito') }}</strong>
                    <span>El sílabo quedó fuera de la cola de revisión.</span>
                </div>
            </div>
        @endif

        @if ($cola->isNotEmpty())
            <ul class="quality-document-list">
                @foreach ($cola as $revision)
                    <li>
                        <span class="quality-document-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="m9 14.5 2 2 4-4"/></svg>
                        </span>
                        <div>
                            <h2>{{ $revision->curso_nombre }}</h2>
                            <p>
                                <span class="quality-period">{{ $revision->periodo_codigo }}</span>
                                {{ $revision->curso_codigo }} · Ciclo {{ $revision->ciclo }}
                            </p>
                            <small>Profesor: {{ $revision->profesor_nombre }} · Versión {{ $revision->version }}</small>
                        </div>
                        <div class="quality-file-meta">
                            @if ($revision->estado_final === 'Validado')
                                <span class="quality-status is-success">{{ $revision->estado_final }}</span>
                            @elseif ($revision->estado_final === 'Observado')
                                <span class="quality-status is-danger">{{ $revision->estado_final }}</span>
                            @else
                                <span class="quality-status is-warning">{{ $revision->estado_final }}</span>
                            @endif
                            <strong>{{ $revision->archivo_url }}</strong>
                            <small>Revisión {{ $revision->id }}</small>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="m9 14.5 2 2 4-4"/></svg>
                <h2>No hay sílabos pendientes de revisión</h2>
                <p>Cuando un profesor envíe un sílabo quedará aquí en cola, con su lista de cotejo, hasta que se revise.</p>
            </section>
        @endif
    </div>
@endsection
