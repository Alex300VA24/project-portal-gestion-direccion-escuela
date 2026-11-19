@extends('modules.tesis-proyecto-tesis.layout')

@php
    $inicial = ['nuevas' => old('observaciones') ?: [], 'dictamen' => old('dictamen', ''), 'levantar' => old('levantar', [])];
@endphp

@section('contenido')
    @include('modules.tesis-proyecto-tesis.partials.volver', ['url' => route('tesis-proyecto-tesis.asesor.proyectos'), 'a' => 'Mis proyectos'])
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.asesor.proyectos') }}">Mis proyectos</a> › <a href="{{ route('tesis-proyecto-tesis.asesor.proyecto', $proyecto) }}">{{ $proyecto->codigo }}</a> › Entrega v{{ $entrega->version }}</p>
            <h1>Revisar avance · {{ $entrega->tipo === 'Final' ? 'Versión final' : 'Semana '.$entrega->numero_semana }}</h1>
            <p>{{ collect($integrantes)->pluck('nombre')->implode(' y ') }} · enviado {{ $entrega->entregado_en->format('d/m/Y H:i') }}</p>
        </div>
        @include('modules.tesis-proyecto-tesis.partials.entrega-estado', ['estado' => $entrega->estado])
    </div>

    <section class="tz-panel tz-panel-body" style="display: grid; gap: 10px">
        <div class="tz-actions">
            <a class="tz-btn tz-btn-secondary" href="{{ route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $entrega->uuid]) }}" target="_blank" rel="noopener">Abrir documento v{{ $entrega->version }} (PDF)</a>
        </div>
        @if ($entrega->comentario)
            <p><strong>Comentario del estudiante:</strong> {{ $entrega->comentario }}</p>
        @endif
    </section>

    @if ($revision)
        <section class="tz-panel">
            <div class="tz-panel-head"><h2>Revisión registrada</h2><span class="tz-muted tz-small">{{ $revision->revisado_en->format('d/m/Y H:i') }}</span></div>
            <div class="tz-panel-body" style="display: grid; gap: 10px">
                <p><strong>Resultado:</strong> {{ ['Observado' => 'Con observaciones', 'Conforme' => 'Sin observaciones', 'Culminado' => 'Proyecto culminado'][$revision->dictamen] ?? $revision->dictamen }}</p>
                @if ($revision->comentario) <p>{{ $revision->comentario }}</p> @endif
                @foreach ($revision->observaciones as $o)
                    <div class="tz-small"><span class="tz-status is-neutral">{{ $o->estado }}</span> <strong>{{ $o->seccion_documento }}:</strong> {{ $o->descripcion }}</div>
                @endforeach
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('tesis-proyecto-tesis.asesor.revisar.guardar', [$proyecto, $entrega]) }}" class="tz-form" x-data="Object.assign(@js($inicial), { enviando: false })" @submit="setTimeout(() => enviando = true)">
            @csrf
            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Observaciones anteriores</h2><span class="tz-muted tz-small">Marca las respondidas que quedan levantadas</span></div>
                @if ($abiertas->isEmpty())
                    <div class="tz-panel-body tz-muted">No tienes observaciones abiertas en este proyecto.</div>
                @else
                    <ul class="tz-list">
                        @foreach ($abiertas as $o)
                            <li>
                                <div class="tz-tags"><span class="tz-status is-{{ $o->estado === 'Respondida' ? 'info' : 'warning' }}">{{ $o->estado }}</span> <span class="tz-muted tz-small">v{{ $o->version_entrega }} · {{ $o->seccion_documento ?? 'General' }}</span></div>
                                <span>{{ $o->descripcion }}</span>
                                @if ($o->estado === 'Respondida')
                                    <span class="tz-small"><strong>Respuesta:</strong> {{ $o->respuesta_estudiante }} @if ($o->entregaCorreccion)<span class="tz-muted">· corregida en v{{ $o->entregaCorreccion->version }}</span>@endif</span>
                                    <label class="tz-check"><input type="checkbox" name="levantar[]" value="{{ $o->uuid }}" x-model="levantar"> Dar por levantada</label>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Nuevas observaciones</h2></div>
                <div class="tz-panel-body" style="display: grid; gap: 14px">
                    <template x-for="(o, i) in nuevas" :key="i">
                        <div class="tz-fields" style="grid-template-columns: minmax(180px, 240px) 1fr auto; align-items: end">
                            <div class="tz-field">
                                <label :for="`sec-${i}`">Sección del documento</label>
                                <select class="tz-select" :id="`sec-${i}`" :name="`observaciones[${i}][seccion]`" x-model="o.seccion">
                                    @foreach ($secciones as $s) <option value="{{ $s }}">{{ $s }}</option> @endforeach
                                </select>
                            </div>
                            <div class="tz-field">
                                <label :for="`des-${i}`">Observación</label>
                                <textarea class="tz-textarea" style="min-height: 60px" :id="`des-${i}`" :name="`observaciones[${i}][descripcion]`" x-model="o.descripcion" maxlength="2000"></textarea>
                            </div>
                            <button type="button" class="tz-btn tz-btn-danger tz-btn-sm" @click="nuevas.splice(i, 1)">Quitar</button>
                        </div>
                    </template>
                    <div><button type="button" class="tz-btn tz-btn-secondary tz-btn-sm" @click="nuevas.push({ seccion: 'Marco teórico', descripcion: '' }); dictamen = 'Observado'">+ Agregar observación</button></div>
                    @error('observaciones') <span class="tz-error">{{ $message }}</span> @enderror
                </div>
            </section>

            <section class="tz-panel">
                <div class="tz-panel-head"><h2>Resultado de la revisión</h2></div>
                <div class="tz-panel-body tz-form">
                    <fieldset style="border: 0; padding: 0; margin: 0; display: grid; gap: 10px">
                        <legend class="sr-only">Resultado</legend>
                        <label class="tz-check"><input type="radio" name="dictamen" value="Observado" x-model="dictamen"> <span><strong>Con observaciones.</strong> El avance vuelve a los estudiantes para corregir.</span></label>
                        <label class="tz-check"><input type="radio" name="dictamen" value="SinObservaciones" x-model="dictamen" :disabled="nuevas.length > 0"> <span><strong>Sin observaciones.</strong> Continúan con la siguiente semana.</span></label>
                        <label class="tz-check"><input type="radio" name="dictamen" value="Culminado" x-model="dictamen" :disabled="nuevas.length > 0"> <span><strong>Proyecto culminado.</strong> Pasa a la verificación con la rúbrica.</span></label>
                    </fieldset>
                    @error('dictamen') <span class="tz-error">{{ $message }}</span> @enderror
                    <div class="tz-field">
                        <label for="comentario">Comentario general (opcional)</label>
                        <textarea id="comentario" name="comentario" class="tz-textarea" maxlength="3000">{{ old('comentario') }}</textarea>
                    </div>
                    <div class="tz-actions"><button class="tz-btn tz-btn-primary" type="submit" :disabled="enviando || dictamen === ''">Enviar revisión</button></div>
                </div>
            </section>
        </form>
    @endif
@endsection
