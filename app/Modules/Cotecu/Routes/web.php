<?php

use App\Modules\Cotecu\Controllers\CotecuController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotecu')
    ->name('cotecu.')
    ->group(function () {
        Route::get('/', [CotecuController::class, 'index'])->name('index');
        Route::get('/crear', [CotecuController::class, 'create'])->name('create');
        Route::post('/', [CotecuController::class, 'store'])->name('store');
    });
