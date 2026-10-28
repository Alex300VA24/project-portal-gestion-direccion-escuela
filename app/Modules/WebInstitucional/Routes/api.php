<?php

use App\Modules\WebInstitucional\Controllers\WebInstitucionalController;
use Illuminate\Support\Facades\Route;

Route::prefix('web-institucional')
    ->name('api.web-institucional.')
    ->group(function () {
        Route::get('/', [WebInstitucionalController::class, 'apiIndex'])->name('index');
    });
