<?php

return [
    'reglas' => [
        'tercera_matricula' => [
            'veces_minimas' => 3,
            'prioridad' => 'alta',
            'descripcion' => 'Tercera matrícula cursada. Causal de separación temporal por un año (Art. 102 Ley 30220).',
        ],
        'cuarta_matricula' => [
            'veces_minimas' => 4,
            'prioridad' => 'alta',
            'descripcion' => 'Cuarta matrícula cursada. Causal de retiro definitivo de la universidad (Art. 102 Ley 30220).',
        ],
        'inasistencia_inhabilitacion' => [
            'umbral_porcentaje' => 30.0,
            'prioridad' => 'alta',
            'descripcion' => 'Inasistencias superiores al 30%. Conlleva inhabilitación y retiro automático del curso.',
        ],
        'inasistencia_preventiva' => [
            'umbral_min' => 20.0,
            'umbral_max' => 30.0,
            'prioridad' => 'media',
            'descripcion' => 'Inasistencias entre 20% y 30%. Riesgo preventivo antes de inhabilitación.',
        ],
        'creditos_insuficientes' => [
            'creditos_minimos' => 12,
            'prioridad' => 'media',
            'descripcion' => 'Matrícula menor a 12 créditos. Riesgo de pérdida de condición de estudiante regular (Art. 99.8 Ley 30220).',
        ],
    ],
    'horario_ejecucion' => '02:00',
    'timezone' => 'America/Lima',
];
