<?php

use App\Modules\TesisProyectoTesis\Controllers\AlumnoController;
use App\Modules\TesisProyectoTesis\Controllers\ArchivoController;
use App\Modules\TesisProyectoTesis\Controllers\AsesorController;
use App\Modules\TesisProyectoTesis\Controllers\DirectorController;
use App\Modules\TesisProyectoTesis\Controllers\InicioController;
use App\Modules\TesisProyectoTesis\Controllers\JuradoController;
use App\Modules\TesisProyectoTesis\Controllers\SecretariaController;
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

            // Estudiante
            Route::middleware('role:alumno')->prefix('alumno')->name('alumno.')->group(function () {
                Route::get('/', [AlumnoController::class, 'panel'])->name('panel');
                Route::get('/proyecto', [AlumnoController::class, 'proyecto'])->name('proyecto');
                Route::get('/proyecto/registrar', [AlumnoController::class, 'registrar'])->name('proyecto.registrar');
                Route::post('/proyecto/registrar', [AlumnoController::class, 'guardar'])->name('proyecto.guardar');
                Route::get('/coautor', [AlumnoController::class, 'coautor'])->middleware('throttle:20,1')->name('coautor');
                Route::get('/avances', [AlumnoController::class, 'irAvances'])->name('avances');
                Route::get('/observaciones', [AlumnoController::class, 'irObservaciones'])->name('observaciones');

                Route::prefix('proyecto/{proyecto}')->whereUuid('proyecto')->middleware('can:verComoAlumno,proyecto')->group(function () {
                    Route::get('/avances', [AlumnoController::class, 'avances'])->name('proyecto.avances');
                    Route::post('/avances', [AlumnoController::class, 'subirAvance'])->name('proyecto.avances.subir');
                    Route::get('/observaciones', [AlumnoController::class, 'observaciones'])->name('proyecto.observaciones');
                });
                Route::post('/observaciones/{observacion}/responder', [AlumnoController::class, 'responder'])
                    ->whereUuid('observacion')->name('observaciones.responder');
            });

            // Asesor (profesor con designación activa en el proyecto)
            Route::middleware('role:profesor')->prefix('asesor')->name('asesor.')->group(function () {
                Route::get('/proyectos', [AsesorController::class, 'proyectos'])->name('proyectos');

                Route::prefix('proyectos/{proyecto}')->whereUuid('proyecto')->middleware('can:verComoAsesor,proyecto')->scopeBindings()->group(function () {
                    Route::get('/', [AsesorController::class, 'proyecto'])->name('proyecto');
                    Route::get('/entregas/{entrega}/revisar', [AsesorController::class, 'revisar'])->whereUuid('entrega')->name('revisar');
                    Route::post('/entregas/{entrega}/revisar', [AsesorController::class, 'guardarRevision'])->whereUuid('entrega')->name('revisar.guardar');
                    Route::post('/observaciones/{observacion}/levantar', [AsesorController::class, 'levantar'])->whereUuid('observacion')->withoutScopedBindings()->name('observaciones.levantar');
                    Route::get('/verificar', [AsesorController::class, 'verificar'])->name('verificar');
                    Route::post('/verificar', [AsesorController::class, 'guardarVerificacion'])->name('verificar.guardar');
                    Route::get('/similitud', [AsesorController::class, 'similitud'])->name('similitud');
                    Route::post('/similitud', [AsesorController::class, 'registrarSimilitud'])->name('similitud.registrar');
                });
            });

            // Director de Escuela
            Route::middleware('role:director_escuela')->prefix('director')->name('director.')->group(function () {
                Route::get('/', [DirectorController::class, 'panel'])->name('panel');
                Route::get('/proyectos', [DirectorController::class, 'proyectos'])->name('proyectos');
                Route::get('/proyectos/{proyecto}/solicitar-asesor', [DirectorController::class, 'solicitarAsesor'])
                    ->middleware('can:gestionarComoDirector,proyecto')->whereUuid('proyecto')->name('asesor.solicitar');
                Route::post('/proyectos/{proyecto}/solicitar-asesor', [DirectorController::class, 'enviarSolicitudAsesor'])->whereUuid('proyecto')->name('asesor.enviar');
                Route::get('/proyectos/{proyecto}/jurado', [DirectorController::class, 'jurado'])
                    ->middleware('can:gestionarComoDirector,proyecto')->whereUuid('proyecto')->name('jurado');
                Route::post('/proyectos/{proyecto}/jurado', [DirectorController::class, 'asignarJurado'])->whereUuid('proyecto')->name('jurado.asignar');
                Route::post('/proyectos/{proyecto}/jurado/reemplazar', [DirectorController::class, 'reemplazarJurado'])->whereUuid('proyecto')->name('jurado.reemplazar');
            });

            // Jurado (profesor con asignación activa en el proyecto)
            Route::middleware('role:profesor')->prefix('jurado')->name('jurado.')->group(function () {
                Route::get('/proyectos', [JuradoController::class, 'proyectos'])->name('proyectos');
                Route::get('/sustentaciones', [JuradoController::class, 'sustentaciones'])->name('sustentaciones');
                Route::get('/sustentaciones/{sustentacion}/resultado', [JuradoController::class, 'resultado'])->whereUuid('sustentacion')->name('resultado');
                Route::post('/sustentaciones/{sustentacion}/resultado', [JuradoController::class, 'registrarResultado'])->whereUuid('sustentacion')->name('resultado.registrar');

                Route::prefix('proyectos/{proyecto}')->whereUuid('proyecto')->middleware('can:verComoJurado,proyecto')->group(function () {
                    Route::get('/verificar', [JuradoController::class, 'verificar'])->name('verificar');
                    Route::post('/verificar', [JuradoController::class, 'guardar'])->name('verificar.guardar');
                    Route::post('/observaciones/{observacion}/levantar', [JuradoController::class, 'levantar'])->whereUuid('observacion')->name('observaciones.levantar');
                });
            });

            // Secretaría
            Route::middleware('role:secretaria')->prefix('secretaria')->name('secretaria.')->group(function () {
                Route::get('/resoluciones', [SecretariaController::class, 'resoluciones'])->name('resoluciones');
                Route::post('/resoluciones/{solicitud}', [SecretariaController::class, 'registrarResolucion'])->whereUuid('solicitud')->name('resoluciones.registrar');
                Route::get('/sustentaciones', [SecretariaController::class, 'sustentaciones'])->name('sustentaciones');
                Route::get('/sustentaciones/programar/{proyecto}', [SecretariaController::class, 'programar'])
                    ->whereUuid('proyecto')->middleware('can:gestionarComoSecretaria,proyecto')->name('sustentaciones.programar');
                Route::post('/sustentaciones/programar/{proyecto}', [SecretariaController::class, 'guardarProgramacion'])->whereUuid('proyecto')->name('sustentaciones.guardar');
                Route::get('/actas', [SecretariaController::class, 'actas'])->name('actas');
                Route::get('/actas/{acta}', [SecretariaController::class, 'verActa'])->whereUuid('acta')->name('actas.ver');
                Route::post('/actas/{acta}/firmada', [SecretariaController::class, 'subirFirmada'])->whereUuid('acta')->name('actas.firmada');
                Route::post('/actas/{acta}/informar', [SecretariaController::class, 'informar'])->whereUuid('acta')->name('actas.informar');
            });
        });
    });
