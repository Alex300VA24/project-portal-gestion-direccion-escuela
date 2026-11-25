<?php

namespace App\Modules\GestionHorarios\Services;

/** Filtros de presentación; no altera el horario ni concede permisos de edición. */
final class ConsultaSemanalDemo
{
    public const VISTAS = ['ciclo', 'docente', 'ambiente'];

    private static function normalizar(string $texto): string
    {
        return strtolower(strtr(trim($texto), [
            'Á'=>'a','á'=>'a','É'=>'e','é'=>'e','Í'=>'i','í'=>'i','Ó'=>'o','ó'=>'o',
            'Ú'=>'u','ú'=>'u','Ü'=>'u','ü'=>'u','Ñ'=>'n','ñ'=>'n',
        ]));
    }

    public static function comparar(string $a, string $b): int
    {
        return strnatcmp(self::normalizar($a), self::normalizar($b)) ?: strcmp($a, $b);
    }

    public static function opciones(array $nombres, string $buscar = '', string $seleccion = ''): array
    {
        $texto = self::normalizar($buscar);
        $nombres = array_filter($nombres, fn ($nombre, $codigo) => (string) $codigo === $seleccion || $texto === '' || str_contains(self::normalizar($nombre), $texto), ARRAY_FILTER_USE_BOTH);
        uksort($nombres, fn ($a, $b) => self::comparar($nombres[$a], $nombres[$b]) ?: strcmp((string) $a, (string) $b));
        return $nombres;
    }

    public static function filtrar(array $actividades, array $secciones, array $sesiones, string $vista, string $ciclo, string $seccion, string $docente, string $ambiente, string $curso): array
    {
        return array_filter($actividades, static function ($a) use ($secciones, $sesiones, $vista, $ciclo, $seccion, $docente, $ambiente, $curso) {
            $s = $secciones[$a['seccion_id']] ?? null;
            if (!$s) { return false; }
            return match ($vista) {
                'ciclo' => ($ciclo === '' || (string) $s['ciclo'] === $ciclo)
                    && ($seccion === '' || $s['seccion'] === $seccion)
                    && ($curso === '' || (string) $s['curso'] === $curso),
                'docente' => $docente !== '' && in_array($docente, array_column($a['docentes'], 'codigo_docente'), true),
                'ambiente' => $ambiente !== '' && ($sesiones[$a['id']]['ambiente'] ?? null) === $ambiente,
                default => false,
            };
        });
    }
}
