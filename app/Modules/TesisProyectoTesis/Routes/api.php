<?php

use App\Modules\TesisProyectoTesis\Controllers\TesisController;
use Illuminate\Support\Facades\Route;

Route::prefix('tesis-proyecto-tesis')
    ->name('api.tesis-proyecto-tesis.')
    ->group(function () {
        Route::get('/', [TesisController::class, 'apiIndex'])->name('index');
    });
