<?php

use App\Modules\InvestigacionFormativa\Controllers\InvestigacionFormativaController;
use Illuminate\Support\Facades\Route;

Route::prefix('investigacion-formativa')
    ->name('investigacion-formativa.')
    ->group(function () {
        Route::get('/', [InvestigacionFormativaController::class, 'index'])->name('index');
        Route::get('/crear', [InvestigacionFormativaController::class, 'create'])->name('create');
        Route::post('/', [InvestigacionFormativaController::class, 'store'])->name('store');
    });
