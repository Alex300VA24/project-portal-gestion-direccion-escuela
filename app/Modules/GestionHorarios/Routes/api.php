<?php

use App\Modules\GestionHorarios\Controllers\HorarioController;
use Illuminate\Support\Facades\Route;

Route::get('gestion-horarios', [HorarioController::class, 'apiIndex'])
    ->name('api.gestion-horarios.index');

Route::prefix('gestion-horarios')
    ->name('api.gestion-horarios.')
    ->middleware(['web', 'auth:web', 'role:director_escuela|secretaria,web'])
    ->group(function () {
        Route::get('/administracion', [HorarioController::class, 'apiIndex'])->name('administracion');
    });
