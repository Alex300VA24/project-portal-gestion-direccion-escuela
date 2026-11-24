<?php

use App\Modules\PreMatricula\Controllers\AlumnoController;
use App\Modules\PreMatricula\Controllers\ConstanciaController;
use App\Modules\PreMatricula\Controllers\CoordinacionController;
use App\Modules\PreMatricula\Controllers\CuentaController;
use App\Modules\PreMatricula\Controllers\DemandaController;
use App\Modules\PreMatricula\Controllers\EstudianteController;
use App\Modules\PreMatricula\Controllers\HistorialAcademicoController;
use App\Modules\PreMatricula\Controllers\PeriodoController;
use App\Modules\PreMatricula\Controllers\PlanEstudiosController;
use App\Modules\PreMatricula\Controllers\PreMatriculaController;
use App\Modules\PreMatricula\Controllers\RegistroController;
use App\Modules\PreMatricula\Controllers\RegistroEstudianteController;
use App\Modules\PreMatricula\Controllers\ResultadoOficialController;
use App\Modules\PreMatricula\Controllers\VentanaController;
use Illuminate\Support\Facades\Route;

Route::prefix('pre-matricula')
    ->name('pre-matricula.')
    ->group(function () {
        Route::get('/', [PreMatriculaController::class, 'entrada'])->name('index');

        Route::middleware('guest')->group(function () {
            Route::get('/registro', [RegistroEstudianteController::class, 'create'])->name('registro');
            Route::post('/registro', [RegistroEstudianteController::class, 'store'])->middleware('throttle:10,1')->name('registro.store');
        });

        Route::middleware(['auth', 'verified'])->group(function () {
            Route::get('/constancias/{revision}', ConstanciaController::class)->whereNumber('revision')->name('constancia');

            Route::get('/alumno/historial-academico', [HistorialAcademicoController::class, 'alumno'])->middleware('role:alumno')->name('alumno.historial-academico');
            Route::middleware('role:alumno')->prefix('alumno')->name('alumno.')->controller(AlumnoController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/cursos', 'agregar')->name('agregar');
                Route::delete('/cursos/{detalle}', 'retirar')->whereNumber('detalle')->name('retirar');
                Route::post('/registrar', 'registrar')->name('registrar');
                Route::post('/correccion', 'corregir')->name('corregir');
                Route::get('/historial', 'historial')->name('historial');
                Route::patch('/cursos/{detalle}/turno', 'cambiarTurno')->whereNumber('detalle')->name('turno');
            });

            Route::middleware('role:director_escuela|secretaria')->prefix('coordinacion')->name('coordinacion.')->group(function () {
                Route::get('/', CoordinacionController::class)->name('index');

                Route::get('/cuentas', [CuentaController::class, 'index'])->name('cuentas');
                Route::post('/cuentas', [CuentaController::class, 'store'])->name('cuentas.store');
                Route::get('/cuentas/{estudiante}/editar', [CuentaController::class, 'edit'])->whereNumber('estudiante')->name('cuentas.edit');
                Route::put('/cuentas/{estudiante}', [CuentaController::class, 'update'])->whereNumber('estudiante')->name('cuentas.update');
                Route::get('/periodos', [PeriodoController::class, 'index'])->name('periodos');
                Route::post('/periodos', [PeriodoController::class, 'store'])->name('periodos.store');
                Route::get('/periodos/{periodo}/editar', [PeriodoController::class, 'edit'])->whereNumber('periodo')->name('periodos.edit');
                Route::put('/periodos/{periodo}', [PeriodoController::class, 'update'])->whereNumber('periodo')->name('periodos.update');
                Route::delete('/periodos/{periodo}', [PeriodoController::class, 'destroy'])->whereNumber('periodo')->name('periodos.destroy');
                Route::get('/plan', [PlanEstudiosController::class, 'index'])->name('plan');
                Route::get('/plan/plantilla', [PlanEstudiosController::class, 'plantilla'])->name('plan.plantilla');
                Route::get('/plan/exportar', [PlanEstudiosController::class, 'exportar'])->name('plan.exportar');
                Route::post('/plan/validar', [PlanEstudiosController::class, 'validar'])->name('plan.validar');
                Route::post('/plan/confirmar', [PlanEstudiosController::class, 'confirmar'])->name('plan.confirmar');
                Route::get('/plan/cursos/{codigo}/editar', [PlanEstudiosController::class, 'edit'])->name('plan.cursos.edit');
                Route::put('/plan/cursos/{codigo}', [PlanEstudiosController::class, 'update'])->name('plan.cursos.update');
                Route::get('/ventanas', [VentanaController::class, 'index'])->name('ventanas');
                Route::post('/ventanas', [VentanaController::class, 'store'])->name('ventanas.store');

                Route::get('/resultados', [ResultadoOficialController::class, 'index'])->name('resultados');
                Route::get('/resultados/plantilla', [ResultadoOficialController::class, 'plantilla'])->name('resultados.plantilla');
                Route::post('/resultados/validar', [ResultadoOficialController::class, 'validar'])->name('resultados.validar');
                Route::post('/resultados/confirmar', [ResultadoOficialController::class, 'confirmar'])->name('resultados.confirmar');

                Route::get('/historial', [HistorialAcademicoController::class, 'index'])->name('historial');
                Route::get('/resultados/importaciones/{importacion}', [HistorialAcademicoController::class, 'importacion'])->whereNumber('importacion')->name('resultados.importacion');
                Route::get('/registros', [RegistroController::class, 'index'])->name('registros');
                Route::get('/registros/{solicitud}', [RegistroController::class, 'show'])->whereNumber('solicitud')->name('registros.show');

                Route::get('/demanda', [DemandaController::class, 'index'])->name('demanda');
                Route::get('/demanda/{periodo}/cursos/{cursoPlan}', [DemandaController::class, 'interesados'])->whereNumber(['periodo', 'cursoPlan'])->name('demanda.interesados');
                Route::get('/demanda/{periodo}/exportar', [DemandaController::class, 'exportar'])->whereNumber('periodo')->name('demanda.exportar');

                Route::get('/estudiantes', [EstudianteController::class, 'index'])->name('estudiantes');
                Route::get('/estudiantes/{codigo}', [EstudianteController::class, 'show'])->name('estudiantes.show');
            });
        });
    });
