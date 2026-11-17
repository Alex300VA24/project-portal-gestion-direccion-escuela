<?php

namespace App\Modules\Bienestar\Console\Commands;

use App\Modules\Bienestar\Services\RiesgoAcademicoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DetectarEstudiantesRiesgoCommand extends Command
{
    protected $signature = 'bienestar:detectar-riesgo {periodo? : Código del periodo académico (ej. 2026-I)} {--periodo= : Opción alternativa para el periodo}';

    protected $description = 'Evalúa los registros de matrícula e inasistencias para detectar y sincronizar alertas automáticas de riesgo estudiantil';

    public function handle(RiesgoAcademicoService $service): int
    {
        $periodo = (string) ($this->option('periodo')
            ?? $this->argument('periodo')
            ?? DB::table('core.periodo_academico')->where('estado', 'ACTIVO')->value('codigo')
            ?? '2026-I');

        $this->info("Ejecutando detección de riesgo académico para el periodo [{$periodo}]...");

        $stats = $service->detectarYActualizarAlertas($periodo);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Estudiantes procesados', $stats['procesados']],
                ['Alertas nuevas creadas', $stats['creadas']],
                ['Alertas reabiertas', $stats['reabiertas']],
                ['Alertas resueltas automáticamente', $stats['resueltas']],
                ['Registros omitidos (no matriculados en sistema)', $stats['omitidos']],
            ]
        );

        $this->info('Detección completada satisfactoriamente.');

        return self::SUCCESS;
    }
}
