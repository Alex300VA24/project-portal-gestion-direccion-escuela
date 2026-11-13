<?php

namespace Database\Seeders;

use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Database\Seeder;

/**
 * Semestres académicos de prueba para COTECU (core.periodo_academico).
 *
 * Idempotente: se puede ejecutar cuantas veces sea necesario.
 * Uso: php artisan db:seed --class=CotecuDemoSeeder
 *
 * core.periodo_academico es una tabla institucional compartida: este seeder
 * solo siembra los periodos mínimos para probar el módulo. En producción
 * los carga quien administre el esquema core; COTECU únicamente los lee.
 */
class CotecuDemoSeeder extends Seeder
{
    /**
     * Periodos de prueba. El último queda Activo: es el que usan las
     * pantallas por defecto y el único en el que se puede subir un sílabo.
     */
    private const PERIODOS = [
        [
            'codigo' => '2026-I',
            'anio' => 2026,
            'tipo' => 'I',
            'fecha_inicio' => '2026-04-13',
            'fecha_fin' => '2026-08-08',
            'numero_semanas' => 17,
            'estado' => 'Cerrado',
        ],
        [
            'codigo' => '2026-II',
            'anio' => 2026,
            'tipo' => 'II',
            'fecha_inicio' => '2026-08-24',
            'fecha_fin' => '2026-12-19',
            'numero_semanas' => 17,
            'estado' => 'Activo',
        ],
    ];

    public function run(): void
    {
        foreach (self::PERIODOS as $periodo) {
            PeriodoAcademico::firstOrCreate(['codigo' => $periodo['codigo']], $periodo);
        }

        $this->command?->info('Periodos académicos de COTECU listos (2026-I Cerrado, 2026-II Activo).');
    }
}
