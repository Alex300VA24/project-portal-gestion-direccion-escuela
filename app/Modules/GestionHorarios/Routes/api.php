<?php

use App\Modules\GestionHorarios\Controllers\HorarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-horarios')
    ->name('api.gestion-horarios.')
    ->group(function () {
        Route::get('/', [HorarioController::class, 'apiIndex'])->name('index');
    });
