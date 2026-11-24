@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb">Resoluciones</p>
            <h1>Registrar resolución de asesor</h1>
            <p>Registra la resolución que envió el Decanato. Al guardarla, el asesor queda oficialmente designado.</p>
        </div>
    </div>

    <div class="tz-grid-2 is-lista-detalle">
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Solicitudes esperando resolución</h2></div>
            @if ($pendientes->isEmpty())
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay solicitudes pendientes', 'texto' => 'Cuando el Director solicite un asesor al Decanato, la solicitud aparecerá aquí.'])
            @else
                <ul class="tz-list">
                    @foreach ($pendientes as $s)
                        <li @if ($actual && $actual->is($s)) style="background: var(--tz-alt)" @endif>
                            <div class="tz-tags"><strong class="tz-code">{{ $s->proyecto->codigo }}</strong><span class="tz-status is-warning">En Decanato</span></div>
                            <a href="{{ route('tesis-proyecto-tesis.secretaria.resoluciones', ['solicitud' => $s->uuid]) }}" @if ($actual && $actual->is($s)) aria-current="true" @endif>{{ \Illuminate\Support\Str::limit($s->proyecto->titulo, 70) }}</a>
                            <span class="tz-muted tz-small">Enviada el {{ $s->presentada_en->format('d/m/Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($actual)
            <form class="tz-panel" method="POST" action="{{ route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $actual) }}" enctype="multipart/form-data">
                @csrf
                <div class="tz-panel-head"><h2>Datos de la resolución · <span class="tz-code">{{ $actual->proyecto->codigo }}</span></h2></div>
                <div class="tz-panel-body" style="display: grid; gap: 8px">
                    <strong>{{ $actual->proyecto->titulo }}</strong>
                    <span class="tz-muted">{{ collect($ficha['integrantes'])->pluck('nombre')->implode(' · ') }} · {{ $ficha['linea'] }}</span>
                    @if ($sugerido)
                        <span class="tz-small">Sugerencia del Director: <strong>{{ $sugerido['nombre'] }}</strong> (referencial)</span>
                    @endif
                    @if ($actual->id_archivo)
                        <a class="tz-small" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['oficio', $actual->uuid]) }}" target="_blank" rel="noopener">Oficio del Director al Decanato · ver PDF</a>
                    @endif
                </div>
                <div class="tz-panel-body tz-fields">
                    <div class="tz-field">
                        <label for="numero">N.° de resolución *</label>
                        <input id="numero" name="numero" class="tz-input" value="{{ old('numero') }}" placeholder="0451-2026-FCFYM" maxlength="80" required>
                        @error('numero') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field">
                        <label for="fecha">Fecha de emisión *</label>
                        <input id="fecha" name="fecha" type="date" class="tz-input" value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        @error('fecha') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field">
                        <label for="organo_emisor">Órgano emisor *</label>
                        <input id="organo_emisor" name="organo_emisor" class="tz-input" value="{{ old('organo_emisor', 'Decanato · Facultad de Ciencias Físicas y Matemáticas') }}" maxlength="150" required>
                    </div>
                    <div class="tz-field">
                        <label for="dirigido_a">Dirigida a *</label>
                        <input id="dirigido_a" name="dirigido_a" class="tz-input" value="{{ old('dirigido_a', 'Director de la Escuela de Ingeniería Informática') }}" maxlength="150" required>
                    </div>
                    <div class="tz-field is-wide">
                        <label for="docente">Docente designado como asesor *</label>
                        <select id="docente" name="docente" class="tz-select" required>
                            <option value="">Selecciona</option>
                            @foreach ($docentes as $d)
                                <option value="{{ $d['codigo'] }}" @selected(old('docente', $sugerido['codigo'] ?? '') === $d['codigo'])>{{ $d['nombre'] }} · {{ $d['departamento'] ?? '' }}</option>
                            @endforeach
                        </select>
                        @error('docente') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field is-wide">
                        <label for="asunto">Asunto *</label>
                        <input id="asunto" name="asunto" class="tz-input" value="{{ old('asunto', 'Designación de asesor del proyecto de tesis '.$actual->proyecto->codigo) }}" maxlength="500" required>
                    </div>
                    <div class="tz-field is-wide">
                        <label for="detalle">Detalle</label>
                        <textarea id="detalle" name="detalle" class="tz-textarea" maxlength="3000">{{ old('detalle') }}</textarea>
                    </div>
                    <div class="tz-field is-wide">
                        <label for="archivo">Resolución escaneada (PDF, máximo 10 MB) *</label>
                        <input id="archivo" name="archivo" type="file" accept="application/pdf,.pdf" class="tz-input" required>
                        @error('archivo') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="tz-panel-body" style="border-top: 1px solid var(--tz-border); display: grid; gap: 12px">
                    <div class="tz-alert is-info">Al registrar, el sistema marca al docente como asesor vigente del proyecto, cierra la solicitud como atendida y notifica al asesor, a los estudiantes y al Director.</div>
                    <div class="tz-actions"><button class="tz-btn tz-btn-primary" type="submit">Registrar resolución</button></div>
                </div>
            </form>
        @else
            <section class="tz-panel">@include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Selecciona una solicitud'])</section>
        @endif
    </div>

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Resoluciones registradas</h2></div>
        @if ($recientes->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Aún no hay resoluciones registradas'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">N.°</th><th scope="col">Proyecto</th><th scope="col">Fecha</th><th scope="col">PDF</th></tr></thead>
                <tbody>
                    @foreach ($recientes as $r)
                        <tr>
                            <td class="tz-code">{{ $r->numero }}</td>
                            <td class="tz-code">{{ $r->solicitud?->proyecto?->codigo }}</td>
                            <td>{{ $r->fecha->format('d/m/Y') }}</td>
                            <td><a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['resolucion', $r->uuid]) }}" target="_blank" rel="noopener">Ver PDF</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
