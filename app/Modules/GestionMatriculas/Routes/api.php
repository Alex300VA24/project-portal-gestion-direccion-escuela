<?php

use App\Modules\GestionMatriculas\Controllers\MatriculaController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-matriculas')
    ->name('api.gestion-matriculas.')
    ->group(function () {
        Route::get('/', [MatriculaController::class, 'apiIndex'])->name('index');
    });
