<?php

use App\Modules\GestionHorarios\Controllers\HorarioController;
use App\Modules\GestionHorarios\Controllers\MiHorarioDocenteController;
use App\Modules\GestionHorarios\Controllers\RevisionHorarioTrabajoDemoController;
use App\Modules\GestionHorarios\Controllers\CicloVidaHorarioDemoController;
use Illuminate\Support\Facades\Route;

Route::get('gestion-horarios/publicados', [HorarioController::class, 'publicados'])
    ->name('gestion-horarios.publicados');
Route::get('gestion-horarios/catalogo-publico', [HorarioController::class, 'catalogoPublico'])
    ->name('gestion-horarios.catalogo-publico');
Route::get('gestion-horarios', [HorarioController::class, 'index'])
    ->name('gestion-horarios.index');

Route::get('gestion-horarios/mi-horario', [MiHorarioDocenteController::class, 'index'])
    ->middleware(['auth:web', 'role:profesor,web'])->name('gestion-horarios.mi-horario');
Route::view('gestion-horarios/mi-disponibilidad', 'modules.gestion-horarios.mi-disponibilidad-pagina')
    ->middleware(['auth:web', 'role:profesor,web'])->name('gestion-horarios.mi-disponibilidad');
Route::get('gestion-horarios/consulta', [HorarioController::class, 'consultarPublicados'])
    ->middleware('auth:web')->name('gestion-horarios.consulta-publicados');
Route::get('gestion-horarios/consulta-publicados', [HorarioController::class, 'consultarPublicados'])
    ->middleware('auth:web')->name('gestion-horarios.consulta-publicados.legado');
Route::get('gestion-horarios/panel', [HorarioController::class, 'panel'])
    ->middleware('auth:web')->name('gestion-horarios.panel');
Route::get('gestion-horarios/semana-publicada', [HorarioController::class, 'semanaPublicada'])
    ->middleware('auth:web')->name('gestion-horarios.semana-publicada');

Route::prefix('gestion-horarios')
    ->name('gestion-horarios.')
    ->middleware(['auth:web', 'role:director_escuela|secretaria,web'])
    ->group(function () {
        Route::get('/administracion', [HorarioController::class, 'index'])->name('administracion');
        Route::view('/administracion/oferta-academica', 'modules.gestion-horarios.oferta-pagina')->name('oferta');
        Route::view('/administracion/revisiones', 'modules.gestion-horarios.revisiones-pagina')->name('revisiones');
        Route::view('/administracion/periodos', 'modules.gestion-horarios.periodos-pagina')->name('periodos');
        Route::view('/administracion/secciones', 'modules.gestion-horarios.secciones-pagina')->name('secciones');
        Route::view('/administracion/asignacion-docente', 'modules.gestion-horarios.asignacion-docente-pagina')->name('asignacion-docente');
        Route::view('/administracion/catalogo-recursos-qa', 'modules.gestion-horarios.catalogo-recursos-qa-pagina')->name('catalogo-recursos-qa');
        Route::view('/administracion/prioridad-docente', 'modules.gestion-horarios.prioridad-docente-pagina')->name('prioridad-docente');
        Route::view('/administracion/disponibilidad-docente', 'modules.gestion-horarios.disponibilidad-docente-pagina')->name('disponibilidad-docente');
        Route::view('/administracion/ambientes', 'modules.gestion-horarios.ambientes-pagina')->name('ambientes');
        Route::view('/administracion/restricciones', 'modules.gestion-horarios.restricciones-pagina')->name('restricciones');
        Route::view('/administracion/preparacion-generacion', 'modules.gestion-horarios.preparacion-pagina')->name('preparacion');
        Route::view('/administracion/borradores-qa', 'modules.gestion-horarios.horarios-versionados-pagina')->name('borradores-qa');
        Route::get('/administracion/revision-trabajo-qa', [RevisionHorarioTrabajoDemoController::class, 'index'])->name('revision-trabajo-qa');
        Route::get('/administracion/revision-trabajo-qa/{version}/{formato}', [RevisionHorarioTrabajoDemoController::class, 'descargar'])->whereNumber('version')->whereIn('formato', ['pdf','xlsx'])->name('exportar-trabajo-qa');
        Route::post('/administracion/calendario-qa', [CicloVidaHorarioDemoController::class, 'calendario'])->name('calendario-qa.guardar');
        Route::post('/administracion/publicacion-qa', [CicloVidaHorarioDemoController::class, 'accion'])->name('publicacion-qa.accion');
        Route::view('/administracion/horarios', 'modules.gestion-horarios.horarios-pagina')->name('horarios');
        Route::get('/crear', [HorarioController::class, 'create'])->name('create');
        Route::post('/', [HorarioController::class, 'store'])->name('store');
    });
