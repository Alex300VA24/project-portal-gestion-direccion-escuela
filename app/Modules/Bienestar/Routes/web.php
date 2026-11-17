<?php

use App\Modules\Bienestar\Controllers\AlumnoBienestarController;
use App\Modules\Bienestar\Controllers\BienestarController;
use App\Modules\Bienestar\Controllers\BienestarPanelController;
use App\Modules\Bienestar\Controllers\DirectorEstadisticasController;
use App\Modules\Bienestar\Controllers\ProfesorReporteController;
use Illuminate\Support\Facades\Route;

Route::prefix('bienestar')
    ->name('bienestar.')
    ->group(function () {
        // Landing base del módulo (requerido por ModularRouteTest)
        Route::get('/', [BienestarController::class, 'index'])->name('index');
        Route::get('/crear', [BienestarController::class, 'create'])->name('create');
        Route::post('/', [BienestarController::class, 'store'])->name('store');

        // 1. Sitio informativo público (y gestión de artículos por bienestar)
        Route::prefix('informacion')->name('informacion.')->group(function () {
            Route::get('/', [BienestarController::class, 'informacion'])->name('index');
            Route::get('/crear', [BienestarController::class, 'createArticulo'])->middleware(['auth', 'can:bienestar.gestionar'])->name('create');
            Route::post('/', [BienestarController::class, 'storeArticulo'])->middleware(['auth', 'can:bienestar.gestionar'])->name('store');
            Route::get('/{articulo}', [BienestarController::class, 'showArticulo'])->name('show');
            Route::get('/{articulo}/editar', [BienestarController::class, 'editArticulo'])->middleware(['auth', 'can:bienestar.gestionar'])->name('edit');
            Route::put('/{articulo}', [BienestarController::class, 'updateArticulo'])->middleware(['auth', 'can:bienestar.gestionar'])->name('update');
        });

        // 2. Alumno: solicitudes de apoyo y citas
        Route::prefix('alumno')->name('alumno.')->middleware(['auth', 'can:bienestar.solicitar'])->group(function () {
            Route::get('/solicitudes', [AlumnoBienestarController::class, 'solicitudes'])->name('solicitudes.index');
            Route::get('/solicitudes/crear', [AlumnoBienestarController::class, 'crearSolicitud'])->name('solicitudes.create');
            Route::post('/solicitudes', [AlumnoBienestarController::class, 'guardarSolicitud'])->name('solicitudes.store');
            Route::get('/citas', [AlumnoBienestarController::class, 'citas'])->name('citas.index');
        });

        // 3. Docente: reporte y seguimiento de riesgo
        Route::prefix('profesor')->name('profesor.')->middleware(['auth', 'can:bienestar.reportar'])->group(function () {
            Route::get('/', [ProfesorReporteController::class, 'index'])->name('index');
            Route::get('/reportar', [ProfesorReporteController::class, 'create'])->name('create');
            Route::post('/reportar', [ProfesorReporteController::class, 'store'])->name('store');
            Route::get('/buscar-estudiante', [ProfesorReporteController::class, 'buscarEstudiante'])->name('buscar-estudiante');
        });

        // 4. Panel de Bienestar: Hub operativo
        Route::prefix('panel')->name('panel.')->middleware(['auth', 'can:bienestar.gestionar'])->group(function () {
            Route::get('/', [BienestarPanelController::class, 'index'])->name('index');
            Route::get('/alertas/crear', [BienestarPanelController::class, 'createAlertaManual'])->name('alertas.create');
            Route::post('/alertas/manual', [BienestarPanelController::class, 'storeAlertaManual'])->name('alertas.store-manual');
            Route::get('/alertas/{alerta}/atender', [BienestarPanelController::class, 'atender'])->name('atender');
            Route::post('/alertas/{alerta}/atender', [BienestarPanelController::class, 'guardarAtencion'])->name('guardar-atencion');
            Route::get('/casos/{caso}/derivar', [BienestarPanelController::class, 'formularioDerivacion'])->name('derivar');
            Route::post('/derivaciones', [BienestarPanelController::class, 'guardarDerivacion'])->name('guardar-derivacion');
            Route::get('/derivaciones/{derivacion}/imprimir', [BienestarPanelController::class, 'imprimirDerivacion'])->name('imprimir-derivacion');
            Route::post('/derivaciones/{derivacion}/resultado', [BienestarPanelController::class, 'registrarResultado'])->name('registrar-resultado');
            Route::get('/solicitudes', [BienestarPanelController::class, 'solicitudes'])->name('solicitudes.index');
            Route::post('/solicitudes/{solicitud}/evaluar', [BienestarPanelController::class, 'evaluarSolicitud'])->name('solicitudes.evaluar');
        });

        // 5. Director de Escuela: Estadísticas y métricas agregadas (solo lectura)
        Route::prefix('director')->name('director.')->middleware(['auth', 'can:bienestar.estadisticas'])->group(function () {
            Route::get('/', [DirectorEstadisticasController::class, 'index'])->name('index');
            Route::match(['post', 'put', 'patch', 'delete'], '/{any?}', [DirectorEstadisticasController::class, 'mutacionBloqueada'])->where('any', '.*');
        });
    });
