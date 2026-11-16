@extends('modules.tesis-proyecto-tesis.layout')

@section('contenido')
    <div class="tz-head">
        <div>
            <p class="tz-crumb"><a href="{{ route('tesis-proyecto-tesis.alumno.panel') }}">Inicio</a> › Observaciones</p>
            <h1>Levantar observaciones</h1>
            <p>Responde cada observación indicando cómo la corregiste y en qué versión. El revisor confirma si queda levantada.</p>
        </div>
    </div>

    <div x-data="{ tab: 'Pendiente' }" style="display: grid; gap: 16px">
        <div class="tz-actions" role="tablist" aria-label="Estado de las observaciones">
            @foreach (['Pendiente' => 'Pendientes', 'Respondida' => 'Respondidas', 'Levantada' => 'Levantadas'] as $clave => $titulo)
                <button type="button" role="tab" class="tz-btn tz-btn-sm" id="tab-{{ $clave }}" aria-controls="panel-{{ $clave }}"
                        :class="tab === '{{ $clave }}' ? 'tz-btn-navy' : 'tz-btn-secondary'" :aria-selected="(tab === '{{ $clave }}').toString()" @click="tab = '{{ $clave }}'">
                    {{ $titulo }} ({{ $grupos[$clave]->count() }})
                </button>
            @endforeach
        </div>

        @foreach ($grupos as $clave => $lista)
            <section class="tz-panel" id="panel-{{ $clave }}" role="tabpanel" aria-labelledby="tab-{{ $clave }}" x-show="tab === '{{ $clave }}'" @if ($clave !== 'Pendiente') x-cloak @endif>
                @if ($lista->isEmpty())
                    @include('modules.tesis-proyecto-tesis.partials.vacio', ['titulo' => 'No hay observaciones en este estado'])
                @else
                    <ul class="tz-list">
                        @foreach ($lista as $o)
                            <li>
                                @include('modules.tesis-proyecto-tesis.partials.observacion')
                                @if ($o->estado !== 'Levantada')
                                    <form method="POST" action="{{ route('tesis-proyecto-tesis.alumno.observaciones.responder', $o->uuid) }}" class="tz-form" style="gap: 10px">
                                        @csrf
                                        <div class="tz-field">
                                            <label for="r-{{ $o->uuid }}">Tu respuesta</label>
                                            <textarea id="r-{{ $o->uuid }}" name="respuesta" class="tz-textarea" minlength="5" maxlength="3000" required>{{ old('respuesta', $o->estado === 'Respondida' ? $o->respuesta_estudiante : '') }}</textarea>
                                        </div>
                                        <div class="tz-actions">
                                            <label for="e-{{ $o->uuid }}" class="tz-label">Corregida en</label>
                                            <select id="e-{{ $o->uuid }}" name="entrega" class="tz-select" style="max-width: 320px">
                                                <option value="">Elige la versión (opcional)</option>
                                                @foreach ($entregas as $e)
                                                    @if ($e->id_entrega !== $o->id_entrega_origen && $e->entregado_en->gte(\Illuminate\Support\Carbon::parse($o->revisado_en)->subDay()))
                                                        <option value="{{ $e->uuid }}" @selected($o->id_entrega_correccion === $e->id_entrega)>v{{ $e->version }} · {{ $e->etapa }} · {{ $e->tipo === 'Final' ? 'final' : 'semana '.$e->numero_semana }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button class="tz-btn tz-btn-primary tz-btn-sm" type="submit">{{ $o->estado === 'Respondida' ? 'Actualizar respuesta' : 'Enviar respuesta' }}</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
@endsection
