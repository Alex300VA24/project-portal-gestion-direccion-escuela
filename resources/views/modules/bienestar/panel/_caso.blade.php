@php $abierto = $caso->estado !== 'Cerrado'; @endphp
<section class="card-unt p-6 space-y-6">
    <div class="encabezado encabezado-chico">
        <div>
            <h2>Caso #{{ $caso->id_caso }}</h2>
            <p>{{ str_replace('_', ' ', $caso->estado) }} · abierto el {{ $caso->abierto_en?->format('d/m/Y') }}@if ($caso->cerrado_en) · cerrado el {{ $caso->cerrado_en->format('d/m/Y') }}@endif</p>
        </div>
        @if ($abierto)
            <div class="acciones">
                <button type="button" class="btn-unt-outline" data-abrir-modal="m-cita">Programar cita</button>
                <button type="button" class="btn-unt-secondary" data-abrir-modal="m-derivar">Derivar</button>
                <button type="button" class="btn-unt-primary" data-abrir-modal="m-cerrar">Cerrar caso</button>
            </div>
        @endif
    </div>

    <div class="space-y-2">
        <h3 class="subtitulo">Citas</h3>
        @forelse ($caso->citas as $cita)
            <div class="fila">
                <div><b>{{ $cita->inicio?->format('d/m/Y H:i') }} – {{ $cita->fin?->format('H:i') }}</b><small>{{ $cita->modalidad }} · {{ str_replace(['No_Asistio', '_'], ['No asistió', ' '], $cita->estado) }}</small>@if ($cita->notas_atencion)<p>{{ $cita->notas_atencion }}</p>@endif</div>
                @if ($cita->estado === 'Programada')
                    <button type="button" class="btn-unt-outline" data-abrir-modal="m-resultado" data-accion="{{ route('bienestar.panel.citas.registrar', $cita) }}">Registrar resultado</button>
                @endif
            </div>
        @empty
            <p class="vacio">Sin citas registradas.</p>
        @endforelse
    </div>

    <div class="space-y-2">
        <h3 class="subtitulo">Derivaciones</h3>
        @forelse ($caso->derivaciones as $der)
            <div class="fila">
                <div><b>{{ $der->destino_texto }}</b><small>{{ $der->estado }} · {{ $der->consentimiento_estudiante ? 'con consentimiento' : 'sin consentimiento' }}</small><p>{{ $der->motivo }}</p>@if ($der->resultado_especialista)<p><b>Informe:</b> {{ $der->resultado_especialista }}</p>@endif</div>
                <div class="acciones">
                    @if ($der->consentimiento_estudiante)<a href="{{ route('bienestar.panel.imprimir-derivacion', $der) }}" target="_blank" rel="noopener" class="btn-unt-outline">Imprimir ficha</a>@endif
                    @unless ($der->resultado_especialista)<button type="button" class="btn-unt-primary" data-abrir-modal="m-informe" data-accion="{{ route('bienestar.panel.registrar-resultado', $der) }}">Registrar informe</button>@endunless
                </div>
            </div>
        @empty
            <p class="vacio">Sin derivaciones.</p>
        @endforelse
    </div>

    <div>
        <h3 class="subtitulo">Historial</h3>
        <ol class="historial">
            @foreach ($caso->historial as $h)
                <li><b>{{ str_replace('_', ' ', $h->estado_nuevo) }}</b><small>{{ $h->cambiado_en?->setTimezone('America/Lima')->format('d/m/Y H:i') }}</small><p>{{ $h->observacion }}</p></li>
            @endforeach
        </ol>
    </div>
</section>

<x-bienestar::modal-unt id="m-cita" titulo="Programar cita" detalle="Dura una hora. El estudiante recibe un aviso." :accion="route('bienestar.panel.casos.citas', $caso)" boton="Programar">
    <label class="campo">Fecha y hora *<input type="datetime-local" name="inicio_cita" value="{{ old('inicio_cita') }}" min="{{ now('America/Lima')->format('Y-m-d\TH:i') }}" required></label>
    <label class="campo">Modalidad *<select name="modalidad"><option>Presencial</option><option @selected(old('modalidad') === 'Virtual')>Virtual</option></select></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-derivar" titulo="Derivar a especialista" detalle="Requiere el consentimiento del estudiante." :accion="route('bienestar.panel.guardar-derivacion')" boton="Derivar e imprimir" tono="secondary">
    @include('modules.bienestar.panel._derivacion_campos')
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-cerrar" titulo="Cerrar caso" detalle="Sus alertas quedarán como atendidas. No se podrá reabrir." :accion="route('bienestar.panel.casos.cerrar', $caso)" boton="Cerrar caso">
    <label class="campo">Resultado del acompañamiento *<textarea name="observacion" rows="4" required minlength="10" maxlength="1000">{{ old('observacion') }}</textarea></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-resultado" titulo="Resultado de la cita" detalle="Las notas se guardan cifradas.">
    <label class="campo">Resultado *<select name="estado"><option>Realizada</option><option value="No_Asistio" @selected(old('estado') === 'No_Asistio')>No asistió</option><option @selected(old('estado') === 'Cancelada')>Cancelada</option></select></label>
    <label class="campo">Notas de la atención<textarea name="notas" rows="4" maxlength="2000">{{ old('notas') }}</textarea><small>Obligatorias si la cita se realizó.</small></label>
</x-bienestar::modal-unt>
<x-bienestar::modal-unt id="m-informe" titulo="Informe del especialista" detalle="Contrarreferencia recibida.">
    <label class="campo">Informe *<textarea name="resultado_especialista" rows="4" required>{{ old('resultado_especialista') }}</textarea></label>
    <label class="campo">Estado *<select name="estado_derivacion"><option>Atendida</option><option>Pendiente</option><option>Cancelada</option></select></label>
</x-bienestar::modal-unt>
