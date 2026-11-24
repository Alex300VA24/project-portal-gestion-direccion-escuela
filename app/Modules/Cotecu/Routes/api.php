<?php

use App\Modules\Cotecu\Controllers\CotecuController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotecu')
    ->name('api.cotecu.')
    ->middleware(['auth', 'permission:cotecu.view'])
    ->group(function () {
        Route::get('/', [CotecuController::class, 'apiIndex'])->name('index');
    });
