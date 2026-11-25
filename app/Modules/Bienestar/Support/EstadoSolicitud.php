<?php

namespace App\Modules\Bienestar\Support;

/** Nombre visible de cada estado y el aviso que recibe el estudiante al llegar a él. */
final class EstadoSolicitud
{
    private const TEXTOS = [
        'enviada' => ['Enviada', 'Recibimos tu solicitud #%d. Secretaría la revisará.'],
        'recibida' => ['En revisión', 'Tu solicitud #%d pasó a revisión.'],
        'observada' => ['Observada', 'Tu solicitud #%d fue observada. Corrige los requisitos indicados.'],
        'remitida' => ['En Bienestar', 'Tu solicitud #%d fue revisada y pasó a Bienestar para su evaluación.'],
        'aprobada' => ['Aprobada', 'Tu solicitud #%d fue aprobada.'],
        'rechazada' => ['Rechazada', 'Tu solicitud #%d fue rechazada. Revisa el motivo.'],
    ];

    public static function texto(string $estado): string
    {
        return self::TEXTOS[$estado][0] ?? ucfirst(str_replace('_', ' ', $estado));
    }

    public static function aviso(string $estado, int $id, ?string $anterior = null): string
    {
        return $anterior === 'observada' && $estado === 'recibida'
            ? "Tu solicitud #{$id} corregida volvió a revisión."
            : sprintf(self::TEXTOS[$estado][1] ?? 'Tu solicitud #%d cambió de estado.', $id);
    }

    public static function tono(string $estado): string
    {
        return match ($estado) {
            'aprobada', 'remitida', 'recibida' => 'azul',
            'observada', 'rechazada' => 'rojo',
            default => 'dorado',
        };
    }
}
