<?php

use App\Modules\Cotecu\Controllers\CotecuController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotecu')
    ->name('cotecu.')
    ->middleware(['auth', 'permission:cotecu.view'])
    ->group(function () {
        Route::get('/', [CotecuController::class, 'index'])->name('index');

        Route::get('/mis-silabos', [CotecuController::class, 'misSilabos'])
            ->middleware('permission:cotecu.silabo.upload')->name('mis-silabos');

        Route::post('/mis-silabos', [CotecuController::class, 'storeSilabo'])
            ->middleware('permission:cotecu.silabo.upload')->name('mis-silabos.store');

        Route::get('/silabos', [CotecuController::class, 'silabos'])
            ->middleware('permission:cotecu.silabo.list')->name('silabos.index');

        Route::get('/silabos/revisiones', [CotecuController::class, 'revisionesSilabos'])
            ->middleware('permission:cotecu.silabo.review')->name('silabos.revisiones');

        Route::get('/silabos/revisiones/{revision}', [CotecuController::class, 'revisarSilabo'])
            ->middleware('permission:cotecu.silabo.review')->name('silabos.revisiones.show');

        Route::post('/silabos/revisiones/{revision}', [CotecuController::class, 'storeRevision'])
            ->middleware('permission:cotecu.silabo.review')->name('silabos.revisiones.store');

        Route::get('/planes', [CotecuController::class, 'planes'])
            ->middleware('permission:cotecu.plan.list')->name('planes.index');

        Route::post('/planes', [CotecuController::class, 'store'])
            ->middleware('permission:cotecu.plan.upload')->name('planes.store');
    });
