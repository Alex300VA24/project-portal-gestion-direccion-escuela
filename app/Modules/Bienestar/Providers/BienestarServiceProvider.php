<?php

namespace App\Modules\Bienestar\Providers;

use App\Modules\Bienestar\Adapters\AdaptadorMatriculaLocal;
use App\Modules\Bienestar\Console\Commands\DetectarEstudiantesRiesgoCommand;
use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

final class BienestarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FuenteAcademicaInterface::class, AdaptadorMatriculaLocal::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                DetectarEstudiantesRiesgoCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Programar el comando de detección de riesgo una vez al día a las 02:00 (America/Lima)
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('bienestar:detectar-riesgo')
                ->dailyAt('02:00')
                ->timezone('America/Lima');
        });
    }
}
