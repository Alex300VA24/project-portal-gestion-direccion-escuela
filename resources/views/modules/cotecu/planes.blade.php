@extends('layouts.cotecu')

@section('title', 'Planes curriculares')
@section('page-label', 'Planes curriculares')

@section('content')
    <div class="quality-page" x-data="{ subirOpen: {{ $errors->any() ? 'true' : 'false' }} }">
        <header class="quality-page-heading">
            <div>
                <h1>Planes curriculares</h1>
                <p>Documentos del plan de estudios y su estado de revisión. No se filtran por semestre: un plan se revisa una vez y vigila durante años.</p>
            </div>
            @if ($puedeSubir)
                <button class="institutional-primary" type="button" @click="subirOpen = true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                    Subir plan curricular
                </button>
            @endif
        </header>

        @if (session('exito'))
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/></svg>
                <div>
                    <strong>{{ session('exito') }}</strong>
                    <span>El documento queda a la espera de la revisión del director de escuela.</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="quality-alert" role="alert">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>No se pudo guardar el plan curricular</strong>
                    <span>{{ $errors->first() }}@if ($errors->count() > 1) (y {{ $errors->count() - 1 }} más.)@endif</span>
                </div>
            </div>
        @endif

        @if ($planes->isEmpty())
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay planes curriculares</h2>
                <p>Los planes subidos por el director de COTECU aparecerán aquí junto con su estado de revisión.</p>
            </section>
        @else
            <ul class="quality-document-list">
                @foreach ($planes as $plan)
                    <li>
                        <span class="quality-document-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg>
                        </span>
                        <div>
                            <h2>{{ $plan->nombre }}</h2>
                            <p>
                                <span class="quality-period">{{ $plan->plan_codigo }}</span>
                                Versión {{ $plan->version }}
                            </p>
                        </div>
                        <div class="quality-file-meta">
                            @if ($plan->estado === 'Validado')
                                <span class="quality-status is-success">{{ $plan->estado }}</span>
                            @elseif ($plan->estado === 'Observado')
                                <span class="quality-status is-danger">{{ $plan->estado }}</span>
                            @else
                                <span class="quality-status is-warning">{{ $plan->estado }}</span>
                            @endif
                            <strong>{{ $plan->archivo_url }}</strong>
                            <small>Plan {{ $plan->plan_nombre }}</small>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($puedeSubir)
        <div class="quality-modal" x-cloak x-show="subirOpen" x-transition.opacity @keydown.escape.window="subirOpen = false">
            <div class="quality-modal-backdrop" @click="subirOpen = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="subir-plan-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="subir-plan-title">Subir plan curricular</h2></div>
                    <button class="quality-icon-button" type="button" @click="subirOpen = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <form method="POST" action="{{ route('cotecu.planes.store') }}">
                    @csrf
                    <div class="quality-a11y-body">
                        <div class="quality-a11y-row">
                            <div>
                                <strong>Plan de estudios</strong>
                                <small>Plan aprobado al que pertenece el documento</small>
                            </div>
                            <span class="quality-select">
                                <select name="id_plan" required>
                                    <option value="">Seleccione un plan…</option>
                                    @foreach ($planesEstudio as $item)
                                        <option value="{{ $item->id_plan }}" @selected(old('id_plan') == $item->id_plan)>{{ $item->codigo }} · {{ $item->nombre }}</option>
                                    @endforeach
                                </select>
                            </span>
                        </div>
                        <div class="quality-a11y-row">
                            <div>
                                <strong>Versión</strong>
                                <small>Número correlativo del documento</small>
                            </div>
                            <span class="quality-search">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8l9 5 9-5-9-5z"/><path d="m3 13 9 5 9-5"/></svg>
                                <input type="number" name="version" min="1" step="1" required value="{{ old('version') }}" placeholder="Ej. 3">
                            </span>
                        </div>
                        <div class="quality-a11y-row">
                            <div><strong>Archivo PDF</strong></div>
                            <span class="quality-search">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/></svg>
                                <input type="file" name="archivo" accept="application/pdf" disabled aria-describedby="aviso-archivo">
                            </span>
                        </div>
                        <div class="quality-a11y-row">
                            <div>
                                <small id="aviso-archivo">Aún no se puede subir archivos en esta versión de COTECU. Ubicación por defecto: <code>storage/app/private/</code></small>
                            </div>
                        </div>
                        <div class="quality-a11y-actions">
                            <button class="institutional-primary" type="submit">Guardar plan curricular</button>
                            <button class="quality-reset" type="button" @click="subirOpen = false">Cancelar</button>
                        </div>
                    </div>
                </form>
            </section>
        </div>
        @endif
    </div>
@endsection
