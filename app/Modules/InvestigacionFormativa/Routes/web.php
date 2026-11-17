<?php

use App\Modules\InvestigacionFormativa\Controllers\ConfiguracionController;
use App\Modules\InvestigacionFormativa\Controllers\DirectorController;
use App\Modules\InvestigacionFormativa\Controllers\EncuestaController;
use App\Modules\InvestigacionFormativa\Controllers\EvaluacionController;
use App\Modules\InvestigacionFormativa\Controllers\GrupoController;
use App\Modules\InvestigacionFormativa\Controllers\InvestigacionFormativaController;
use Illuminate\Support\Facades\Route;

Route::prefix('investigacion-formativa')
    ->name('investigacion-formativa.')
    ->group(function () {
        Route::middleware(['auth', 'permission:investigacion-formativa.view'])->group(function () {
            Route::get('/', [InvestigacionFormativaController::class, 'index'])->name('index');

            Route::get('/director', [DirectorController::class, 'index'])
                ->middleware('permission:investigacion-formativa.courses.manage')
                ->name('director.index');

            Route::middleware('permission:investigacion-formativa.courses.manage')->group(function () {
                Route::get('/director/cursos', [DirectorController::class, 'cursos'])
                    ->name('director.cursos.index');

                Route::patch('/director/secciones/{seccion}/habilitacion', [DirectorController::class, 'habilitacion'])
                    ->name('director.secciones.habilitacion');
            });

            Route::middleware('permission:investigacion-formativa.surveys.manage')->group(function () {
                Route::get('/director/secciones/{seccion}/encuesta', [EncuestaController::class, 'editar'])
                    ->name('director.encuestas.edit');
                Route::put('/director/secciones/{seccion}/encuesta', [EncuestaController::class, 'actualizar'])
                    ->name('director.encuestas.update');
                Route::post('/director/secciones/{seccion}/encuesta/publicar', [EncuestaController::class, 'publicar'])
                    ->name('director.encuestas.publicar');
                Route::post('/director/secciones/{seccion}/encuesta/cerrar', [EncuestaController::class, 'cerrar'])
                    ->name('director.encuestas.cerrar');
            });

            Route::get('/docente/cursos', [ConfiguracionController::class, 'cursos'])
                ->middleware('permission:investigacion-formativa.configuration.manage')
                ->name('docente.cursos.index');

            Route::middleware('permission:investigacion-formativa.configuration.manage')->group(function () {
                Route::get('/docente/secciones/{seccion}/competencias', [ConfiguracionController::class, 'competencias'])
                    ->name('docente.competencias.edit');
                Route::put('/docente/secciones/{seccion}/competencias', [ConfiguracionController::class, 'actualizarCompetencias'])
                    ->name('docente.competencias.update');

                Route::get('/docente/secciones/{seccion}/rubrica', [ConfiguracionController::class, 'rubrica'])
                    ->name('docente.rubrica.edit');
                Route::put('/docente/secciones/{seccion}/rubrica', [ConfiguracionController::class, 'actualizarRubrica'])
                    ->name('docente.rubrica.update');

                Route::get('/docente/secciones/{seccion}/cronograma', [ConfiguracionController::class, 'cronograma'])
                    ->name('docente.cronograma.edit');
                Route::put('/docente/secciones/{seccion}/cronograma', [ConfiguracionController::class, 'actualizarCronograma'])
                    ->name('docente.cronograma.update');
            });

            Route::middleware('permission:investigacion-formativa.evaluations.manage')->group(function () {
                Route::put(
                    '/docente/secciones/{seccion}/grupos/{grupo}/entregas/{entrega}/evaluacion',
                    [EvaluacionController::class, 'update'],
                )->name('docente.evaluaciones.update');
            });

            Route::middleware('permission:investigacion-formativa.groups.review')->group(function () {
                Route::get('/docente/secciones/{seccion}/grupos', [GrupoController::class, 'docenteIndex'])
                    ->name('docente.grupos.index');

                Route::get('/docente/secciones/{seccion}/grupos/{grupo}', [GrupoController::class, 'docenteGrupo'])
                    ->name('docente.grupos.show');

                Route::patch('/docente/secciones/{seccion}/grupos/{grupo}/titulo', [GrupoController::class, 'revisarTitulo'])
                    ->name('docente.titulos.update');
            });

            Route::middleware('permission:investigacion-formativa.groups.create')->group(function () {
                Route::get('/alumno/secciones/{seccion}/grupo/crear', [GrupoController::class, 'crear'])
                    ->name('alumno.grupos.create');

                Route::post('/alumno/secciones/{seccion}/grupo', [GrupoController::class, 'store'])
                    ->name('alumno.grupos.store');
            });

            Route::middleware('permission:investigacion-formativa.surveys.respond')->group(function () {
                Route::get('/alumno/secciones/{seccion}/encuesta', [EncuestaController::class, 'mostrarEstudiante'])
                    ->name('alumno.encuestas.show');
                Route::post('/alumno/secciones/{seccion}/encuesta', [EncuestaController::class, 'responder'])
                    ->name('alumno.encuestas.store');
            });

            Route::get('/alumno/cursos', [GrupoController::class, 'cursos'])
                ->middleware('permission:investigacion-formativa.groups.create')
                ->name('alumno.cursos.index');

            Route::get('/alumno/secciones/{seccion}/mi-grupo', [GrupoController::class, 'mostrar'])
                ->name('alumno.grupos.show');

            Route::patch('/alumno/secciones/{seccion}/mi-grupo/titulo', [GrupoController::class, 'actualizarTitulo'])
                ->middleware('permission:investigacion-formativa.projects.update')
                ->name('alumno.titulos.update');
        });
    });
