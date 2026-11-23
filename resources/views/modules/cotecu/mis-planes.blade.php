@extends('layouts.cotecu')

@section('title', 'Mis planes')
@section('page-label', 'Mis planes')

@section('content')
    <div class="quality-page" x-data="{ subirOpen: {{ $errors->any() ? 'true' : 'false' }}, avisoArchivo: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Mis planes</h1>
                <p>Planes curriculares que subiste y el estado de su revisión.</p>
            </div>
            <button class="institutional-primary" type="button" @click="subirOpen = true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                Subir plan curricular
            </button>
        </header>

        @if ($porRevisar)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>Tienes {{ $porRevisar }} {{ $porRevisar === 1 ? 'plan' : 'planes' }} por revisar · Entra a Revisar planes en el menú lateral</strong>
                </div>
            </div>
        @endif

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

        @php
            // Los tres estados de revisión, en el orden en que el
            // director de COTECU los lee: aprobado, en cola y observado.
            // Cada tarjeta se pinta solo si tiene filas.
            $gruposPlanes = [
                ['titulo' => 'Plan curricular aprobado', 'filas' => $planes->where('estado', 'Validado')],
                ['titulo' => 'Plan curricular pendiente', 'filas' => $planes->where('estado', 'Pendiente')],
                ['titulo' => 'Planes curriculares observados', 'filas' => $planes->where('estado', 'Observado')],
            ];
        @endphp

        @if ($planes->isEmpty())
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no has subido planes curriculares</h2>
                <p>Usa el botón <strong>Subir plan curricular</strong> para enviar tu primer documento. El estado de cada envío aparecerá aquí.</p>
            </section>
        @else
            <div class="quality-map-sections">
                @foreach ($gruposPlanes as $grupo)
                    @if ($grupo['filas']->isNotEmpty())
                        <section class="quality-process-group">
                            <header>
                                <div><h2>{{ $grupo['titulo'] }}</h2></div>
                            </header>
                            <ul class="quality-document-list">
                                @foreach ($grupo['filas'] as $plan)
                                    <li>
                                        <span class="quality-document-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg>
                                        </span>
                                        <div>
                                            <h2>{{ $plan->nombre }}</h2>
                                            <p>
                                                <span class="quality-period">{{ $plan->plan_codigo }}</span>
                                            </p>
                                        </div>
                                        <div class="quality-file-meta">
                                            <span class="quality-a11y-actions">
                                                <button class="institutional-primary" type="button" @click="avisoArchivo = true">Ver</button>
                                                <button class="institutional-secondary" type="button" @click="avisoArchivo = true">Descargar</button>
                                            </span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="quality-modal" x-cloak x-show="subirOpen" x-transition.opacity @keydown.escape.window="subirOpen = false">
            <div class="quality-modal-backdrop" @click="subirOpen = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="subir-plan-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="subir-plan-title">Subir plan curricular</h2></div>
                    <button class="quality-icon-button" type="button" @click="subirOpen = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <form method="POST" action="{{ route('cotecu.mis-planes.store') }}">
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
                                <small id="aviso-archivo">Aún no se puede subir archivos en esta versión de COTECU.</small>
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

        <div class="quality-modal" x-cloak x-show="avisoArchivo" x-transition.opacity @keydown.escape.window="avisoArchivo = false">
            <div class="quality-modal-backdrop" @click="avisoArchivo = false"></div>
            <section class="quality-dialog" role="dialog" aria-modal="true" aria-labelledby="aviso-archivo-title">
                <header>
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="aviso-archivo-title">Archivo no disponible</h2></div>
                    <button class="quality-icon-button" type="button" @click="avisoArchivo = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <div class="quality-a11y-body">
                    <div class="quality-a11y-row">
                        <div>
                            <strong>Por ahora no se puede ver ni descargar el archivo</strong>
                            <small>La subida de archivos está simulada en esta versión de COTECU: los PDF aún no están cargados en el servidor. La función se habilitará en una siguiente entrega.</small>
                        </div>
                    </div>
                    <div class="quality-a11y-actions">
                        <button class="institutional-primary" type="button" @click="avisoArchivo = false">Entendido</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
