<?php

use App\Modules\SeguimientoEgresado\Controllers\SeguimientoEgresadoController;
use Illuminate\Support\Facades\Route;

Route::prefix('seguimiento-egresado')
    ->name('seguimiento-egresado.')
    ->group(function () {
        Route::get('/', [SeguimientoEgresadoController::class, 'index'])->name('index');
        Route::get('/crear', [SeguimientoEgresadoController::class, 'create'])->name('create');
        Route::post('/', [SeguimientoEgresadoController::class, 'store'])->name('store');
    });
