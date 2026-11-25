<?php

return [
    // Activar solo tras aprobar el alcance de la demanda por periodo completo,
    // los proveedores de Seguridad/Core y la política de cierre.
    'habilitada' => env('HORARIOS_INTEGRACION_HABILITADA', false),
    // EN_CURSO + ventanas finalizadas no equivale por sí solo a un cierre oficial.
    // Usar esta transición únicamente si Prematrícula la acredita como tal.
    'cierre_por_transicion' => env('HORARIOS_INTEGRACION_CIERRE_POR_TRANSICION', false),
    // 0 deshabilita la sincronización programada; el comando exige habilitación.
    'periodo' => (int) env('HORARIOS_INTEGRACION_PERIODO', 0),
];
