<?php

use App\Modules\GestionPracticas\Controllers\PracticaController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-practicas')
    ->name('api.gestion-practicas.')
    ->group(function () {
        Route::get('/', [PracticaController::class, 'apiIndex'])->name('index');
    });
