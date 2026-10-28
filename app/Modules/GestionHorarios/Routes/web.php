<?php

use App\Modules\GestionHorarios\Controllers\HorarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-horarios')
    ->name('gestion-horarios.')
    ->group(function () {
        Route::get('/', [HorarioController::class, 'index'])->name('index');
        Route::get('/crear', [HorarioController::class, 'create'])->name('create');
        Route::post('/', [HorarioController::class, 'store'])->name('store');
    });
