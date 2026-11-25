<?php

namespace App\Modules\GestionHorarios\Services;

/** Estimación sin escrituras: conserva las plazas activas, no asigna alumnos. */
final class PropuestaSecciones
{
    public static function calcular(?int $demanda, int $capacidadObjetivo, iterable $secciones): array
    {
        if ($capacidadObjetivo < 1 || $capacidadObjetivo > 2147483647 || ($demanda !== null && $demanda < 0)) {
            throw new \InvalidArgumentException('Demanda o capacidad inválida.');
        }
        $plazas = 0;
        $activas = 0;
        $incompletas = 0;
        foreach ($secciones as $seccion) {
            if ($seccion->estado !== 'ACTIVA') {
                continue;
            }
            $activas++;
            if ($seccion->capacidad === null || (int) $seccion->capacidad <= 0) {
                $incompletas++;
                continue;
            }
            $plazas += (int) $seccion->capacidad;
        }
        $faltantes = $demanda === null || $incompletas > 0 ? null : max(0, $demanda - $plazas);
        $adicionales = $faltantes === null ? null : intdiv($faltantes, $capacidadObjetivo) + (int) ($faltantes % $capacidadObjetivo !== 0);

        return [
            'activas' => $activas,
            'plazas' => $plazas,
            'incompletas' => $incompletas,
            'faltantes' => $faltantes,
            'adicionales' => $adicionales,
        ];
    }
}
