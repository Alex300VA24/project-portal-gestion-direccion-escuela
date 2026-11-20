<?php

namespace App\Modules\SeguimientoEgresado\Support\Accesos;

/**
 * Página a la que se envía a cada perfil después de iniciar sesión (nombres de ruta existentes del módulo).
 * Propuesta: Secretaría entra a su gestión diaria, el Director a los resultados y el egresado a su seguimiento.
 */
final class RutaInicial
{
    public static function para(Perfil $perfil): string
    {
        return match ($perfil) {
            Perfil::Secretaria => 'seguimiento-egresado.padron',
            Perfil::Director => 'seguimiento-egresado.indicadores',
            Perfil::Egresado => 'seguimiento-egresado.egresado.inicio',
        };
    }
}
