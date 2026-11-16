<?php

namespace App\Modules\GestionPracticas\Support;

/**
 * Roles y permisos (Spatie, guard web) del módulo Gestión de Prácticas.
 * Los cuatro actores del proceso: Administrador, Empresa, Estudiante y Docente Supervisor.
 */
final class Acceso
{
    public const ADMINISTRADOR = 'practicas_administrador';

    public const EMPRESA = 'practicas_empresa';

    public const ESTUDIANTE = 'practicas_estudiante';

    public const SUPERVISOR = 'practicas_supervisor';

    public const VER = 'gestion-practicas.view';

    public const EMPRESAS = 'gestion-practicas.empresas.gestionar';

    public const CONVOCATORIAS_GESTIONAR = 'gestion-practicas.convocatorias.gestionar';

    public const CONVOCATORIAS_REVISAR = 'gestion-practicas.convocatorias.revisar';

    public const CONVOCATORIAS_VER = 'gestion-practicas.convocatorias.ver';

    public const POSTULAR = 'gestion-practicas.postulaciones.gestionar';

    public const PRACTICAS_REGISTRAR = 'gestion-practicas.practicas.registrar';

    public const PRACTICAS_VALIDAR = 'gestion-practicas.practicas.validar';

    public const PRACTICAS_SUPERVISAR = 'gestion-practicas.practicas.supervisar';

    /** Nota mínima aprobatoria (escala vigesimal). */
    public const NOTA_MINIMA = 11;

    /** @return array<string, list<string>> rol => permisos */
    public static function roles(): array
    {
        return [
            self::ADMINISTRADOR => [
                self::VER, self::EMPRESAS, self::CONVOCATORIAS_REVISAR, self::PRACTICAS_VALIDAR,
            ],
            self::EMPRESA => [
                self::VER, self::CONVOCATORIAS_GESTIONAR,
            ],
            self::ESTUDIANTE => [
                self::VER, self::CONVOCATORIAS_VER, self::POSTULAR, self::PRACTICAS_REGISTRAR,
            ],
            self::SUPERVISOR => [
                self::VER, self::PRACTICAS_SUPERVISAR,
            ],
        ];
    }

    /** @return list<string> */
    public static function permisos(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::roles()))));
    }
}
