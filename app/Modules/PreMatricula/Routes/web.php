<?php

use App\Modules\PreMatricula\Controllers\PreMatriculaController;
use Illuminate\Support\Facades\Route;

Route::prefix('pre-matricula')
    ->name('pre-matricula.')
    ->group(function () {
        Route::get('/', [PreMatriculaController::class, 'index'])->name('index');
        Route::get('/crear', [PreMatriculaController::class, 'create'])->name('create');
        Route::post('/', [PreMatriculaController::class, 'store'])->name('store');
    });
