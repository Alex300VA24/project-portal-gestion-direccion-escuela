<?php

use App\Modules\Bienestar\Controllers\BienestarController;
use Illuminate\Support\Facades\Route;

Route::prefix('bienestar')
    ->name('bienestar.')
    ->group(function () {
        Route::get('/', [BienestarController::class, 'index'])->name('index');
        Route::get('/crear', [BienestarController::class, 'create'])->name('create');
        Route::post('/', [BienestarController::class, 'store'])->name('store');
    });
