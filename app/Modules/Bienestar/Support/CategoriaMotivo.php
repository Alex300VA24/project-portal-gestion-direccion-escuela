<?php

namespace App\Modules\Bienestar\Support;

/** Categoría del motivo de una alerta: lo único del motivo que ve la Secretaría (Ley 29733). */
final class CategoriaMotivo
{
    public const TODAS = [
        'academico' => 'Rendimiento académico',
        'asistencia' => 'Inasistencias',
        'economico' => 'Situación económica',
        'personal' => 'Situación personal o familiar',
        'adaptacion' => 'Adaptación universitaria',
        'otro' => 'Otro motivo',
    ];

    public static function porRegla(string $regla): string
    {
        return match (true) {
            str_starts_with($regla, 'inasistencia') => 'asistencia',
            in_array($regla, ['reporte_docente', 'manual_bienestar', 'alerta_manual'], true) => 'otro',
            default => 'academico',
        };
    }

    public static function texto(?string $categoria): string
    {
        return self::TODAS[$categoria] ?? self::TODAS['otro'];
    }
}
