@extends('layouts.cotecu')

@section('title', 'Sílabos')
@section('page-label', 'Sílabos')

@section('content')
    <div class="quality-page" x-data="{ avisoArchivo: false }">
        <header class="quality-page-heading">
            <div>
                <h1>Sílabos</h1>
                <p>Sílabos ya validados por la dirección de escuela, filtrados por semestre académico.</p>
            </div>
            @if ($periodos->isNotEmpty())
                <form class="quality-period-form" method="GET" action="{{ route('cotecu.silabos.index') }}">
                    <label for="periodo">Semestre</label>
                    <span class="quality-select">
                        <select id="periodo" name="periodo" onchange="this.form.submit()">
                            @foreach ($periodos as $periodo)
                                <option value="{{ $periodo->codigo }}" @selected($periodoSeleccionado?->codigo === $periodo->codigo)>{{ $periodo->codigo }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </span>
                    <label for="ciclo">Ciclo</label>
                    <span class="quality-select">
                        <select id="ciclo" name="ciclo" onchange="this.form.submit()">
                            @foreach ($ciclos as $valor => $etiqueta)
                                {{-- PHP guarda como int las claves numéricas del array de
                                     opciones: se compara como texto para que el ciclo
                                     elegido en la URL quede marcado en el combobox. --}}
                                <option value="{{ $valor }}" @selected((string) $cicloSeleccionado === (string) $valor)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </span>
                </form>
            @endif
        </header>

        @if ($periodoSeleccionado?->fecha_inicio)
            @php
                // Aviso de inicio del semestre seleccionado. Se compara
                // contra hoy a las 00:00 para que la diferencia salga en
                // días enteros (fecha_inicio ya viene sin hora por el
                // cast 'date' del modelo). diffInDays devuelve un float
                // con signo en Carbon 3: se toma el valor absoluto.
                $inicioSemestre = $periodoSeleccionado->fecha_inicio;
                $hoy = \Carbon\Carbon::today();
                $dias = (int) abs($hoy->diffInDays($inicioSemestre));
                $diasTexto = $dias === 1 ? 'día' : 'días';
            @endphp
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                <div>
                    <strong>
                        @if ($inicioSemestre->isToday())
                            El semestre {{ $periodoSeleccionado->codigo }} comienza hoy, {{ $inicioSemestre->format('d/m/Y') }}
                        @elseif ($inicioSemestre->gt($hoy))
                            El semestre {{ $periodoSeleccionado->codigo }} comenzará el {{ $inicioSemestre->format('d/m/Y') }} (En {{ $dias }} {{ $diasTexto }})
                        @else
                            El semestre {{ $periodoSeleccionado->codigo }} comenzó el {{ $inicioSemestre->format('d/m/Y') }} (Hace {{ $dias }} {{ $diasTexto }})
                        @endif
                    </strong>
                </div>
            </div>
        @endif

        @if ($porRevisar)
            <div class="quality-alert" role="status">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 17h.01"/></svg>
                <div>
                    <strong>Tienes {{ $porRevisar }} {{ $porRevisar === 1 ? 'sílabo' : 'sílabos' }} por revisar · Entra a Revisar sílabos en el menú lateral</strong>
                </div>
            </div>
        @endif

        @if ($silabos->isNotEmpty())
            <ul class="quality-document-list">
                @foreach ($silabos as $silabo)
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
        @else
            <section class="quality-empty">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5"/><path d="M9 10h6M9 14h6M9 18h4"/></svg>
                <h2>Aún no hay sílabos validados en {{ $periodoSeleccionado?->codigo ?? 'este semestre' }}@if ($cicloSeleccionado !== '') ({{ $ciclos[$cicloSeleccionado] ?? '' }})@endif</h2>
                <p>Esta lista muestra únicamente los sílabos aprobados por la dirección de escuela. Cuando un sílabo supere la revisión quedará aquí, junto con su archivo.</p>
            </section>
        @endif

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
