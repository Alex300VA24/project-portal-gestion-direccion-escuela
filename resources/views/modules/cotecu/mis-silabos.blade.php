@extends('layouts.cotecu')

@section('title', 'Mis sílabos')
@section('page-label', 'Mis sílabos')

@section('content')
    <div class="quality-page" x-data="{ subirOpen: {{ $errors->any() ? 'true' : 'false' }}, avisoArchivo: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Mis sílabos</h1>
                <p>Sílabos subidos, en revisión, aprobados y observados, con el detalle de cada revisión.</p>
            </div>
            @if ($periodos->isNotEmpty())
                <form class="quality-period-form" method="GET" action="{{ route('cotecu.mis-silabos') }}">
                    <label for="periodo">Semestre</label>
                    <span class="quality-select">
                        <select id="periodo" name="periodo" onchange="this.form.submit()">
                            @foreach ($periodos as $periodo)
                                <option value="{{ $periodo->codigo }}" @selected($periodoSeleccionado?->codigo === $periodo->codigo)>{{ $periodo->codigo }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </span>
                    <button class="institutional-primary" type="button" @disabled(! $hayPeriodoActivo || $secciones->isEmpty()) @click="subirOpen = true">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>
                        Subir sílabo
                    </button>
                </form>
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
                    <strong>No se pudo guardar el sílabo</strong>
                    <span>{{ $errors->first() }}@if ($errors->count() > 1) (y {{ $errors->count() - 1 }} más.)@endif</span>
                </div>
            </div>
        @endif

        @php
            // Los tres estados del sílabo, en el orden de la revisión.
            // Los subtítulos son siempre en plural: el profesor envía
            // varios sílabos por semestre.
            $aprobados = $silabos->where('estado', 'Validado');
            $pendientes = $silabos->where('estado', 'Pendiente');
            $observados = $silabos->where('estado', 'Observado');

            $gruposSilabos = [
                ['titulo' => 'Sílabos aprobados', 'filas' => $aprobados],
                ['titulo' => 'Sílabos pendientes', 'filas' => $pendientes],
                ['titulo' => 'Sílabos observados', 'filas' => $observados],
            ];
        @endphp

        @if ($silabos->isNotEmpty())
            <div class="quality-map-sections">
                @foreach ($gruposSilabos as $grupo)
                    @if ($grupo['filas']->isNotEmpty())
                        <section class="quality-process-group">
                            <header>
                                <div><h2>{{ $grupo['titulo'] }}</h2></div>
                            </header>
                            <ul class="quality-document-list">
                                @foreach ($grupo['filas'] as $silabo)
                                    <li>
                                        <span class="quality-document-icon">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4"/><path d="M9 12h7M9 16h7"/></svg>
                                        </span>
                                        <div>
                                            <h2>{{ $silabo->curso_nombre }}</h2>
                                            <p>
                                                <span class="quality-period">{{ $silabo->curso_codigo }}</span>
                                                Ciclo {{ $silabo->ciclo }}
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
        @elseif ($hayPeriodoActivo)
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
                    <div><span class="quality-eyebrow">COTECU</span><h2 id="subir-title">Subir sílabo</h2></div>
                    <button class="quality-icon-button" type="button" @click="subirOpen = false" aria-label="Cerrar">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                <form method="POST" action="{{ route('cotecu.mis-silabos.store') }}">
                    @csrf
                    <div class="quality-a11y-body">
                        <div class="quality-a11y-row">
                            <div>
                                <strong>Curso asignado</strong>
                                <small>Solo tus secciones en {{ $periodoActivo?->codigo }}</small>
                            </div>
                            <span class="quality-select">
                                <select name="id_curso_plan" required>
                                    <option value="">Seleccione un curso…</option>
                                    @foreach ($secciones as $seccion)
                                        <option value="{{ $seccion->id_curso_plan }}" @selected(old('id_curso_plan') == $seccion->id_curso_plan)>{{ $seccion->curso_codigo }} · {{ $seccion->curso_nombre }} (Sección {{ $seccion->seccion_codigo }})</option>
                                    @endforeach
                                </select>
                            </span>
                        </div>
                        @if ($secciones->isEmpty())
                            <div class="quality-a11y-row">
                                <div><small>No tienes cursos asignados en el periodo activo. La dirección de escuela carga las secciones cada semestre.</small></div>
                            </div>
                        @endif
                        <div class="quality-a11y-row">
                            <div>
                                <strong>Versión</strong>
                                <small>Número correlativo del documento</small>
                            </div>
                            <span class="quality-search">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 3 8l9 5 9-5-9-5z"/><path d="m3 13 9 5 9-5"/></svg>
                                <input type="number" name="version" min="1" step="1" required value="{{ old('version') }}" placeholder="Ej. 2">
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
                            <button class="institutional-primary" type="submit">Guardar sílabo</button>
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
