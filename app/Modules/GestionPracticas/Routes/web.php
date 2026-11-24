<?php

use App\Modules\GestionPracticas\Controllers\ConvocatoriaEmpresaController;
use App\Modules\GestionPracticas\Controllers\ConvocatoriaEstudianteController;
use App\Modules\GestionPracticas\Controllers\DocumentoController;
use App\Modules\GestionPracticas\Controllers\EmpresaAdminController;
use App\Modules\GestionPracticas\Controllers\PanelController;
use App\Modules\GestionPracticas\Controllers\PerfilEmpresaController;
use App\Modules\GestionPracticas\Controllers\PostulacionController;
use App\Modules\GestionPracticas\Controllers\PracticaController;
use App\Modules\GestionPracticas\Controllers\PracticaEstudianteController;
use App\Modules\GestionPracticas\Controllers\RevisionConvocatoriaController;
use App\Modules\GestionPracticas\Controllers\RevisionPracticaController;
use App\Modules\GestionPracticas\Controllers\SupervisorController;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion-practicas')
    ->name('gestion-practicas.')
    ->group(function () {
        // Portada pública del módulo; quien ya tiene acceso pasa directo al panel.
        Route::get('/', [PracticaController::class, 'portada'])->name('index');

        Route::middleware(['auth', 'verified', 'permission:'.Acceso::VER])
            ->prefix('panel')
            ->group(function () {
                Route::get('/', PanelController::class)->name('panel');

                // Documentos: la autorización por práctica la resuelve PracticaPolicy.
                Route::post('practicas/{practica}/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
                Route::get('documentos/{documento}', [DocumentoController::class, 'descargar'])->name('documentos.descargar');
                Route::post('documentos/{documento}/revisar', [DocumentoController::class, 'revisar'])->name('documentos.revisar');

                // Administrador: empresas y revisiones.
                Route::middleware('permission:'.Acceso::EMPRESAS)->prefix('empresas')->name('empresas.')->group(function () {
                    Route::get('/', [EmpresaAdminController::class, 'index'])->name('index');
                    Route::get('crear', [EmpresaAdminController::class, 'create'])->name('create');
                    Route::post('/', [EmpresaAdminController::class, 'store'])->name('store');
                    Route::post('{empresa}/estado', [EmpresaAdminController::class, 'cambiarEstado'])->whereNumber('empresa')->name('estado');
                });

                Route::middleware('permission:'.Acceso::CONVOCATORIAS_REVISAR)
                    ->prefix('revision/convocatorias')->name('revision.convocatorias.')->group(function () {
                        Route::get('/', [RevisionConvocatoriaController::class, 'index'])->name('index');
                        Route::get('{convocatoria}', [RevisionConvocatoriaController::class, 'show'])->name('show');
                        Route::post('{convocatoria}/aprobar', [RevisionConvocatoriaController::class, 'aprobar'])->name('aprobar');
                        Route::post('{convocatoria}/observar', [RevisionConvocatoriaController::class, 'observar'])->name('observar');
                    });

                Route::middleware('permission:'.Acceso::PRACTICAS_VALIDAR)
                    ->prefix('revision/practicas')->name('revision.practicas.')->group(function () {
                        Route::get('/', [RevisionPracticaController::class, 'index'])->name('index');
                        Route::get('{practica}', [RevisionPracticaController::class, 'show'])->name('show');
                        Route::post('{practica}/validar', [RevisionPracticaController::class, 'validar'])->name('validar');
                        Route::post('{practica}/observar', [RevisionPracticaController::class, 'observar'])->name('observar');
                    });

                // Empresa: perfil y convocatorias.
                Route::middleware('permission:'.Acceso::CONVOCATORIAS_GESTIONAR)->prefix('empresa')->name('empresa.')->group(function () {
                    Route::get('perfil', [PerfilEmpresaController::class, 'edit'])->name('perfil.edit');
                    Route::put('perfil', [PerfilEmpresaController::class, 'update'])->name('perfil.update');
                    Route::put('perfil/password', [PerfilEmpresaController::class, 'password'])->name('perfil.password');

                    Route::prefix('convocatorias')->name('convocatorias.')->group(function () {
                        Route::get('/', [ConvocatoriaEmpresaController::class, 'index'])->name('index');
                        Route::get('crear', [ConvocatoriaEmpresaController::class, 'create'])->name('create');
                        Route::post('/', [ConvocatoriaEmpresaController::class, 'store'])->name('store');
                        Route::get('{convocatoria}', [ConvocatoriaEmpresaController::class, 'show'])->name('show');
                        Route::get('{convocatoria}/editar', [ConvocatoriaEmpresaController::class, 'edit'])->name('edit');
                        Route::put('{convocatoria}', [ConvocatoriaEmpresaController::class, 'update'])->name('update');
                        Route::post('{convocatoria}/enviar', [ConvocatoriaEmpresaController::class, 'enviar'])->name('enviar');
                        Route::post('{convocatoria}/cerrar', [ConvocatoriaEmpresaController::class, 'cerrar'])->name('cerrar');
                        Route::post('{convocatoria}/anular', [ConvocatoriaEmpresaController::class, 'anular'])->name('anular');
                    });
                });

                // Estudiante: convocatorias, postulaciones y prácticas.
                Route::prefix('estudiante')->name('estudiante.')->group(function () {
                    Route::middleware('permission:'.Acceso::CONVOCATORIAS_VER)->prefix('convocatorias')->name('convocatorias.')->group(function () {
                        Route::get('/', [ConvocatoriaEstudianteController::class, 'index'])->name('index');
                        Route::get('{convocatoria}', [ConvocatoriaEstudianteController::class, 'show'])->name('show');
                        Route::post('{convocatoria}/postular', [ConvocatoriaEstudianteController::class, 'postular'])->name('postular');
                    });

                    Route::middleware('permission:'.Acceso::POSTULAR)->prefix('postulaciones')->name('postulaciones.')->group(function () {
                        Route::get('/', [PostulacionController::class, 'index'])->name('index');
                        Route::post('{postulacion}/resultado', [PostulacionController::class, 'resultado'])->name('resultado');
                    });

                    Route::middleware('permission:'.Acceso::PRACTICAS_REGISTRAR)->prefix('practicas')->name('practicas.')->group(function () {
                        Route::get('/', [PracticaEstudianteController::class, 'index'])->name('index');
                        Route::get('registrar/{postulacion}', [PracticaEstudianteController::class, 'create'])->name('create');
                        Route::post('registrar/{postulacion}', [PracticaEstudianteController::class, 'store'])->name('store');
                        Route::get('{practica}', [PracticaEstudianteController::class, 'show'])->name('show');
                        Route::get('{practica}/editar', [PracticaEstudianteController::class, 'edit'])->name('edit');
                        Route::put('{practica}', [PracticaEstudianteController::class, 'update'])->name('update');
                        Route::post('{practica}/enviar', [PracticaEstudianteController::class, 'enviar'])->name('enviar');
                    });
                });

                // Docente supervisor.
                Route::middleware('permission:'.Acceso::PRACTICAS_SUPERVISAR)
                    ->prefix('supervisor/practicas')->name('supervisor.practicas.')->group(function () {
                        Route::get('/', [SupervisorController::class, 'index'])->name('index');
                        Route::get('{practica}', [SupervisorController::class, 'show'])->name('show');
                        Route::post('{practica}/seguimientos', [SupervisorController::class, 'seguimiento'])->name('seguimiento');
                        Route::post('{practica}/evaluacion', [SupervisorController::class, 'evaluar'])->name('evaluar');
                        Route::post('{practica}/cerrar', [SupervisorController::class, 'cerrar'])->name('cerrar');
                    });
            });
    });
