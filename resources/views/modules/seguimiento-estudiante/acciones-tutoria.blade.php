@php
    $esTutorCita = $solicitud->fecha_hora && (int) $solicitud->id_persona_tutor === $persona;
    $idCita = $solicitud->id_solicitud;
    $formularioEnviado = (string) $idCita === old('formulario_cita');
    $accionEnviada = $formularioEnviado ? old('formulario_accion') : null;
    $valor = fn ($campo, $predeterminado = '') => $formularioEnviado && is_scalar(old($campo))
        ? old($campo)
        : $predeterminado;
    $rutaAccion = fn ($accion) => $accion === 'programar'
        ? route('seguimiento-estudiante.solicitudes.tutoria.programar', [$perfil->id_estudiante, $idCita])
        : route('seguimiento-estudiante.solicitudes.tutoria.resolver', [$perfil->id_estudiante, $idCita, $accion]);
@endphp

@if ($formularioEnviado)
    <x-input-error :messages="$errors->all()" />
@endif

@if ($solicitud->estado === 'Pendiente' || ($solicitud->estado === 'Programada' && $esTutorCita))
    @include('modules.seguimiento-estudiante.programar-tutoria', ['accionAgenda' => $solicitud->estado === 'Pendiente' ? 'programar' : 'reprogramar'])

    @if ($solicitud->cambio_tipo && ! $solicitud->cambio_decision)
        <details class="mt-3" @if ($accionEnviada === 'mantener' && $errors->any()) open @endif>
            <summary class="cursor-pointer font-medium text-indigo-700">Mantener la solicitud como está</summary>
            <form method="POST" action="{{ $rutaAccion('mantener') }}" class="mt-3 space-y-3">
                @csrf
                <input type="hidden" name="formulario_cita" value="{{ $idCita }}">
                <input type="hidden" name="formulario_accion" value="mantener">
                <input type="hidden" name="version" value="{{ $solicitud->version }}">
                <x-input-label for="mantener_{{ $idCita }}" value="Respuesta que verá el estudiante" />
                <textarea id="mantener_{{ $idCita }}" name="nota" rows="2" required minlength="5" maxlength="1000"
                          class="block w-full rounded-md border-gray-300">{{ $accionEnviada === 'mantener' ? $valor('nota') : '' }}</textarea>
                <x-primary-button>Mantener solicitud</x-primary-button>
            </form>
        </details>
    @endif

    <details class="mt-3" @if ($accionEnviada === 'cancelar' && $errors->any()) open @endif>
        <summary class="cursor-pointer font-medium text-indigo-700">Cancelar solicitud</summary>
        <form method="POST" action="{{ $rutaAccion('cancelar') }}" class="mt-3 space-y-3">
            @csrf
            <input type="hidden" name="formulario_cita" value="{{ $idCita }}">
            <input type="hidden" name="formulario_accion" value="cancelar">
            <input type="hidden" name="version" value="{{ $solicitud->version }}">
            <x-input-label for="cancelar_{{ $idCita }}" value="Motivo" />
            <textarea id="cancelar_{{ $idCita }}" name="nota" rows="2" required minlength="5" maxlength="1000"
                      class="block w-full rounded-md border-gray-300">{{ $accionEnviada === 'cancelar' ? $valor('nota') : '' }}</textarea>
            <x-primary-button>Confirmar cancelación</x-primary-button>
        </form>
    </details>
@endif

@if ($esTutorCita && \Illuminate\Support\Carbon::parse($solicitud->fecha_hora)->lte(now())
     && in_array($solicitud->estado, ['Programada', 'Atendida', 'Inasistencia', 'No realizada'], true))
    @include('modules.seguimiento-estudiante.registrar-tutoria', ['correccion' => $solicitud->estado !== 'Programada'])
@endif

@if ($esTutorCita && $solicitud->estado === 'Atendida' && $solicitud->id_compromiso && ! $solicitud->cumplido)
    <form method="POST" action="{{ $rutaAccion('cumplir') }}" class="mt-3">
        @csrf
        <input type="hidden" name="formulario_cita" value="{{ $idCita }}">
        <input type="hidden" name="version" value="{{ $solicitud->version }}">
        <x-primary-button>Marcar compromiso como cumplido</x-primary-button>
    </form>
@endif
