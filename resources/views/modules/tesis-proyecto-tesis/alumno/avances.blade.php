@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Inicio</a> › Avances</p>
            <h1>Subir avance</h1>
            <p>Tu revisor recibe un aviso al instante. Cada entrega queda guardada como una nueva versión.</p>
        </div>
    </div>

    <div class="tz-grid">
        <article class="tz-card"><span class="tz-eyebrow">Semana actual</span><strong style="font-size: 1.6rem; color: var(--tz-navy)">{{ $semana }}</strong></article>
        <article class="tz-card"><span class="tz-eyebrow">Formato</span><strong style="font-size: 1.2rem; color: var(--tz-navy)">{{ $proyecto->etapa === 'Tesis' ? 'F2 · Tesis' : 'F1 · Proyecto' }}</strong></article>
        <article class="tz-card"><span class="tz-eyebrow">Estado</span>@include('modules.tesis-proyecto-tesis.partials.estado', ['estado' => $proyecto->estado])</article>
    </div>

    <div class="tz-grid-2">
        @if ($recibe)
            <form class="tz-panel" method="POST" action="{{ route('tesis-proyecto-tesis.alumno.proyecto.avances.subir', $proyecto) }}" enctype="multipart/form-data" x-data="{ enviando: false }" @submit="enviando = true">
                @csrf
                <div class="tz-panel-head"><h2>Nueva entrega</h2></div>
                <div class="tz-panel-body tz-form">
                    <div class="tz-fields">
                        <div class="tz-field">
                            <label for="numero_semana">Semana *</label>
                            <select id="numero_semana" name="numero_semana" class="tz-select" required>
                                @for ($s = 1; $s <= 17; $s++)
                                    <option value="{{ $s }}" @selected((int) old('numero_semana', $semana) === $s)>Semana {{ $s }}</option>
                                @endfor
                            </select>
                        </div>
                        <fieldset class="tz-field" style="border: 0; padding: 0; margin: 0">
                            <legend class="tz-label">Tipo de entrega *</legend>
                            <label class="tz-check"><input type="radio" name="tipo" value="Avance" @checked(old('tipo', 'Avance') === 'Avance')> Avance semanal o corrección</label>
                            <label class="tz-check"><input type="radio" name="tipo" value="Final" @checked(old('tipo') === 'Final')> Versión final (para verificación con la rúbrica)</label>
                            @error('tipo') <span class="tz-error">{{ $message }}</span> @enderror
                        </fieldset>
                    </div>
                    <div class="tz-field">
                        <label for="archivo">Documento (PDF, máximo 10 MB) *</label>
                        <input id="archivo" name="archivo" type="file" accept="application/pdf,.pdf" class="tz-input" required aria-invalid="{{ $errors->has('archivo') ? 'true' : 'false' }}">
                        @error('archivo') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field">
                        <label for="comentario">Comentario para el revisor (opcional)</label>
                        <textarea id="comentario" name="comentario" class="tz-textarea" maxlength="2000">{{ old('comentario') }}</textarea>
                    </div>
                    @if ($pendientes->isNotEmpty())
                        <div class="tz-alert is-warning">
                            <div>
                                <strong>Observaciones pendientes que puedes atender en esta versión</strong>
                                <ul>@foreach ($pendientes->take(5) as $o)<li>{{ \Illuminate\Support\Str::limit($o->descripcion, 120) }}</li>@endforeach</ul>
                            </div>
                        </div>
                    @endif
                    <div class="tz-actions">
                        <button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando">Enviar entrega</button>
                    </div>
                </div>
            </form>
        @else
            <section class="tz-panel">
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No se reciben entregas en este momento', 'texto' => 'Estado actual: '.\App\Modules\TesisProyectoTesis\Support\Estados::etiqueta($proyecto->estado).'. Te avisaremos cuando puedas volver a subir avances.'])
            </section>
        @endif

        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Historial de versiones</h2></div>
            @if ($entregas->isEmpty())
                @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Aún no subes avances'])
            @else
                <ul class="tz-list">
                    @foreach ($entregas as $e)
                        <li>
                            <div class="tz-tags" style="justify-content: space-between">
                                <strong>v{{ $e->version }} · {{ $e->etapa }} · {{ $e->tipo === 'Final' ? 'Versión final' : 'Semana '.$e->numero_semana }}</strong>
                                @include('modules.tesis-proyecto-tesis.partials.entrega-estado', ['estado' => $e->estado, 'n' => $e->observaciones_count])
                            </div>
                            <span class="tz-muted tz-small">{{ $e->entregado_en->format('d/m/Y H:i') }} · <a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $e->uuid]) }}" target="_blank" rel="noopener">Ver PDF</a></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
