<?php

use App\Modules\GestionPracticas\Controllers\PracticaController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-practicas')
    ->name('gestion-practicas.')
    ->group(function () {
        Route::get('/', [PracticaController::class, 'index'])->name('index');
        Route::get('/crear', [PracticaController::class, 'create'])->name('create');
        Route::post('/', [PracticaController::class, 'store'])->name('store');
    });
