<?php

namespace App\Modules\TesisProyectoTesis\Support;

use Illuminate\Support\Carbon;

/**
 * Duración del proyecto en meses a partir del cronograma: del primer inicio al último término.
 * Misma fórmula que el formulario F1 (meses de 30,4375 días, redondeado hacia arriba, mínimo 1).
 */
final class DuracionCronograma
{
    public const DIAS_POR_MES = 30.4375;

    /** @param iterable<array{fecha_inicio?: mixed, fecha_fin?: mixed}> $actividades */
    public static function meses(iterable $actividades): ?int
    {
        $inicio = null;
        $fin = null;
        foreach ($actividades as $a) {
            try {
                $i = Carbon::parse((string) ($a['fecha_inicio'] ?? ''))->startOfDay();
                $f = Carbon::parse((string) ($a['fecha_fin'] ?? ''))->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if (($a['fecha_inicio'] ?? '') === '' || ($a['fecha_fin'] ?? '') === '') {
                continue;
            }
            $inicio = $inicio === null || $i->lt($inicio) ? $i : $inicio;
            $fin = $fin === null || $f->gt($fin) ? $f : $fin;
        }

        if ($inicio === null || $fin === null || $fin->lt($inicio)) {
            return null;
        }

        return max(1, (int) ceil(($inicio->diffInDays($fin) + 1) / self::DIAS_POR_MES));
    }
}
