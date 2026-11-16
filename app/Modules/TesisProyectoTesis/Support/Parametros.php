<?php

namespace App\Modules\TesisProyectoTesis\Support;

/**
 * Parámetros del proceso. Se mantienen como constantes del módulo hasta que
 * gestión autorice usar comun.parametro_sistema.
 */
final class Parametros
{
    /** Porcentaje máximo de similitud (Turnitin) para dar conformidad. */
    public const SIMILITUD_MAXIMA = 21;

    /** Máximo de estudiantes por proyecto. */
    public const INTEGRANTES_MAXIMOS = 2;

    /** Semana del 10.º ciclo en la que el jurado hace la revisión intermedia. */
    public const SEMANA_REVISION_INTERMEDIA = 8;

    /** Resultados aprobatorios mínimos para aprobar la sustentación. */
    public const APROBATORIOS_MINIMOS = 2;

    /** Miembros del jurado. */
    public const MIEMBROS_JURADO = 3;

    /** Semanas del ciclo cuando el periodo no lo indica. */
    public const SEMANAS_CICLO = 15;

    /** Duración estimada de una sustentación (para choques de agenda). */
    public const DURACION_SUSTENTACION_MINUTOS = 120;

    /** Tamaño máximo de archivo en kilobytes (10 MB). */
    public const ARCHIVO_MAX_KB = 10240;

    /** Máximo de palabras del título (criterio 1 de la rúbrica). */
    public const TITULO_MAX_PALABRAS = 20;

    public const CARGOS_JURADO = ['Presidente', 'Secretario', 'Vocal'];

    public const ENFOQUES = ['Cuantitativo', 'Cualitativo', 'Mixto'];

    public const TIPOS_INVESTIGACION = ['Básica', 'Aplicada', 'Bibliográfica'];

    public const DISENOS = ['Preexperimental', 'Cuasiexperimental', 'Experimental', 'No experimental', 'Descriptivo', 'Correlacional'];

    public const FINANCIAMIENTOS = ['Autofinanciado', 'Institucional', 'Externo'];

    public const TIPOS_RECURSO = ['Personal', 'Bienes', 'Servicios', 'Local'];

    public const SECCIONES_DOCUMENTO = [
        'Título', 'Problema', 'Matriz de consistencia', 'Marco teórico', 'Material y método',
        'Solución tecnológica', 'Aspectos administrativos', 'Resultados', 'Discusión',
        'Conclusiones y recomendaciones', 'Resumen', 'Referencias', 'General',
    ];

    public const TIPO_SOLICITUD_ASESOR = 'Designación de asesor';

    public const TIPO_SOLICITUD_JURADO = 'Designación de jurado';

    public const TIPO_SOLICITUD_SUSTENTACION = 'Programación de sustentación';

    public const MODULO_ESQUEMA = 'tesis';

    public const DISCO = 'local';

    public const CARPETA = 'tesis';
}
