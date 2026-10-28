<?php

use App\Modules\WebInstitucional\Controllers\WebInstitucionalController;
use Illuminate\Support\Facades\Route;

Route::prefix('web-institucional')
    ->name('web-institucional.')
    ->group(function () {
        Route::get('/', [WebInstitucionalController::class, 'index'])->name('index');
        Route::get('/crear', [WebInstitucionalController::class, 'create'])->name('create');
        Route::post('/', [WebInstitucionalController::class, 'store'])->name('store');
    });
