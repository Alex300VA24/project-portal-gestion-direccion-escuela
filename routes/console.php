<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Composición temporal de integración: ningún módulo importa clases del otro.
Artisan::command('horarios:sincronizar-demanda {periodo} {--simular}', function () {
    try {
        $periodo = filter_var($this->argument('periodo'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($periodo === false) {
            throw new \InvalidArgumentException('Periodo inválido.');
        }
        app()->bind(\App\Shared\Contracts\FuenteDemandaPrematricula::class, \App\Modules\PreMatricula\Services\FuenteDemandaAgregada::class);
        $servicio = new \App\Modules\GestionHorarios\Demand\SincronizarDemandaLocal(
            \Illuminate\Support\Facades\DB::connection(), app(\App\Shared\Contracts\FuenteDemandaPrematricula::class),
        );
        $this->line(json_encode($servicio->ejecutar($periodo, (bool) $this->option('simular')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 0;
    } catch (\Throwable $e) {
        report($e);
        $this->error($e->getMessage());

        return 1;
    }
})->purpose('Sincronizar demanda agregada de Prematrícula (solo integración local)');

$periodoIntegracion = config('gestion_horarios_integracion.periodo', 0);
if (app()->environment('local', 'testing') && $periodoIntegracion > 0) {
    \Illuminate\Support\Facades\Schedule::command('horarios:sincronizar-demanda', [$periodoIntegracion])
        ->everyMinute()->withoutOverlapping(5)
        ->appendOutputTo(storage_path('logs/horarios-sincronizacion.log'));
}
