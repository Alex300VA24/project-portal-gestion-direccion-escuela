@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.asesor.proyectos') }}">Mis proyectos</a> › <a href="{{ route('tesis-proyecto-tesis.asesor.proyecto', $proyecto) }}">{{ $proyecto->codigo }}</a> › Conformidad</p>
            <h1>Dar conformidad y pasar Turnitin</h1>
            <p>{{ $proyecto->titulo }} · {{ collect($integrantes)->pluck('nombre')->implode(' y ') }}</p>
        </div>
    </div>

    <div class="tz-grid">
        <article class="tz-card">
            <span class="tz-eyebrow">Rúbrica</span>
            @if ($rubricaConforme)
                <strong style="color: var(--tz-green); font-size: 1.3rem">✓ Conforme</strong>
                <span class="tz-muted">Verificada el {{ $verificacion->revisado_en->format('d/m/Y') }}</span>
            @else
                <strong style="color: var(--tz-warning); font-size: 1.3rem">Pendiente</strong>
                <a href="{{ route('tesis-proyecto-tesis.asesor.verificar', $proyecto) }}">Verificar con la rúbrica →</a>
            @endif
        </article>
        <article class="tz-card">
            <span class="tz-eyebrow">Observaciones abiertas</span>
            <strong style="font-size: 1.3rem; color: {{ $abiertas ? 'var(--tz-warning)' : 'var(--tz-green)' }}">{{ $abiertas }}</strong>
            <span class="tz-muted">{{ $abiertas ? 'Deben quedar levantadas' : 'Todas levantadas' }}</span>
        </article>
        <article class="tz-card">
            <span class="tz-eyebrow">Documento</span>
            @if ($entrega)
                <strong style="font-size: 1.1rem; color: var(--tz-navy)">Versión v{{ $entrega->version }}</strong>
                <a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $entrega->uuid]) }}" target="_blank" rel="noopener">Ver PDF</a>
            @else
                <span class="tz-muted">Sin versión final</span>
            @endif
        </article>
    </div>

    @if ($reporte)
        <div class="tz-alert is-{{ $reporte->resultado === 'Apto' ? 'success' : 'danger' }}">
            Ya se registró el reporte de esta versión: {{ $reporte->porcentaje }} % ({{ $reporte->resultado === 'Apto' ? 'apto' : 'no apto' }}).
            {{ $reporte->resultado === 'Apto' ? '' : 'Espera la nueva versión de los estudiantes y vuelve a verificar con la rúbrica.' }}
        </div>
    @elseif ($listo)
        <form class="tz-panel" method="POST" action="{{ route('tesis-proyecto-tesis.asesor.similitud.registrar', $proyecto) }}" enctype="multipart/form-data"
              x-data="{ p: @js(old('porcentaje', '')), max: {{ $maximo }}, ok: @js((bool) old('conformidad')), enviando: false }" @submit="setTimeout(() => enviando = true)">
            @csrf
            <div class="tz-panel-head"><h2>Reporte de similitud</h2></div>
            <div class="tz-panel-body tz-form">
                <div class="tz-fields">
                    <div class="tz-field">
                        <label for="archivo">Reporte de Turnitin (PDF) *</label>
                        <input id="archivo" name="archivo" type="file" accept="application/pdf,.pdf" class="tz-input" required>
                        @error('archivo') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="tz-field">
                        <label for="porcentaje">Similitud (%) *</label>
                        <input id="porcentaje" name="porcentaje" type="number" min="0" max="100" step="0.01" class="tz-input" x-model="p" required>
                        <span class="tz-small" x-show="p !== ''" x-cloak :class="parseFloat(p) <= max ? '' : 'tz-error'"
                              x-text="parseFloat(p) <= max ? `Apto · ${p} % ≤ ${max} %` : `No apto · ${p} % > ${max} %`"></span>
                        <span class="tz-hint">Límite: {{ $maximo }} % (parámetro de similitud máxima).</span>
                        @error('porcentaje') <span class="tz-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="tz-field">
                    <label for="indicaciones">Indicaciones para los estudiantes</label>
                    <textarea id="indicaciones" name="indicaciones" class="tz-textarea" maxlength="2000">{{ old('indicaciones') }}</textarea>
                </div>
                <fieldset class="tz-fieldset" x-show="p !== '' && parseFloat(p) <= max" x-cloak>
                    <legend>Conformidad del asesor</legend>
                    <label class="tz-check"><input type="checkbox" name="conformidad" value="1" x-model="ok">
                        <span>Declaro que {{ $proyecto->enEtapaTesis() ? 'la tesis' : 'el proyecto' }} cumple todos los criterios de la rúbrica, que las observaciones fueron levantadas y que la similitud está dentro del límite. Doy mi conformidad para {{ $proyecto->enEtapaTesis() ? 'la revisión final del jurado' : 'que el Director asigne el jurado' }}.</span></label>
                    @error('conformidad') <span class="tz-error">{{ $message }}</span> @enderror
                </fieldset>
                <div class="tz-alert is-warning" x-show="p !== '' && parseFloat(p) > max" x-cloak>Si la similitud supera el {{ $maximo }} %, el documento vuelve a los estudiantes para corregir.</div>
                <div class="tz-actions">
                    <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.asesor.proyecto', $proyecto) }}">Volver</a>
                    <button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando || p === '' || (parseFloat(p) <= max && !ok)"
                            x-text="p !== '' && parseFloat(p) > max ? 'Registrar reporte y devolver a los estudiantes' : 'Dar conformidad y enviar'">Dar conformidad y enviar</button>
                </div>
            </div>
        </form>
    @else
        <div class="tz-alert is-info">Para registrar Turnitin, la versión vigente debe estar verificada con la rúbrica (conforme) y sin observaciones abiertas.</div>
    @endif

    <section class="tz-panel">
        <div class="tz-panel-head"><h2>Pasadas anteriores</h2></div>
        @if ($historialReportes->isEmpty())
            @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'Sin reportes registrados'])
        @else
            <div class="tz-table-wrap"><table class="tz-table">
                <thead><tr><th scope="col">Versión</th><th scope="col">Fecha</th><th scope="col">Similitud</th><th scope="col">Reporte</th></tr></thead>
                <tbody>
                    @foreach ($historialReportes as $r)
                        <tr>
                            <td>{{ $r->etapa }} v{{ $r->version }}</td>
                            <td>{{ $r->generado_en->format('d/m/Y') }}</td>
                            <td><span class="tz-status is-{{ $r->resultado === 'Apto' ? 'success' : 'danger' }}">{{ $r->porcentaje }} % · {{ $r->resultado === 'Apto' ? 'Apto' : 'No apto' }}</span></td>
                            <td><a href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['reporte', $r->uuid]) }}" target="_blank" rel="noopener">Ver PDF</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endsection
