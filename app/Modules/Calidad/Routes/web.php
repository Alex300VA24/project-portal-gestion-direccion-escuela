<?php

use App\Modules\Calidad\Controllers\CalidadController;
use Illuminate\Support\Facades\Route;

Route::prefix('calidad')
    ->name('calidad.')
    ->group(function () {
        Route::get('/', [CalidadController::class, 'index'])->name('index');

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::get('/home', [CalidadController::class, 'home'])->name('home');
            Route::get('/mapa-procesos', [CalidadController::class, 'processMap'])->name('process-map');
            Route::get('/indicadores', [CalidadController::class, 'indicators'])->name('indicators');
            Route::get('/documentos', [CalidadController::class, 'documents'])->name('documents');
            Route::get('/crear', [CalidadController::class, 'create'])->name('create');
            Route::post('/', [CalidadController::class, 'store'])->name('store');
        });
    });
