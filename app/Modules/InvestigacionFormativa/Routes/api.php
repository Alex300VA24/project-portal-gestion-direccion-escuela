<?php

use App\Modules\InvestigacionFormativa\Controllers\InvestigacionFormativaController;
use Illuminate\Support\Facades\Route;

Route::prefix('investigacion-formativa')
    ->name('api.investigacion-formativa.')
    ->group(function () {
        Route::get('/', [InvestigacionFormativaController::class, 'apiIndex'])->name('index');
    });
