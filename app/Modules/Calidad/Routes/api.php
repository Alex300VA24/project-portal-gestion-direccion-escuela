<?php

use App\Modules\Calidad\Controllers\CalidadController;
use Illuminate\Support\Facades\Route;

Route::prefix('calidad')
    ->name('api.calidad.')
    ->group(function () {
        Route::get('/', [CalidadController::class, 'apiIndex'])->name('index');
    });
