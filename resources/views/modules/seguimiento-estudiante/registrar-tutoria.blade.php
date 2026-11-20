@php
    $accion = $correccion ? 'corregir' : 'registrar';
    $asistencia = match ($solicitud->estado) {
        'Inasistencia' => 'NO_ASISTIO',
        'No realizada' => 'NO_REALIZADA',
        default => 'ASISTIO',
    };
@endphp

<details class="mt-3" @if ($accionEnviada === $accion && $errors->any()) open @endif>
    <summary class="cursor-pointer font-medium text-indigo-700">
        {{ $correccion ? 'Corregir atención' : 'Registrar atención' }}
    </summary>
    <form method="POST" action="{{ $rutaAccion($accion) }}" class="mt-3 space-y-3">
        @csrf
        <input type="hidden" name="formulario_cita" value="{{ $idCita }}">
        <input type="hidden" name="formulario_accion" value="{{ $accion }}">
        <input type="hidden" name="version" value="{{ $solicitud->version }}">
        <div>
            <x-input-label for="asistencia_{{ $idCita }}" value="Resultado de la atención" />
            <select id="asistencia_{{ $idCita }}" name="asistencia" required class="rounded-md border-gray-300">
                <option value="ASISTIO" @selected($valor('asistencia', $asistencia) === 'ASISTIO')>Asistió</option>
                <option value="NO_ASISTIO" @selected($valor('asistencia', $asistencia) === 'NO_ASISTIO')>No asistió</option>
                <option value="NO_REALIZADA" @selected($valor('asistencia', $asistencia) === 'NO_REALIZADA')>No realizada</option>
            </select>
        </div>
        @foreach (['actividad' => 'Actividad (obligatoria si asistió)', 'acuerdos' => 'Acuerdos (obligatorios si asistió)', 'observaciones' => 'Observaciones privadas'] as $campo => $etiqueta)
            <div>
                <x-input-label :for="$campo.'_'.$idCita" :value="$etiqueta" />
                <textarea id="{{ $campo }}_{{ $idCita }}" name="{{ $campo }}" rows="2" maxlength="2000"
                          class="block w-full rounded-md border-gray-300">{{ $valor($campo, $solicitud->{$campo} ?? '') }}</textarea>
            </div>
        @endforeach
        @if ($correccion)
            <div>
                <x-input-label for="motivo_correccion_{{ $idCita }}" value="Motivo de la corrección" />
                <textarea id="motivo_correccion_{{ $idCita }}" name="motivo_correccion" rows="2" required minlength="10" maxlength="1000"
                          class="block w-full rounded-md border-gray-300">{{ $valor('motivo_correccion') }}</textarea>
            </div>
        @endif
        <x-primary-button>Guardar atención</x-primary-button>
    </form>
</details>
