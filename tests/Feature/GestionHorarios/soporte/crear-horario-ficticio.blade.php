<div>
    <h1>Crear horario de prueba</h1>
    <label>Periodo <input wire:model="periodo"></label>
    <label>Tiempo máximo en segundos <input type="number" min="1" max="150" wire:model="tiempo"></label>
    <button wire:click="comprobar">Comprobar preparación</button>
    @if ($huella)<button wire:click="solicitar">Crear horario de prueba</button>@endif
    @error('preparacion') <p role="alert">{{ $message }}</p> @enderror
    @foreach ($diagnosticos as $diagnostico)<p>{{ $diagnostico['mensaje'] ?? $diagnostico['codigo'] ?? 'Dato pendiente' }}</p>@endforeach
    @if ($fila)
        <p wire:poll.2s>Estado: {{ match ($fila->estado) { 'EN_COLA' => 'En cola', 'EJECUTANDO' => 'Calculando', 'REQUIERE_REVISION' => 'Requiere revisión', 'FINALIZADO' => match ($fila->solver) { 'OPTIMAL' => 'Solución óptima encontrada', 'FEASIBLE' => 'Solución factible encontrada', default => 'Finalizado' }, 'RECHAZADO' => match ($fila->solver) { 'INFEASIBLE' => 'Sin solución compatible', 'UNKNOWN' => 'Sin solución: resultado indeterminado', 'MODEL_INVALID', 'INPUT_INVALID' => 'Error de entrada', default => 'Requiere revisión' }, default => 'Error del motor' } }}</p>
        @if ($fila->diagnostico)<p>{{ $fila->diagnostico }}</p>@endif
        @if ($propuesta)<h2>Propuesta de prueba</h2>@foreach (json_decode($propuesta->sesiones, true) as $sesion)<p>{{ $sesion['actividad_id'] }} · {{ $sesion['dia'] }} · {{ $sesion['inicio'] }}–{{ $sesion['fin'] }}</p>@endforeach @endif
    @endif
</div>
