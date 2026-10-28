<?php

use App\Modules\SeguimientoEgresado\Controllers\SeguimientoEgresadoController;
use Illuminate\Support\Facades\Route;

Route::prefix('seguimiento-egresado')
    ->name('api.seguimiento-egresado.')
    ->group(function () {
        Route::get('/', [SeguimientoEgresadoController::class, 'apiIndex'])->name('index');
    });
