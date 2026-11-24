<?php

namespace App\Modules\SeguimientoEgresado\Support\Accesos;

/**
 * Perfiles del módulo. Secretaría y Dirección salen de los roles del portal (Spatie); Egresado se identifica por el
 * enlace de su invitación (no existe como rol ni como cuenta). La empresa responde con token y no tiene perfil.
 */
enum Perfil: string
{
    case Egresado = 'EGRESADO';
    case Secretaria = 'SECRETARIA';
    case Director = 'DIRECTOR';

    /** Código de seguimiento_egresado.rol_interno => perfil. */
    public static function desdeRolInterno(string $codigo): ?self
    {
        return match ($codigo) {
            'SECRETARIA' => self::Secretaria,
            'DIRECTOR' => self::Director,
            default => null,
        };
    }

    /**
     * Perfil que corresponde a los roles que el portal le dio al usuario (DatabaseSeeder del portal: `secretaria` y
     * `director_escuela`). Si tuviera más de uno se usa Secretaría antes que Director. Sin ninguno, no tiene acceso
     * al módulo.
     *
     * @param  iterable<int, string>  $roles
     */
    public static function desdeRolesDelPortal(iterable $roles): ?self
    {
        $nombres = is_array($roles) ? $roles : iterator_to_array($roles, false);

        return match (true) {
            in_array('secretaria', $nombres, true) => self::Secretaria,
            in_array('director_escuela', $nombres, true) => self::Director,
            default => null,
        };
    }
}
