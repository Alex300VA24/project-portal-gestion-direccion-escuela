<?php

namespace App\Modules\InvestigacionFormativa\Services;

use InvalidArgumentException;

final class CalificacionService
{
    /**
     * Normaliza puntos seleccionados a centésimas enteras en una escala de 0 a 20.
     */
    public function normalizar(int $puntosSeleccionados, int $maximo): int
    {
        if ($puntosSeleccionados < 0 || $maximo <= 0 || $puntosSeleccionados > $maximo) {
            throw new InvalidArgumentException('Los puntos seleccionados deben estar entre cero y el máximo.');
        }

        $numerador = 20 * $puntosSeleccionados * 100;

        return $this->redondearMedioArriba($numerador, $maximo);
    }

    /**
     * Calcula el promedio final de tres notas normalizadas.
     *
     * @param  list<int|null>  $notas
     */
    public function promedioFinal(array $notas): ?int
    {
        if (count($notas) !== 3) {
            return null;
        }

        $suma = 0;

        foreach ($notas as $nota) {
            if (! is_int($nota) || $nota < 0 || $nota > 2000) {
                return null;
            }

            $suma += $nota;
        }

        return $this->redondearMedioArriba($suma, 3);
    }

    public function formatear(int $centavos): string
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }

    private function redondearMedioArriba(int $numerador, int $denominador): int
    {
        return intdiv(($numerador * 2) + $denominador, $denominador * 2);
    }
}
