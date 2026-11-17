<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo con el enlace personal de las invitaciones
    |--------------------------------------------------------------------------
    |
    | El envío usa la configuración de correo de Laravel (MAIL_* en el .env). Con MAIL_MAILER=log (por defecto) el
    | mensaje solo queda en storage/logs: no se entrega y la invitación no se marca como enviada.
    |
    | enviar:       SG_CORREO_ENVIAR=false desactiva el envío; los enlaces siguen mostrándose en pantalla.
    | redirigir_a:  SG_CORREO_REDIRIGIR_A=correo@ejemplo.com envía TODOS los correos a esa dirección (demostración y
    |               pruebas, para no escribir a egresados reales). En producción se deja vacío.
    | dias_contacto_inicial: vigencia del enlace de contacto inicial.
    |
    */

    'correo' => [
        'enviar' => (bool) env('SG_CORREO_ENVIAR', true),
        'redirigir_a' => env('SG_CORREO_REDIRIGIR_A') ?: null,
        'dias_contacto_inicial' => (int) env('SG_DIAS_CONTACTO_INICIAL', 30),
    ],

];
