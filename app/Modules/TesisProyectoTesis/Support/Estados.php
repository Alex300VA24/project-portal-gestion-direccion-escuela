<?php

namespace App\Modules\TesisProyectoTesis\Support;

/**
 * Estados del proyecto (columna tesis.proyecto_tesis.estado) y etapas.
 */
final class Estados
{
    public const ETAPA_PROYECTO = 'Proyecto';

    public const ETAPA_TESIS = 'Tesis';

    public const REGISTRADO = 'Registrado';

    public const CON_ASESOR = 'ConAsesor';

    public const EN_DESARROLLO = 'EnDesarrollo';

    public const VERIFICADO_ASESOR = 'VerificadoAsesor';

    public const EN_REVISION_JURADO = 'EnRevisionJurado';

    public const APROBADO = 'Aprobado';

    public const EN_TESIS = 'EnTesis';

    public const LISTO_SUSTENTAR = 'ListoSustentar';

    public const PROGRAMADO = 'Programado';

    public const SUSTENTADO = 'Sustentado';

    public const DESAPROBADO = 'Desaprobado';

    public const TESIS_APROBADA = 'TesisAprobada';

    /** Estados en los que el proyecto ya no ocupa al estudiante. */
    public const FINALES = [self::TESIS_APROBADA];

    /** Estados en los que el estudiante puede subir entregas. */
    public const PERMITEN_ENTREGA = [self::CON_ASESOR, self::EN_DESARROLLO, self::EN_REVISION_JURADO, self::EN_TESIS];

    /** Estados de desarrollo con el asesor (verificación con rúbrica y Turnitin). */
    public const DESARROLLO = [self::EN_DESARROLLO, self::EN_TESIS];

    public const ETIQUETAS = [
        self::REGISTRADO => 'Registrado',
        self::CON_ASESOR => 'Con asesor',
        self::EN_DESARROLLO => 'En desarrollo',
        self::VERIFICADO_ASESOR => 'Verificado por el asesor',
        self::EN_REVISION_JURADO => 'En revisión del jurado',
        self::APROBADO => 'Proyecto aprobado',
        self::EN_TESIS => 'Tesis en desarrollo',
        self::LISTO_SUSTENTAR => 'Listo para sustentar',
        self::PROGRAMADO => 'Sustentación programada',
        self::SUSTENTADO => 'Sustentado',
        self::DESAPROBADO => 'Sustentación desaprobada',
        self::TESIS_APROBADA => 'Tesis aprobada',
    ];

    /** Variante visual de la etiqueta: success, warning, danger, neutral, info. */
    public const VARIANTES = [
        self::REGISTRADO => 'neutral',
        self::CON_ASESOR => 'info',
        self::EN_DESARROLLO => 'warning',
        self::VERIFICADO_ASESOR => 'info',
        self::EN_REVISION_JURADO => 'warning',
        self::APROBADO => 'success',
        self::EN_TESIS => 'warning',
        self::LISTO_SUSTENTAR => 'info',
        self::PROGRAMADO => 'info',
        self::SUSTENTADO => 'success',
        self::DESAPROBADO => 'danger',
        self::TESIS_APROBADA => 'success',
    ];

    public static function etiqueta(?string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? (string) $estado;
    }

    public static function variante(?string $estado): string
    {
        return self::VARIANTES[$estado] ?? 'neutral';
    }
}
