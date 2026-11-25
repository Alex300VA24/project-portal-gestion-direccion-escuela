<?php

use App\Modules\Bienestar\Controllers\AlumnoBienestarController;
use App\Modules\Bienestar\Controllers\BienestarController;
use App\Modules\Bienestar\Controllers\BienestarPanelController;
use App\Modules\Bienestar\Controllers\CasoBienestarController;
use App\Modules\Bienestar\Controllers\DirectorEstadisticasController;
use App\Modules\Bienestar\Controllers\NotificacionController;
use App\Modules\Bienestar\Controllers\ProfesorReporteController;
use App\Modules\Bienestar\Controllers\RequisitoController;
use App\Modules\Bienestar\Controllers\SecretariaBienestarController;
use App\Modules\Bienestar\Controllers\TipoSolicitudController;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Support\Facades\Route;

Route::prefix('bienestar')
    ->name('bienestar.')
    // Identificadores numéricos (bigint): cualquier otro valor responde 404
    ->where(array_fill_keys(['articulo', 'solicitud', 'alerta', 'documento', 'tipoSolicitud', 'caso', 'derivacion', 'cita', 'requisito', 'notificacion'], '[0-9]{1,18}'))
    ->group(function () {
        // Landing base del módulo
        Route::get('/', [BienestarController::class, 'index'])->name('index');
        Route::get('/crear', [BienestarController::class, 'create'])->name('create');
        Route::post('/', [BienestarController::class, 'store'])->name('store');

        // 1. Sitio informativo público (y gestión de artículos)
        Route::prefix('informacion')->name('informacion.')->group(function () {
            Route::get('/', [BienestarController::class, 'informacion'])->name('index');
            Route::get('/crear', [BienestarController::class, 'createArticulo'])
                ->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])
                ->name('create');
            Route::post('/', [BienestarController::class, 'storeArticulo'])
                ->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])
                ->name('store');
            Route::get('/{articulo}', [BienestarController::class, 'showArticulo'])->name('show');
            Route::get('/{articulo}/editar', [BienestarController::class, 'editArticulo'])
                ->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])
                ->name('edit');
            Route::put('/{articulo}', [BienestarController::class, 'updateArticulo'])
                ->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])
                ->name('update');
        });

        // Comunes a todos los roles autenticados
        Route::middleware(['auth', 'password.change'])->group(function () {
            Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
            Route::get('/notificaciones/recientes', [NotificacionController::class, 'recientes'])->name('notificaciones.recientes');
            Route::post('/notificaciones/leidas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
            Route::get('/notificaciones/{notificacion}', [NotificacionController::class, 'abrir'])->name('notificaciones.abrir');
            Route::get('/requisitos/{requisito}/plantilla', [RequisitoController::class, 'plantilla'])->name('requisitos.plantilla');
        });

        // 2. Alumno: solicitudes de apoyo y citas
        Route::prefix('alumno')->name('alumno.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::SOLICITAR)])->group(function () {
            Route::get('/solicitudes', [AlumnoBienestarController::class, 'solicitudes'])->name('solicitudes.index');
            Route::get('/solicitudes/crear', [AlumnoBienestarController::class, 'crearSolicitud'])->name('solicitudes.create');
            Route::post('/solicitudes', [AlumnoBienestarController::class, 'guardarSolicitud'])->name('solicitudes.store');
            Route::get('/solicitudes/{solicitud}', [AlumnoBienestarController::class, 'mostrarSolicitud'])->name('solicitudes.show');
            Route::get('/solicitudes/{solicitud}/subsanar', [AlumnoBienestarController::class, 'subsanarSolicitud'])->name('solicitudes.subsanar');
            Route::post('/solicitudes/{solicitud}/subsanar', [AlumnoBienestarController::class, 'guardarSubsanacion'])->name('solicitudes.guardar-subsanacion');
            Route::get('/citas', [AlumnoBienestarController::class, 'citas'])->name('citas.index');
        });

        // 3. Docente: reporte y seguimiento de riesgo
        Route::prefix('profesor')->name('profesor.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::REPORTAR)])->group(function () {
            Route::get('/', [ProfesorReporteController::class, 'index'])->name('index');
            Route::get('/reportar', [ProfesorReporteController::class, 'create'])->name('create');
            Route::post('/reportar', [ProfesorReporteController::class, 'store'])->name('store');
            Route::get('/buscar-estudiante', [ProfesorReporteController::class, 'buscarEstudiante'])->name('buscar-estudiante');
        });

        // 4. Secretaría: Filtro administrativo, revisión de requisitos y foliado externo
        Route::prefix('secretaria')->name('secretaria.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::SECRETARIA)])->group(function () {
            Route::get('/alertas', [SecretariaBienestarController::class, 'alertas'])->name('alertas');
            Route::post('/alertas/{alerta}/filtrar', [SecretariaBienestarController::class, 'procesarAlerta'])->name('alertas.filtrar');
            Route::get('/solicitudes', [SecretariaBienestarController::class, 'solicitudes'])->name('solicitudes');
            Route::get('/solicitudes/{solicitud}/revisar', [SecretariaBienestarController::class, 'revisarSolicitud'])->name('solicitudes.revisar');
            Route::post('/solicitudes/{solicitud}/revisar', [SecretariaBienestarController::class, 'guardarRevision'])->name('solicitudes.guardar-revision');
            Route::get('/documentos/{documento}/descargar', [SecretariaBienestarController::class, 'descargarDocumento'])->name('documentos.descargar');
            Route::get('/indicadores', [SecretariaBienestarController::class, 'indicadores'])->name('indicadores');
        });

        // 5. Catálogo de Tipos de Solicitud UNT (Administrado por Bienestar)
        Route::prefix('catalogo-solicitudes')->name('catalogo-solicitudes.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])->group(function () {
            Route::get('/', [TipoSolicitudController::class, 'index'])->name('index');
            Route::get('/crear', [TipoSolicitudController::class, 'create'])->name('create');
            Route::post('/', [TipoSolicitudController::class, 'store'])->name('store');
            Route::get('/{tipoSolicitud}/editar', [TipoSolicitudController::class, 'edit'])->name('edit');
            Route::put('/{tipoSolicitud}', [TipoSolicitudController::class, 'update'])->name('update');
            Route::patch('/{tipoSolicitud}/toggle-activo', [TipoSolicitudController::class, 'toggleActivo'])->name('toggle-activo');
            Route::post('/{tipoSolicitud}/requisitos', [RequisitoController::class, 'store'])->name('requisitos.store');
            Route::put('/requisitos/{requisito}', [RequisitoController::class, 'update'])->name('requisitos.update');
            Route::patch('/requisitos/{requisito}/estado', [RequisitoController::class, 'toggle'])->name('requisitos.toggle');
        });

        // 6. Panel de Bienestar: Hub operativo, derivaciones y dictamen de solicitudes remitidas
        Route::prefix('panel')->name('panel.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::GESTIONAR)])->group(function () {
            Route::get('/', [BienestarPanelController::class, 'index'])->name('index');
            Route::get('/alertas/crear', [BienestarPanelController::class, 'createAlertaManual'])->name('alertas.create');
            Route::post('/alertas/manual', [BienestarPanelController::class, 'storeAlertaManual'])->name('alertas.store-manual');
            Route::get('/alertas/{alerta}/atender', [BienestarPanelController::class, 'atender'])->name('atender');
            Route::post('/alertas/{alerta}/atender', [BienestarPanelController::class, 'guardarAtencion'])->name('guardar-atencion');
            Route::post('/alertas/{alerta}/reabrir', [BienestarPanelController::class, 'reabrirAlerta'])->name('alertas.reabrir');
            Route::get('/casos', [CasoBienestarController::class, 'index'])->name('casos.index');
            Route::get('/casos/{caso}', [CasoBienestarController::class, 'show'])->name('casos.show');
            Route::post('/casos/{caso}/citas', [CasoBienestarController::class, 'programarCita'])->name('casos.citas');
            Route::post('/casos/{caso}/cerrar', [CasoBienestarController::class, 'cerrar'])->name('casos.cerrar');
            Route::post('/citas/{cita}/resultado', [CasoBienestarController::class, 'registrarCita'])->name('citas.registrar');
            Route::get('/casos/{caso}/derivar', [BienestarPanelController::class, 'formularioDerivacion'])->name('derivar');
            Route::post('/derivaciones', [BienestarPanelController::class, 'guardarDerivacion'])->name('guardar-derivacion');
            Route::get('/derivaciones/{derivacion}/imprimir', [BienestarPanelController::class, 'imprimirDerivacion'])->name('imprimir-derivacion');
            Route::post('/derivaciones/{derivacion}/resultado', [BienestarPanelController::class, 'registrarResultado'])->name('registrar-resultado');
            Route::get('/solicitudes', [BienestarPanelController::class, 'solicitudes'])->name('solicitudes.index');
            Route::get('/solicitudes/{solicitud}', [BienestarPanelController::class, 'mostrarSolicitud'])->name('solicitudes.show');
            Route::get('/documentos/{documento}/descargar', [BienestarPanelController::class, 'descargarDocumento'])->name('documentos.descargar');
            Route::post('/solicitudes/{solicitud}/evaluar', [BienestarPanelController::class, 'evaluarSolicitud'])->name('solicitudes.evaluar');
        });

        // 7. Director de Escuela: Estadísticas y métricas agregadas (solo lectura)
        Route::prefix('director')->name('director.')->middleware(['auth', 'password.change', BienestarPermissions::roleOrPermission(BienestarPermissions::ESTADISTICAS)])->group(function () {
            Route::get('/', [DirectorEstadisticasController::class, 'index'])->name('index');
            Route::get('/exportar-csv', [DirectorEstadisticasController::class, 'exportarCsv'])->name('exportar-csv');
            Route::get('/imprimir', [DirectorEstadisticasController::class, 'imprimir'])->name('imprimir');
            Route::match(['post', 'put', 'patch', 'delete'], '/{any?}', [DirectorEstadisticasController::class, 'mutacionBloqueada'])->where('any', '.*');
        });
    });
