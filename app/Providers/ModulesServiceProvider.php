<?php

namespace App\Providers;

use App\Shared\Database\ModularMigrationRegistry;
use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModularMigrationRegistry::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Las rutas se ejecutan por grupos mediante module:migrate.
        // No se registran en el migrador global porque Laravel ordena todas
        // las rutas por timestamp y perdería el orden infraestructura -> shared -> módulo.
    }
}
