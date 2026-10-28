<?php

use App\Modules\Calidad\Controllers\CalidadController;
use Illuminate\Support\Facades\Route;

Route::prefix('calidad')
    ->name('calidad.')
    ->group(function () {
        Route::get('/', [CalidadController::class, 'index'])->name('index');
        Route::get('/crear', [CalidadController::class, 'create'])->name('create');
        Route::post('/', [CalidadController::class, 'store'])->name('store');
    });
