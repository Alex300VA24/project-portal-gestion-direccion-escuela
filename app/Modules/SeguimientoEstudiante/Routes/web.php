<?php

use App\Modules\SeguimientoEstudiante\Controllers\SeguimientoEstudianteController;
use Illuminate\Support\Facades\Route;

Route::prefix('seguimiento-estudiante')
    ->name('seguimiento-estudiante.')
    ->group(function () {
        Route::get('/', [SeguimientoEstudianteController::class, 'index'])->name('index');
        Route::get('/crear', [SeguimientoEstudianteController::class, 'create'])->name('create');
        Route::post('/', [SeguimientoEstudianteController::class, 'store'])->name('store');
    });
