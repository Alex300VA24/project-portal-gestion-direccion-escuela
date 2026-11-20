<?php

namespace App\Modules\InvestigacionFormativa\Support;

final class PuntajeRubrica
{
    /**
     * Convierte un puntaje decimal a centésimas enteras sin comparaciones de punto flotante.
     */
    public static function aCentavos(mixed $valor): ?int
    {
        if (is_int($valor)) {
            $texto = (string) $valor;
        } elseif (is_float($valor)) {
            if (! is_finite($valor)) {
                return null;
            }

            $texto = (string) $valor;
        } elseif (is_string($valor)) {
            $texto = trim($valor);
        } else {
            return null;
        }

        if ($texto === '' || ! preg_match('/^(\d+)(?:\.(\d+))?$/', $texto, $partes)) {
            return null;
        }

        $decimal = isset($partes[2]) ? rtrim($partes[2], '0') : '';

        if (strlen($decimal) > 2 || strlen($partes[1]) > 4) {
            return null;
        }

        $entero = (int) $partes[1];
        $decimal = str_pad($decimal, 2, '0');
        $centavos = ($entero * 100) + (int) $decimal;

        return $centavos <= 999999 ? $centavos : null;
    }

    /**
     * @param  list<array{nivel: mixed, puntaje: mixed}>  $niveles
     * @return array<int, int>|null
     */
    public static function centavosPorNivel(array $niveles): ?array
    {
        if (count($niveles) !== 4) {
            return null;
        }

        $porNivel = [];

        foreach ($niveles as $nivel) {
            if (! is_array($nivel)) {
                return null;
            }

            $numero = filter_var($nivel['nivel'] ?? null, FILTER_VALIDATE_INT);

            if ($numero === false || $numero < 1 || $numero > 4 || isset($porNivel[$numero])) {
                return null;
            }

            $centavos = self::aCentavos($nivel['puntaje'] ?? null);

            if ($centavos === null) {
                return null;
            }

            $porNivel[$numero] = $centavos;
        }

        ksort($porNivel);

        return array_keys($porNivel) === [1, 2, 3, 4] ? $porNivel : null;
    }

    public static function formatear(int $centavos): string
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }
}
