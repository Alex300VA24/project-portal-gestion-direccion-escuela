<?php

namespace App\Modules\GestionHorarios\Engine;

/** Compara conjuntos de franjas sin depender del orden ni admitir duplicados. */
final class ConciliarFranjasDocentes
{
    public function coinciden(array $registradas, array $recibidas): bool
    {
        $normalizar = static function (array $franjas): ?array {
            $resultado = [];
            foreach ($franjas as $franja) {
                if (! is_array($franja) || count($franja) !== 3 || ! is_string($franja['dia'] ?? null)
                    || ! in_array($franja['dia'], ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'], true)
                    || ! is_int($franja['inicio'] ?? null) || ! is_int($franja['fin'] ?? null)
                    || $franja['inicio'] < 0 || $franja['fin'] > 24 || $franja['inicio'] >= $franja['fin']) {
                    return null;
                }
                $clave = $franja['dia'].':'.$franja['inicio'].':'.$franja['fin'];
                if (isset($resultado[$clave])) {
                    return null;
                }
                $resultado[$clave] = true;
            }
            ksort($resultado);

            return $resultado;
        };
        $a = $normalizar($registradas);
        $b = $normalizar($recibidas);

        return $a !== null && $b !== null && $a === $b;
    }
}
