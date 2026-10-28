<?php

use App\Modules\TesisProyectoTesis\Controllers\TesisController;
use Illuminate\Support\Facades\Route;

Route::prefix('tesis-proyecto-tesis')
    ->name('tesis-proyecto-tesis.')
    ->group(function () {
        Route::get('/', [TesisController::class, 'index'])->name('index');
        Route::get('/crear', [TesisController::class, 'create'])->name('create');
        Route::post('/', [TesisController::class, 'store'])->name('store');
    });
