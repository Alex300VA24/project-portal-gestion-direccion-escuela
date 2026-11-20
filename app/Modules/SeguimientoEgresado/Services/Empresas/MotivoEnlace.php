<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

/** Resultado de comprobar un enlace de encuesta empresarial. */
enum MotivoEnlace: string
{
    case Vigente = 'vigente';
    case NoValido = 'no_valido';
    case Vencido = 'vencido';
    case Usado = 'usado';
    case CampaniaCerrada = 'campania_cerrada';

    /** Código HTTP con el que se rechaza el enlace. */
    public function codigoHttp(): int
    {
        return match ($this) {
            self::Vigente => 200,
            self::NoValido => 404,
            self::Usado => 409,
            self::Vencido, self::CampaniaCerrada => 410,
        };
    }

    public function titulo(): string
    {
        return match ($this) {
            self::Vigente => 'Enlace disponible',
            self::NoValido => 'Enlace no válido',
            self::Vencido => 'Este enlace venció',
            self::Usado => 'Esta encuesta ya fue respondida',
            self::CampaniaCerrada => 'Esta campaña ya terminó',
        };
    }

    /** Una encuesta ya respondida no es un error para la empresa. */
    public function tipo(): string
    {
        return $this === self::Usado ? 'info' : 'aviso';
    }

    public function mensaje(): string
    {
        return match ($this) {
            self::Vigente => 'El enlace está disponible.',
            self::NoValido => 'Este enlace no es válido. Revisa que esté completo o pide uno nuevo a la Escuela.',
            self::Vencido => 'Pide uno nuevo a la Escuela.',
            self::Usado => 'Cada enlace admite una sola respuesta. No necesitas hacer nada más.',
            self::CampaniaCerrada => 'Gracias por tu interés.',
        };
    }
}
