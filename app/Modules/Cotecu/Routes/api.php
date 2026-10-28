<?php

use App\Modules\Cotecu\Controllers\CotecuController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotecu')
    ->name('api.cotecu.')
    ->group(function () {
        Route::get('/', [CotecuController::class, 'apiIndex'])->name('index');
    });
