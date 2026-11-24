@extends('layouts.cotecu')

@section('title', 'Revisar planes')
@section('page-label', 'Revisar planes')

@section('content')
    <div class="quality-page">
        <header class="quality-page-heading">
            <div>
                <h1>Revisar planes curriculares</h1>
                <p>Planes subidos a revisión junto con su lista de cotejo.</p>
            </div>
            <div class="quality-period-form">
                <span>Plan Curricular:</span>
                <span class="quality-period">{{ $anioPlan ?? '—' }}</span>
            </div>
        </header>

        @if ($errors->any())
            <div class="quality-alert" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>No se pudo procesar la revisión</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            </div>
        @endif

        @if (session('exito'))
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>
                <div>
                    <strong>{{ session('exito') }}</strong>
                    <span>El plan quedó fuera de la cola de revisión.</span>
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
                            <h2>{{ $revision->nombre }}</h2>
                            <p>
                                <span class="quality-period">{{ $revision->plan_codigo }}</span>
                                Versión {{ $revision->version }} · Año {{ $revision->plan_anio }}
                            </p>
                        </div>
                        <div class="quality-file-meta">
                            <a class="institutional-primary" href="{{ route('cotecu.planes.revisiones.show', $revision->id) }}">Revisar</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="m9 14.5 2 2 4-4"/></svg>
                <h2>No hay planes pendientes de revisión</h2>
                <p>Cuando el director de COTECU suba un plan curricular quedará aquí en cola, con su lista de cotejo, hasta que se revise.</p>
            </section>
        @endif
    </div>
@endsection
