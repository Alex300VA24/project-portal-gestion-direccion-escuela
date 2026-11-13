<?php

use App\Modules\Cotecu\Controllers\CotecuController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotecu')
    ->name('cotecu.')
    ->middleware(['auth', 'permission:cotecu.view'])
    ->group(function () {
        Route::get('/', [CotecuController::class, 'index'])->name('index');

        Route::view('/mis-silabos', 'modules.cotecu.stub', [
            'titulo' => 'Mis sílabos',
            'descripcion' => 'Sílabos subidos, en revisión, aprobados y observados, con el detalle de cada revisión.',
        ])->middleware('permission:cotecu.silabo.upload')->name('mis-silabos');

        Route::view('/silabos', 'modules.cotecu.stub', [
            'titulo' => 'Sílabos',
            'descripcion' => 'Listado de sílabos del módulo COTECU.',
        ])->middleware('permission:cotecu.silabo.list')->name('silabos.index');

        Route::view('/silabos/revisiones', 'modules.cotecu.stub', [
            'titulo' => 'Revisar sílabos',
            'descripcion' => 'Sílabos enviados a revisión junto con su lista de cotejo.',
        ])->middleware('permission:cotecu.silabo.review')->name('silabos.revisiones');
    });
