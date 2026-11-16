<?php

use App\Modules\TesisProyectoTesis\Controllers\ArchivoController;
use App\Modules\TesisProyectoTesis\Controllers\InicioController;
use App\Modules\TesisProyectoTesis\Services\ExpedienteService;
use Illuminate\Support\Facades\Route;

/*
 * Módulo Tesis y Proyecto de Tesis. Solo la portada (index) responde sin sesión y no muestra datos;
 * todo lo demás exige auth + verified, el rol Spatie del carril y la Policy del proyecto.
 */
Route::prefix('tesis-proyecto-tesis')
    ->name('tesis-proyecto-tesis.')
    ->group(function () {
        Route::get('/', [InicioController::class, 'index'])->name('index');

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::post('/notificaciones/leer', [InicioController::class, 'marcarLeidas'])->name('notificaciones.leer');

            Route::get('/archivos/{tipo}/{uuid}', [ArchivoController::class, 'descargar'])
                ->whereIn('tipo', ExpedienteService::TIPOS)
                ->whereUuid('uuid')
                ->name('archivos.descargar');
        });
    });
