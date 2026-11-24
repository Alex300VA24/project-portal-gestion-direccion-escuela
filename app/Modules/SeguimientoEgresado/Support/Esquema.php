<?php

namespace App\Modules\SeguimientoEgresado\Support;

/**
 * Nombres de tabla del esquema del módulo para las reglas de validación de Laravel.
 *
 * Las reglas `exists` y `unique` leen `a.b` como «conexión.tabla»; una tabla con esquema (`seguimiento_egresado.cohorte`)
 * necesita por eso el nombre de la conexión delante: `pgsql.seguimiento_egresado.cohorte`.
 */
final class Esquema
{
    public const NOMBRE = 'seguimiento_egresado';

    /** Tabla calificada con el esquema: `seguimiento_egresado.cohorte`. */
    public static function tabla(string $tabla): string
    {
        return self::NOMBRE.'.'.$tabla;
    }

    /** Tabla lista para las reglas `exists`/`unique`: `<conexión>.seguimiento_egresado.cohorte`. */
    public static function validar(string $tabla): string
    {
        return config('database.default').'.'.self::tabla($tabla);
    }
}
