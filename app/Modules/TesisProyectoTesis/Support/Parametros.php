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

    /** Tipo por el fin que persigue (formato F1 de la Escuela). */
    public const TIPOS_INVESTIGACION = ['Básica', 'Aplicada'];

    /** Diseño de contrastación. «Otro» exige describirlo; se guarda como «Otro: …». */
    public const DISENOS = ['Experimental', 'Cuasi-experimental', 'Pre-experimental', 'No experimental – descriptivo', 'No experimental – correlacional', 'Otro'];

    public const DISENO_OTRO = 'Otro';

    public const FINANCIAMIENTOS = ['Autofinanciado', 'Institucional', 'Externo'];

    /** Línea de ayuda de cada tipo de financiamiento. */
    public const FINANCIAMIENTO_AYUDA = [
        'Autofinanciado' => 'Los estudiantes cubren todos los gastos de la investigación.',
        'Institucional' => 'La Universidad u otra institución pública cubre todo o parte de los gastos.',
        'Externo' => 'Una empresa, ONG o fondo concursable financia la investigación.',
    ];

    public const INSTITUCION_POR_DEFECTO = 'Universidad Nacional de Trujillo';

    /** Duración máxima del cronograma, en meses (se calcula del primer inicio al último término). */
    public const DURACION_MAX_MESES = 36;

    /** Tipos admitidos por tesis.recurso. En la interfaz del F1 solo se ofrecen TIPOS_RECURSO_F1. */
    public const TIPOS_RECURSO = ['Personal', 'Bienes', 'Servicios', 'Local'];

    public const TIPOS_RECURSO_F1 = ['Bienes', 'Servicios'];

    /** Código «sin código»: bienes propios a costo 0 (por ejemplo, la laptop del estudiante). */
    public const SIN_CODIGO = '–';

    /** Catálogo inicial del clasificador de gastos (código => concepto). */
    public const CLASIFICADOR_GASTOS = [
        '2.3.1.5.1' => 'Materiales y útiles de oficina',
        '2.3.2.2.1' => 'Energía eléctrica',
        '2.3.2.2.2' => 'Telefonía e internet',
        '2.3.2.7.11' => 'Otros servicios (impresiones, empastado, anillado)',
        self::SIN_CODIGO => 'Sin código (bien propio a costo 0)',
    ];

    /** Sugerencias de unidad de medida (se puede escribir otra). */
    public const UNIDADES_MEDIDA = ['Unidad', 'Millar', 'Ciento', 'Paquete', 'Ciento de hojas', 'Mes', 'Hora', 'Servicio', 'Global'];

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
