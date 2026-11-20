<?php

namespace App\Modules\SeguimientoEgresado\Requests;

use Closure;

/**
 * Reglas de formato compartidas por los formularios del egresado. Cada regla devuelve un mensaje en español
 * (el atributo se reemplaza por el nombre legible que define cada Request).
 */
final class Formatos
{
    /** Correo con arroba, dominio y terminación (por ejemplo, .com o .pe). */
    public static function correo(): array
    {
        return ['bail', 'string', 'max:254', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/'];
    }

    public static function esCelular(mixed $valor): bool
    {
        return is_string($valor)
            && preg_match('/^\+?[0-9 ]+$/', $valor) === 1
            && self::entre(self::digitos($valor), 9, 15);
    }

    public static function celular(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! self::esCelular($valor)) {
                $fallar('Ingresa un :attribute válido: de 9 a 15 dígitos, con + opcional al inicio (ejemplo: +51 900 000 000).');
            }
        };
    }

    public static function telefonoFijo(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            $valido = is_string($valor) && preg_match('/^[0-9 ()+\-]+$/', $valor) === 1 && self::entre(self::digitos($valor), 6, 15);

            if (! $valido) {
                $fallar('Ingresa un :attribute válido: de 6 a 15 dígitos (ejemplo: 044 000000).');
            }
        };
    }

    public static function correoOCelular(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            $esCorreo = is_string($valor) && filter_var($valor, FILTER_VALIDATE_EMAIL) !== false
                && preg_match('/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', $valor) === 1;

            if (! $esCorreo && ! self::esCelular($valor)) {
                $fallar('Ingresa un correo o un celular válido para el contacto del jefe directo.');
            }
        };
    }

    /** Texto libre: sin caracteres de control y con al menos una letra o un número. */
    public static function texto(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            $limpio = is_string($valor)
                && preg_match('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u', $valor) === 1
                && preg_match('/[\p{L}\p{N}]/u', $valor) === 1;

            if (! $limpio) {
                $fallar('El campo :attribute debe tener texto válido, sin caracteres especiales de control.');
            }
        };
    }

    /** Nombre de ciudad o lugar: letras, números y signos básicos. */
    public static function lugar(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            if (! is_string($valor) || preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .,\'()\/\-]*$/u', $valor) !== 1) {
                $fallar('El campo :attribute solo puede tener letras, números y signos básicos (. , \' ( ) / -).');
            }
        };
    }

    /** Contraseña: letras y números (el largo mínimo se exige aparte). */
    public static function clave(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            $valida = is_string($valor) && preg_match('/\p{L}/u', $valor) === 1 && preg_match('/\p{N}/u', $valor) === 1;

            if (! $valida) {
                $fallar('La :attribute debe incluir letras y números.');
            }
        };
    }

    /** Matrícula: letras, números y . _ / - (4 a 35 caracteres). */
    public static function reglaMatricula(): string
    {
        return 'regex:/^[A-Za-z0-9][A-Za-z0-9._\/\-]{3,34}$/';
    }

    /** Documento de identidad: letras, números, espacios y guiones (6 a 25 caracteres). */
    public static function reglaDocumento(): string
    {
        return 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-]{5,24}$/';
    }

    private static function digitos(string $valor): int
    {
        return strlen(preg_replace('/\D/', '', $valor));
    }

    private static function entre(int $valor, int $minimo, int $maximo): bool
    {
        return $valor >= $minimo && $valor <= $maximo;
    }
}
