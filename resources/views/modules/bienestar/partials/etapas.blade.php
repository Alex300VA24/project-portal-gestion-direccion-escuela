@php
    $etapas = ['enviada' => 'Enviada', 'recibida' => 'En revisión', 'remitida' => 'En Bienestar', 'fin' => 'Dictamen'];
    $actual = ['enviada' => 0, 'recibida' => 1, 'observada' => 1, 'remitida' => 2, 'aprobada' => 4, 'rechazada' => 4][$solicitud->estado] ?? 0;
@endphp
<ol class="etapas" aria-label="Etapas del trámite">
    @foreach (array_values($etapas) as $i => $t)
        <li @class(['hecha' => $i < $actual, 'actual' => $i === $actual, 'observada' => $i === 1 && $solicitud->estado === 'observada', 'rechazada' => $i === 3 && $solicitud->estado === 'rechazada'])
            @if ($i === $actual) aria-current="step" @endif>
            <span>{{ $i < $actual ? '✓' : $i + 1 }}</span>{{ $i === 1 && $solicitud->estado === 'observada' ? 'Observada' : ($i === 3 && $actual === 4 ? ucfirst($solicitud->estado) : $t) }}
        </li>
    @endforeach
</ol>
