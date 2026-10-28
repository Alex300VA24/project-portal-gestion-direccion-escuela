<?php

use App\Modules\GestionMatriculas\Controllers\MatriculaController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-matriculas')
    ->name('gestion-matriculas.')
    ->group(function () {
        Route::get('/', [MatriculaController::class, 'index'])->name('index');
        Route::get('/crear', [MatriculaController::class, 'create'])->name('create');
        Route::post('/', [MatriculaController::class, 'store'])->name('store');
    });
