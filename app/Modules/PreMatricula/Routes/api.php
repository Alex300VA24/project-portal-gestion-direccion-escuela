<?php

use App\Modules\PreMatricula\Controllers\PreMatriculaController;
use Illuminate\Support\Facades\Route;

Route::prefix('pre-matricula')
    ->name('api.pre-matricula.')
    ->group(function () {
        Route::get('/', [PreMatriculaController::class, 'apiIndex'])->name('index');
    });
