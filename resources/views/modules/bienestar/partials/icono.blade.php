@php
    $trazos = [
        'mas' => 'M12 5v14M5 12h14',
        'documento' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6l6 6v10a2 2 0 0 1-2 2z',
        'calendario' => 'M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z',
        'alerta' => 'M12 9v3m0 4h.01M10.3 4 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0z',
        'grafico' => 'M3 3v18h18M8 17V9m5 8V5m5 12v-6',
        'libro' => 'M12 6v13M12 6C10 5 8 5 3 5v13c5 0 7 0 9 1 2-1 4-1 9-1V5c-5 0-7 0-9 1z',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $trazos[$icono] ?? $trazos['documento'] }}"/></svg>
