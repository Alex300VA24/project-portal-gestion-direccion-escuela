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

        Route::get('/silabos', [CotecuController::class, 'silabos'])
            ->middleware('permission:cotecu.silabo.list')->name('silabos.index');

        Route::view('/silabos/revisiones', 'modules.cotecu.stub', [
            'titulo' => 'Revisar sílabos',
            'descripcion' => 'Sílabos enviados a revisión junto con su lista de cotejo.',
        ])->middleware('permission:cotecu.silabo.review')->name('silabos.revisiones');

        Route::get('/planes', [CotecuController::class, 'planes'])
            ->middleware('permission:cotecu.plan.list')->name('planes.index');
    });
