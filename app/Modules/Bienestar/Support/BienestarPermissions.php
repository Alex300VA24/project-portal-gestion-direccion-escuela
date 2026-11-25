<?php

namespace App\Modules\Bienestar\Support;

final class BienestarPermissions
{
    public const SOLICITAR = 'bienestar.solicitar';

    public const REPORTAR = 'bienestar.reportar';

    public const SECRETARIA = 'bienestar.secretaria';

    public const GESTIONAR = 'bienestar.gestionar';

    public const ESTADISTICAS = 'bienestar.estadisticas';

    public const ROL_ALUMNO = 'alumno';

    public const ROL_PROFESOR = 'profesor';

    public const ROL_SECRETARIA = 'secretaria';

    public const ROL_BIENESTAR = 'bienestar';

    public const ROL_DIRECTOR = 'director_escuela';

    public const ALL = [
        self::SOLICITAR,
        self::REPORTAR,
        self::SECRETARIA,
        self::GESTIONAR,
        self::ESTADISTICAS,
    ];

    /**
     * Mapeo canónico único: Permiso Spatie <-> Rol Spatie principal equivalente
     */
    public const MAP = [
        self::SOLICITAR => self::ROL_ALUMNO,
        self::REPORTAR => self::ROL_PROFESOR,
        self::SECRETARIA => self::ROL_SECRETARIA,
        self::GESTIONAR => self::ROL_BIENESTAR,
        self::ESTADISTICAS => self::ROL_DIRECTOR,
    ];

    public const ROLE_PERMISSIONS = [
        self::ROL_ALUMNO => [self::SOLICITAR],
        self::ROL_PROFESOR => [self::REPORTAR],
        self::ROL_SECRETARIA => [self::SECRETARIA],
        self::ROL_BIENESTAR => [self::GESTIONAR, self::ESTADISTICAS],
        self::ROL_DIRECTOR => [self::ESTADISTICAS],
    ];

    /**
     * Expresión Spatie para middleware role_or_permission (ej. "role_or_permission:secretaria|bienestar.secretaria")
     */
    public static function roleOrPermission(string $permission): string
    {
        $role = self::MAP[$permission] ?? null;

        return $role ? "role_or_permission:{$role}|{$permission}" : "can:{$permission}";
    }

    /**
     * Verifica si el usuario cuenta con el permiso Spatie o con su rol equivalente
     */
    public static function check(?object $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        // 1. Rol Spatie directo o equivalente
        $role = self::MAP[$permission] ?? null;
        if (method_exists($user, 'hasRole')) {
            try {
                if ($role && $user->hasRole($role)) {
                    return true;
                }
                // El rol bienestar también tiene acceso a estadísticas
                if ($permission === self::ESTADISTICAS && $user->hasRole(self::ROL_BIENESTAR)) {
                    return true;
                }
            } catch (\Throwable) {
                // Continuar si la tabla de roles no responde o aún no se ha migrado
            }
        }

        // 2. Permiso Spatie directo o heredado vía Spatie
        if (method_exists($user, 'hasPermissionTo')) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                // Continuar si el permiso aún no existe en BD
            }
        }

        return false;
    }
}
