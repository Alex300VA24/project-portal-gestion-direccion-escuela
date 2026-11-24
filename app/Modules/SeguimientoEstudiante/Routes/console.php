<?php

use App\Modules\SeguimientoEstudiante\Jobs\GenerarRecordatoriosTutoria;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('seguimiento-estudiante:recordatorios {--days=1 : Días adicionales al día actual}', function () {
    $dias = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
    if ($dias === false) {
        $this->error('Indica un número entero de 0 a 30 días.');

        return 1;
    }

    GenerarRecordatoriosTutoria::dispatch($dias)->onQueue('seguimiento-estudiante');
    $this->info('Trabajo de recordatorios despachado.');

    return 0;
})->purpose('Generar avisos internos para próximas citas de Tutoría');

Schedule::command('seguimiento-estudiante:recordatorios')
    ->dailyAt('08:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(15);
