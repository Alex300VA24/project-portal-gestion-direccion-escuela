<?php

use App\Modules\Bienestar\Controllers\BienestarController;
use Illuminate\Support\Facades\Route;

Route::prefix('bienestar')
    ->name('api.bienestar.')
    ->group(function () {
        Route::get('/', [BienestarController::class, 'apiIndex'])->name('index');
    });
