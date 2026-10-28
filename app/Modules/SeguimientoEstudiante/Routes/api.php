<?php

use App\Modules\SeguimientoEstudiante\Controllers\SeguimientoEstudianteController;
use Illuminate\Support\Facades\Route;

Route::prefix('seguimiento-estudiante')
    ->name('api.seguimiento-estudiante.')
    ->group(function () {
        Route::get('/', [SeguimientoEstudianteController::class, 'apiIndex'])->name('index');
    });
