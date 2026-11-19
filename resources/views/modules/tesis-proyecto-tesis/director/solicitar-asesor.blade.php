@extends('modules.tesis-proyecto-tesis.layout')

@php
    $nombres = collect($integrantes)->pluck('nombre')->implode(' y ');
    $detalle = "Me dirijo a usted para solicitar la designación de un docente asesor, especialista en la línea de {$linea}, para el proyecto de tesis «{$proyecto->titulo}», presentado por ".(count($integrantes) > 1 ? 'los estudiantes ' : 'el estudiante ').$nombres.'.';
@endphp

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.director.panel'), 'a' => 'Panel general'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.director.panel') }}">Panel general</a> › Solicitar asesor</p>
            <h1>Solicitar asesor al Decanato</h1>
            <p>El Decanato busca al docente en el departamento académico del área y emite la resolución. La Secretaría la registra al recibirla.</p>
            <p class="tz-alert is-info tz-small" role="note"><span><strong>El sistema registra la solicitud; el envío al Decanato se hace por el canal oficial.</strong> Al registrarla se genera el oficio dirigido al Decano para que lo imprimas, firmes y envíes.</span></p>
        </div>
    </div>

    @include('modules.tesis-proyecto-tesis.partials.ficha')

    @if ($pendiente)
        <div class="tz-alert is-info" role="status">
            <div>La solicitud se registró el {{ $pendiente->presentada_en->format('d/m/Y') }} y se envía al Decanato por el canal oficial; la Secretaría registrará la resolución cuando llegue.
                <a href="{{ route('tesis-proyecto-tesis.director.oficio', $pendiente) }}">Ver e imprimir el oficio</a></div>
        </div>
    @elseif (! $puedeSolicitar)
        <div class="tz-alert is-info" role="status">Este proyecto ya no espera la designación de asesor ({{ \App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado) }}).</div>
    @else
        <form class="tz-panel" method="POST" action="{{ route('tesis-proyecto-tesis.director.asesor.enviar', $proyecto) }}">
            @csrf
            <div class="tz-panel-head">
                <h2>Solicitud</h2>
                @if ($proyecto->id_archivo_documento)
                    <a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['proyecto', $proyecto->uuid]) }}" target="_blank" rel="noopener">Documento F1 · ver PDF</a>
                @endif
            </div>
            <div class="tz-panel-body tz-fields">
                <div class="tz-field">
                    <label for="destinatario">Dirigido a (cargo)</label>
                    <input id="destinatario" name="destinatario" class="tz-input" value="{{ old('destinatario', 'Decano de la Facultad de Ciencias Físicas y Matemáticas') }}" maxlength="120" required>
                    @error('destinatario') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field">
                    <label for="destinatario_area">Área</label>
                    <input id="destinatario_area" name="destinatario_area" class="tz-input" value="{{ old('destinatario_area', 'Decanato') }}" maxlength="120" required>
                    @error('destinatario_area') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field is-wide">
                    <label for="asunto">Asunto</label>
                    <input id="asunto" name="asunto" class="tz-input" value="{{ old('asunto', 'Solicitud de designación de asesor de tesis · '.$proyecto->codigo) }}" maxlength="200" required>
                    @error('asunto') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <div class="tz-field is-wide">
                    <label for="detalle">Descripción</label>
                    <textarea id="detalle" name="detalle" class="tz-textarea" rows="5" maxlength="3000" required>{{ old('detalle', $detalle) }}</textarea>
                    @error('detalle') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
                <fieldset class="tz-fieldset is-wide" style="grid-column: 1 / -1">
                    <legend>Docentes especialistas en la línea (referencial)</legend>
                    <label class="tz-check"><input type="radio" name="docente_sugerido" value="" @checked(old('docente_sugerido', '') === '')> Sin sugerencia: el Decanato decide</label>
                    @forelse ($sugerencias as $s)
                        <label class="tz-check"><input type="radio" name="docente_sugerido" value="{{ $s['codigo'] }}" @checked(old('docente_sugerido') === $s['codigo'])> {{ $s['nombre'] }} <span class="tz-muted">· {{ $s['carga'] }} {{ $s['carga'] === 1 ? 'proyecto asesorado' : 'proyectos asesorados' }}</span></label>
                    @empty
                        <p class="tz-muted">No hay docentes registrados como especialistas de esta línea.</p>
                    @endforelse
                    @error('docente_sugerido') <span class="tz-error">{{ $message }}</span> @enderror
                </fieldset>
            </div>
            <div class="tz-panel-body tz-actions" style="border-top: 1px solid var(--tz-border)">
                <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.director.panel') }}">Cancelar</a>
                <button class="tz-btn tz-btn-primary" type="submit">Registrar solicitud y generar oficio</button>
            </div>
        </form>
    @endif
@endsection
