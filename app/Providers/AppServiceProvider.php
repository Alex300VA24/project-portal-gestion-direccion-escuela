<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('gestion_horarios_integracion.habilitada') === true) {
            $this->app->bind(\App\Shared\Contracts\FuenteDemandaPrematricula::class, \App\Modules\PreMatricula\Services\FuenteDemandaAgregada::class);
            $this->app->bind(\App\Shared\Contracts\CierreDemandaPrematricula::class, \App\Modules\PreMatricula\Services\CierreDemandaParaHorarios::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
