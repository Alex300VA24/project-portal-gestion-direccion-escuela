<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Composición institucional: ningún módulo importa clases del otro.
Artisan::command('horarios:sincronizar-demanda {periodo} {--simular}', function () {
    try {
        $periodo = filter_var($this->argument('periodo'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($periodo === false) {
            throw new \InvalidArgumentException('Periodo inválido.');
        }
        if (config('gestion_horarios_integracion.habilitada') !== true || ! app()->bound(\App\Shared\Contracts\FuenteDemandaPrematricula::class)) {
            $this->error('Sincronización institucional no habilitada.');
            return 1;
        }
        $servicio = new \App\Modules\GestionHorarios\Demand\SincronizarDemandaLocal(
            \Illuminate\Support\Facades\DB::connection(), app(\App\Shared\Contracts\FuenteDemandaPrematricula::class),
        );
        $this->line(json_encode($servicio->ejecutar($periodo, (bool) $this->option('simular')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 0;
    } catch (\Throwable $e) {
        report($e);
        $this->error('No se pudo sincronizar la demanda. Consulte el registro técnico.');

        return 1;
    }
})->purpose('Sincronizar demanda agregada de Prematrícula (periodo completo)');

$periodoIntegracion = config('gestion_horarios_integracion.periodo', 0);
if (config('gestion_horarios_integracion.habilitada') === true && $periodoIntegracion > 0) {
    \Illuminate\Support\Facades\Schedule::command('horarios:sincronizar-demanda', [$periodoIntegracion])
        ->everyMinute()->withoutOverlapping(5)
        ->appendOutputTo(storage_path('logs/horarios-sincronizacion.log'));
}
