<details class="mt-3" @if ($accionEnviada === $accionAgenda && $errors->any()) open @endif>
    <summary class="cursor-pointer font-medium text-indigo-700">
        {{ $accionAgenda === 'programar' ? 'Programar cita' : 'Reprogramar cita' }}
    </summary>
    <form method="POST" action="{{ $rutaAccion($accionAgenda) }}" class="mt-3 space-y-3">
        @csrf
        <input type="hidden" name="formulario_cita" value="{{ $idCita }}">
        <input type="hidden" name="formulario_accion" value="{{ $accionAgenda }}">
        <input type="hidden" name="version" value="{{ $solicitud->version }}">
        <div class="flex flex-wrap gap-4">
            <div>
                <x-input-label for="fecha_cita_{{ $idCita }}" value="Fecha y hora de Lima" />
                <x-text-input id="fecha_cita_{{ $idCita }}" name="fecha_hora" type="datetime-local" :value="$valor('fecha_hora', $solicitud->fecha_hora
                    ? \Illuminate\Support\Carbon::parse($solicitud->fecha_hora)->setTimezone('America/Lima')->format('Y-m-d\TH:i')
                    : '')" required />
            </div>
            <div>
                <x-input-label for="duracion_cita_{{ $idCita }}" value="Duración en minutos" />
                <x-text-input id="duracion_cita_{{ $idCita }}" name="duracion_minutos" type="number" min="10" max="120"
                              :value="$valor('duracion_minutos', $solicitud->duracion_minutos ?? 30)" required />
            </div>
            <div>
                <x-input-label for="modalidad_cita_{{ $idCita }}" value="Modalidad" />
                <select id="modalidad_cita_{{ $idCita }}" name="modalidad" required class="rounded-md border-gray-300">
                    <option value="PRESENCIAL" @selected($valor('modalidad', $solicitud->modalidad) === 'PRESENCIAL')>Presencial</option>
                    <option value="VIRTUAL" @selected($valor('modalidad', $solicitud->modalidad) === 'VIRTUAL')>Virtual</option>
                </select>
            </div>
        </div>
        <div>
            <x-input-label for="lugar_cita_{{ $idCita }}" value="Lugar o enlace de la cita" />
            <x-text-input id="lugar_cita_{{ $idCita }}" name="lugar" maxlength="250" :value="$valor('lugar', $solicitud->lugar)" class="w-full" required />
        </div>
        <div>
            <x-input-label for="nota_cita_{{ $idCita }}" value="Motivo o nota de programación" />
            <textarea id="nota_cita_{{ $idCita }}" name="nota" rows="2" required minlength="5" maxlength="1000"
                      class="block w-full rounded-md border-gray-300">{{ $valor('nota') }}</textarea>
        </div>
        <x-primary-button>Guardar cita</x-primary-button>
    </form>
</details>
