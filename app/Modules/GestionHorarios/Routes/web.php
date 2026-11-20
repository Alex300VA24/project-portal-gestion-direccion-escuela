<?php

use App\Modules\GestionHorarios\Controllers\HorarioController;
use Illuminate\Support\Facades\Route;

Route::get('gestion-horarios/publicados', [HorarioController::class, 'publicados'])
    ->name('gestion-horarios.publicados');
Route::get('gestion-horarios', [HorarioController::class, 'index'])
    ->name('gestion-horarios.index');

Route::view('gestion-horarios/mi-disponibilidad', 'modules.gestion-horarios.mi-disponibilidad-pagina')
    ->middleware(['auth:web', 'role:profesor,web'])->name('gestion-horarios.mi-disponibilidad');
Route::get('gestion-horarios/consulta-publicados', [HorarioController::class, 'consultarPublicados'])
    ->middleware('auth:web')->name('gestion-horarios.consulta-publicados');
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
        Route::view('/administracion/disponibilidad-docente', 'modules.gestion-horarios.disponibilidad-docente-pagina')->name('disponibilidad-docente');
        Route::view('/administracion/ambientes', 'modules.gestion-horarios.ambientes-pagina')->name('ambientes');
        Route::view('/administracion/restricciones', 'modules.gestion-horarios.restricciones-pagina')->name('restricciones');
        Route::view('/administracion/preparacion-generacion', 'modules.gestion-horarios.preparacion-pagina')->name('preparacion');
        Route::view('/administracion/horarios', 'modules.gestion-horarios.horarios-pagina')->name('horarios');
        Route::get('/crear', [HorarioController::class, 'create'])->name('create');
        Route::post('/', [HorarioController::class, 'store'])->name('store');
    });
