@php
    $idCita = $solicitud->id_solicitud;
    $enviada = (string) $idCita === old('solicitud_cambio');
    $editable = in_array($solicitud->estado, ['Pendiente', 'Programada'], true)
        && (! $solicitud->fecha_hora || \Illuminate\Support\Carbon::parse($solicitud->fecha_hora)->gt(now()))
        && (! $solicitud->cambio_tipo || $solicitud->cambio_decision);
@endphp

@if ($enviada)
    <x-input-error :messages="$errors->all()" />
@endif

@if ($editable)
    <details class="mt-3" @if ($enviada && $errors->any()) open @endif>
        <summary class="cursor-pointer font-medium text-indigo-700">Solicitar cambio de cita</summary>
        <form method="POST" action="{{ route('seguimiento-estudiante.solicitudes.tutoria.cambio', [$perfil->id_estudiante, $idCita]) }}"
              class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="solicitud_cambio" value="{{ $idCita }}">
            <input type="hidden" name="version" value="{{ $solicitud->version }}">
            <div>
                <x-input-label for="tipo_cambio_{{ $idCita }}" value="Tipo de cambio" />
                <select id="tipo_cambio_{{ $idCita }}" name="tipo_cambio" required class="rounded-md border-gray-300">
                    <option value="REPROGRAMACION">Reprogramar</option>
                    <option value="CANCELACION" @selected($enviada && old('tipo_cambio') === 'CANCELACION')>Cancelar</option>
                </select>
            </div>
            <div>
                <x-input-label for="motivo_cambio_{{ $idCita }}" value="Motivo" />
                <textarea id="motivo_cambio_{{ $idCita }}" name="motivo_cambio" rows="2" required minlength="5" maxlength="2000"
                          class="block w-full rounded-md border-gray-300">{{ $enviada && is_string(old('motivo_cambio')) ? old('motivo_cambio') : '' }}</textarea>
            </div>
            <x-primary-button>Enviar petición</x-primary-button>
        </form>
    </details>
@endif
