<?php

namespace App\Modules\Bienestar\Support;

final class BienestarPermissions
{
    public const SOLICITAR = 'bienestar.solicitar';

    public const REPORTAR = 'bienestar.reportar';

    public const GESTIONAR = 'bienestar.gestionar';

    public const ESTADISTICAS = 'bienestar.estadisticas';

    public const ALL = [
        self::SOLICITAR,
        self::REPORTAR,
        self::GESTIONAR,
        self::ESTADISTICAS,
    ];

    public const ROLE_PERMISSIONS = [
        'alumno' => [self::SOLICITAR],
        'profesor' => [self::REPORTAR],
        'secretaria' => [self::GESTIONAR],
        'director_escuela' => [self::ESTADISTICAS],
    ];
}
