<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

/** Definición oficial de cada indicador (Indicadores_Oficiales.xlsx, proceso M01.05/PG). */
final class FichasIndicadores
{
    private const FICHAS = [
        'I1' => [
            'nombre' => 'Egresados por promoción',
            'formula' => 'N.º de egresados de la promoción / N.º de ingresantes de la promoción × 100',
            'unidad' => '%',
            'fuente' => 'Padrón y cohortes',
        ],
        'I2' => [
            'nombre' => 'Empleabilidad en la carrera',
            'formula' => 'N.º de encuestados que trabajan en su carrera / N.º de encuestados × 100',
            'unidad' => '%',
            'fuente' => 'Encuesta de seguimiento',
        ],
        'I3' => [
            'nombre' => 'Objetivos educacionales',
            'formula' => 'N.º de objetivos con logro bueno o muy bueno / N.º total de objetivos del programa × 100',
            'unidad' => '%',
            'fuente' => 'Evaluación de Dirección',
        ],
        'I4' => [
            'nombre' => 'Satisfacción de egresados',
            'formula' => 'N.º de satisfechos y muy satisfechos / N.º de egresados encuestados × 100',
            'unidad' => '%',
            'fuente' => 'Encuesta de seguimiento',
        ],
        'I5' => [
            'nombre' => 'Satisfacción de empleadores',
            'formula' => 'N.º de empleadores satisfechos y muy satisfechos / N.º de empleadores encuestados × 100',
            'unidad' => '%',
            'fuente' => 'Encuesta empresarial',
        ],
        'I6' => [
            'nombre' => 'Mejoras curriculares',
            'formula' => 'Cantidad absoluta de mejoras implementadas derivadas del seguimiento',
            'unidad' => 'mejoras',
            'fuente' => 'Registro de mejoras',
        ],
    ];

    /** @return array{nombre:string, formula:string, unidad:string, fuente:string} */
    public static function de(string $codigo): array
    {
        return self::FICHAS[$codigo];
    }
}
