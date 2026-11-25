<?php

use App\Modules\SeguimientoEstudiante\Controllers\AgendaDocenteController;
use App\Modules\SeguimientoEstudiante\Controllers\AsignacionTutoriaController;
use App\Modules\SeguimientoEstudiante\Controllers\AvisoTutoriaController;
use App\Modules\SeguimientoEstudiante\Controllers\DirectorioController;
use App\Modules\SeguimientoEstudiante\Controllers\DocumentosNivelacionController;
use App\Modules\SeguimientoEstudiante\Controllers\PlanGeneralTutoriaController;
use App\Modules\SeguimientoEstudiante\Controllers\PlanTutoriaController;
use App\Modules\SeguimientoEstudiante\Controllers\RecepcionSecretariaController;
use App\Modules\SeguimientoEstudiante\Controllers\SeguimientoEstudianteController;
use Illuminate\Support\Facades\Route;

Route::prefix('seguimiento-estudiante')
    ->name('seguimiento-estudiante.')
    ->middleware(['auth', 'can:seguimiento-estudiante.view'])
    ->group(function () {
        Route::get('/', [DirectorioController::class, 'index'])->name('index');
        Route::get('/expedientes/exportar', [DirectorioController::class, 'exportar'])
            ->middleware('can:seguimiento-estudiante.tutoria.view')->name('directorio.exportar');
        Route::get('/avisos-de-tutoria', [AvisoTutoriaController::class, 'index'])->name('avisos.index');
        Route::post('/avisos-de-tutoria/{aviso}/leer', [AvisoTutoriaController::class, 'leer'])
            ->whereNumber('aviso')->name('avisos.leer');
        Route::middleware('can:seguimiento-estudiante.tutoria.asignar')->group(function () {
            Route::get('/asignaciones-de-tutoria', [AsignacionTutoriaController::class, 'index'])->name('asignaciones.index');
            Route::get('/asignaciones-de-tutoria/{escuela}/{periodo}', [AsignacionTutoriaController::class, 'mostrar'])
                ->whereNumber(['escuela', 'periodo'])->name('asignaciones.mostrar');
            Route::post('/asignaciones-de-tutoria/{escuela}/{periodo}', [AsignacionTutoriaController::class, 'guardar'])
                ->whereNumber(['escuela', 'periodo'])->name('asignaciones.guardar');
            Route::post('/asignaciones-de-tutoria/{escuela}/{periodo}/finalizar', [AsignacionTutoriaController::class, 'finalizar'])
                ->whereNumber(['escuela', 'periodo'])->name('asignaciones.finalizar');
            Route::middleware('can:seguimiento-estudiante.tutoria.causas.gestionar')->group(function () {
                Route::get('/asignaciones-de-tutoria/{escuela}/{periodo}/causas', [AsignacionTutoriaController::class, 'catalogo'])
                    ->whereNumber(['escuela', 'periodo'])->name('asignaciones.causas');
                Route::post('/asignaciones-de-tutoria/{escuela}/{periodo}/causas', [AsignacionTutoriaController::class, 'guardarCatalogo'])
                    ->whereNumber(['escuela', 'periodo'])->name('asignaciones.causas.guardar');
            });
        });
        Route::middleware('can:seguimiento-estudiante.secretaria.lotes')->group(function () {
            Route::get('/secretaria/lotes', [RecepcionSecretariaController::class, 'lotes'])->name('secretaria.lotes.index');
            Route::get('/secretaria/lotes/{lote}/{revision?}', [RecepcionSecretariaController::class, 'lotes'])
                ->whereNumber(['lote', 'revision'])->name('secretaria.lotes.mostrar');
            Route::post('/secretaria/lotes', [RecepcionSecretariaController::class, 'guardarLote'])->name('secretaria.lotes.guardar');
            Route::post('/secretaria/lotes/{lote}/{revision}/confirmar', [RecepcionSecretariaController::class, 'confirmarLote'])
                ->whereNumber(['lote', 'revision'])->name('secretaria.lotes.confirmar');
        });
        Route::middleware('can:seguimiento-estudiante.secretaria.prevalidar')->group(function () {
            Route::get('/secretaria/plantillas/{tipo}', [RecepcionSecretariaController::class, 'plantilla'])
                ->whereIn('tipo', ['matriculas', 'notas', 'asistencia'])->name('secretaria.plantilla');
            Route::get('/secretaria/recepcion', [RecepcionSecretariaController::class, 'index'])->name('secretaria.recepcion');
            Route::post('/secretaria/recepcion', [RecepcionSecretariaController::class, 'prevalidar'])->name('secretaria.prevalidar');
        });
        Route::prefix('plan-general-de-tutoria')->name('plan-general.')->group(function () {
            Route::get('/', [PlanGeneralTutoriaController::class, 'index'])->name('index');
            Route::get('/{escuela}/{periodo}', [PlanGeneralTutoriaController::class, 'mostrar'])
                ->whereNumber(['escuela', 'periodo'])->name('mostrar');
            Route::get('/{escuela}/{periodo}/{documento}', [PlanGeneralTutoriaController::class, 'descargar'])
                ->whereNumber(['escuela', 'periodo'])->whereUuid('documento')->name('descargar');
            Route::middleware('can:seguimiento-estudiante.plan-general.publicar')->group(function () {
                Route::post('/{escuela}/{periodo}', [PlanGeneralTutoriaController::class, 'publicar'])
                    ->whereNumber(['escuela', 'periodo'])->name('publicar');
                Route::post('/{escuela}/{periodo}/{documento}/retirar', [PlanGeneralTutoriaController::class, 'retirar'])
                    ->whereNumber(['escuela', 'periodo'])->whereUuid('documento')->name('retirar');
            });
        });
        Route::middleware('can:seguimiento-estudiante.plan-tutoria.revisar')->group(function () {
            Route::get('/coordinacion/planes-de-tutoria', [PlanTutoriaController::class, 'coordinacion'])->name('planes.coordinacion');
            Route::get('/coordinacion/planes-de-tutoria/{escuela}/{periodo}', [PlanTutoriaController::class, 'presentados'])
                ->whereNumber(['escuela', 'periodo'])->name('planes.presentados');
            Route::post('/coordinacion/planes-de-tutoria/{escuela}/{periodo}/{autor}', [PlanTutoriaController::class, 'revisar'])
                ->whereNumber(['escuela', 'periodo', 'autor'])->name('planes.revisar');
        });
        Route::middleware(['can:seguimiento-estudiante.tutoria.view', 'can:seguimiento-estudiante.plan-tutoria.editar'])->group(function () {
            Route::get('/planes-de-tutoria', [PlanTutoriaController::class, 'index'])->name('planes.index');
            Route::get('/planes-de-tutoria/{escuela}/{periodo}', [PlanTutoriaController::class, 'mostrar'])
                ->whereNumber(['escuela', 'periodo'])->name('planes.mostrar');
            Route::post('/planes-de-tutoria/{escuela}/{periodo}', [PlanTutoriaController::class, 'guardar'])
                ->whereNumber(['escuela', 'periodo'])->name('planes.guardar');
        });
        Route::get('/nivelacion/docencia/agenda', [AgendaDocenteController::class, 'index'])
            ->middleware('can:seguimiento-estudiante.nivelacion.agenda-docente')->name('nivelacion.agenda-docente');
        Route::get('/mi-ingreso-y-nivelacion/{estudiante?}', [SeguimientoEstudianteController::class, 'ingresoNivelacion'])
            ->whereNumber('estudiante')->name('nivelacion.ingreso');
        Route::get('/mi-ingreso-y-nivelacion/{inscripcion}/resumen.pdf', [DocumentosNivelacionController::class, 'resumen'])
            ->whereNumber('inscripcion')->name('nivelacion.resumen');
        Route::get('/mi-ingreso-y-nivelacion/{inscripcion}/materiales/{archivo}', [DocumentosNivelacionController::class, 'material'])
            ->whereNumber(['inscripcion', 'archivo'])->name('nivelacion.material');
        Route::middleware('can:seguimiento-estudiante.nivelacion.documentos')->group(function () {
            Route::get('/nivelacion/documentos', [DocumentosNivelacionController::class, 'index'])->name('nivelacion.documentos');
            Route::get('/nivelacion/{programa}/{tipo}.pdf', [DocumentosNivelacionController::class, 'gestion'])
                ->whereNumber('programa')->whereIn('tipo', ['programa', 'informe'])->name('nivelacion.gestion');
            Route::post('/nivelacion/{programa}/materiales', [DocumentosNivelacionController::class, 'adjuntar'])
                ->whereNumber('programa')->middleware('can:seguimiento-estudiante.nivelacion.materiales.gestionar')
                ->name('nivelacion.adjuntar');
            Route::get('/nivelacion/{programa}/materiales/{archivo}', [DocumentosNivelacionController::class, 'materialEnGestion'])
                ->whereNumber(['programa', 'archivo'])->name('nivelacion.material-gestion');
            Route::post('/nivelacion/{programa}/materiales/{archivo}/visibilidad', [DocumentosNivelacionController::class, 'publicarMaterial'])
                ->whereNumber(['programa', 'archivo'])->middleware('can:seguimiento-estudiante.nivelacion.materiales.gestionar')
                ->name('nivelacion.visibilidad-material');
        });
        Route::get('/estudiantes/{estudiante}/expediente', [SeguimientoEstudianteController::class, 'expediente'])
            ->whereNumber('estudiante')
            ->name('expediente');
        Route::get('/mis-tutorias/{solicitud}/constancia.pdf', [SeguimientoEstudianteController::class, 'constanciaTutoria'])
            ->whereNumber('solicitud')->name('solicitudes.tutoria.constancia');
        Route::post('/estudiantes/{estudiante}/solicitudes/tutoria', [SeguimientoEstudianteController::class, 'solicitarTutoria'])
            ->whereNumber('estudiante')
            ->middleware(['can:seguimiento-estudiante.solicitud.create', 'throttle:10,1'])
            ->name('solicitudes.tutoria.store');
        Route::post('/estudiantes/{estudiante}/solicitudes/tutoria/{solicitud}/programar', [SeguimientoEstudianteController::class, 'programarTutoria'])
            ->whereNumber(['estudiante', 'solicitud'])
            ->middleware(['can:seguimiento-estudiante.tutoria.view', 'can:seguimiento-estudiante.tutoria.programar'])
            ->name('solicitudes.tutoria.programar');
        Route::post('/estudiantes/{estudiante}/solicitudes/tutoria/{solicitud}/solicitar-cambio', [SeguimientoEstudianteController::class, 'solicitarCambioTutoria'])
            ->whereNumber(['estudiante', 'solicitud'])
            ->middleware(['can:seguimiento-estudiante.solicitud.create'])
            ->name('solicitudes.tutoria.cambio');
        Route::post('/estudiantes/{estudiante}/solicitudes/tutoria/{solicitud}/{accion}', [SeguimientoEstudianteController::class, 'programarTutoria'])
            ->whereNumber(['estudiante', 'solicitud'])
            ->whereIn('accion', ['reprogramar', 'cancelar', 'mantener', 'registrar', 'corregir', 'cumplir'])
            ->middleware(['can:seguimiento-estudiante.tutoria.view', 'can:seguimiento-estudiante.tutoria.programar'])
            ->name('solicitudes.tutoria.resolver');
    });
