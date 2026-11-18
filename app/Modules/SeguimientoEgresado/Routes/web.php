<?php

use App\Modules\SeguimientoEgresado\Controllers\Accesos\AccesoController;
use App\Modules\SeguimientoEgresado\Controllers\CampaniasController;
use App\Modules\SeguimientoEgresado\Controllers\Egresado\ContactoController;
use App\Modules\SeguimientoEgresado\Controllers\Egresado\EncuestaController;
use App\Modules\SeguimientoEgresado\Controllers\Egresado\ExpedienteController;
use App\Modules\SeguimientoEgresado\Controllers\Egresado\HistorialController;
use App\Modules\SeguimientoEgresado\Controllers\Empresas\EmpresasController;
use App\Modules\SeguimientoEgresado\Controllers\Empresas\EncuestaEmpresaController;
use App\Modules\SeguimientoEgresado\Controllers\Indicadores\IndicadoresController;
use App\Modules\SeguimientoEgresado\Controllers\InstitucionalController;
use App\Modules\SeguimientoEgresado\Controllers\Invitaciones\ContactoInicialController;
use App\Modules\SeguimientoEgresado\Controllers\PadronController;
use App\Modules\SeguimientoEgresado\Controllers\PromocionController;
use App\Modules\SeguimientoEgresado\Controllers\ReportesController;
use App\Modules\SeguimientoEgresado\Controllers\RespuestasController;
use App\Modules\SeguimientoEgresado\Controllers\SeguimientoEgresadoController;
use App\Modules\SeguimientoEgresado\Middleware\RequiereCapacidad;
use App\Modules\SeguimientoEgresado\Middleware\SincronizaEstadoDeCampanias;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
 * Seguimiento al Egresado.
 *
 * Tres zonas:
 *  - Personal de la Escuela (Secretaría y Dirección): inician sesión en el portal (`auth`) y cada ruta exige una
 *    capacidad del módulo (RequiereCapacidad). Los roles del portal se traducen a perfiles en SesionAcceso.
 *  - Egresado: entra con el enlace personal de su invitación, sin cuenta. Su sesión es propia del módulo.
 *  - Público con token: la empresa responde su encuesta con un enlace; no hay navegación interna.
 *
 * Capacidades y perfiles: docs/seguimiento-egresado/permisos-middleware.md
 */
Route::prefix('seguimiento-egresado')
    ->name('seguimiento-egresado.')
    ->middleware(SincronizaEstadoDeCampanias::class)
    ->group(function () {
        $interna = fn (string $capacidad): array => ['auth', RequiereCapacidad::class.':'.$capacidad];
        $egresado = fn (string $capacidad): array => [RequiereCapacidad::class.':'.$capacidad];

        // Portada del módulo (pública: presenta el módulo y lleva a cada área).
        Route::get('/', [SeguimientoEgresadoController::class, 'index'])->name('index');
        Route::get('/crear', [SeguimientoEgresadoController::class, 'create'])->name('create');
        Route::post('/', [SeguimientoEgresadoController::class, 'store'])->name('store');

        // Padrón oficial, cohortes e importación
        Route::get('/padron', [PromocionController::class, 'index'])->middleware($interna('padron.consultar'))->name('padron');
        Route::get('/padron/promocion/{promocion}', [PromocionController::class, 'show'])->whereNumber('promocion')->middleware($interna('padron.consultar'))->name('padron.promocion');
        Route::get('/padron/consulta', [PadronController::class, 'index'])->middleware($interna('padron.consultar'))->name('padron.consulta');
        Route::get('/padron/egresado/{id}', [PadronController::class, 'fichaEgresado'])->middleware($interna('padron.consultar'))->name('padron.ficha');
        Route::get('/padron/plantilla', [PadronController::class, 'descargarPlantilla'])->middleware($interna('padron.consultar'))->name('padron.plantilla');
        Route::post('/padron/importar', [PadronController::class, 'importar'])->middleware($interna('padron.gestionar'))->name('padron.importar');
        Route::post('/padron/egresado', [PadronController::class, 'guardarEgresado'])->middleware($interna('padron.gestionar'))->name('padron.guardar-egresado');
        Route::post('/padron/cohorte', [PadronController::class, 'guardarCohorte'])->middleware($interna('padron.gestionar'))->name('padron.guardar-cohorte');
        Route::post('/padron/plan-curricular', [PadronController::class, 'guardarPlanCurricular'])->middleware($interna('padron.gestionar'))->name('padron.guardar-plan');
        Route::post('/padron/egresado/{id}/confirmar-egreso', [PadronController::class, 'confirmarEgreso'])->whereNumber('id')->middleware($interna('padron.gestionar'))->name('padron.confirmar-egreso');
        Route::post('/padron/estado-academico', [PadronController::class, 'guardarEstadoAcademico'])->middleware($interna('padron.gestionar'))->name('padron.guardar-estado');

        // Contacto inicial: enlaces para que el egresado deje sus datos
        Route::prefix('padron/contacto-inicial')->group(function () use ($interna) {
            Route::post('/cohorte', [ContactoInicialController::class, 'invitarCohorte'])->middleware($interna('padron.gestionar'))->name('padron.contacto-inicial.cohorte');
            Route::post('/egresado/{egresado}', [ContactoInicialController::class, 'invitar'])->whereNumber('egresado')->middleware($interna('padron.gestionar'))->name('padron.contacto-inicial.egresado');
        });

        // Campañas e invitaciones
        Route::get('/campanias', [CampaniasController::class, 'index'])->middleware($interna('campanias.consultar'))->name('campanias');
        Route::get('/campanias/{id}/detalle', [CampaniasController::class, 'detalle'])->whereNumber('id')->middleware($interna('campanias.consultar'))->name('campanias.detalle');
        Route::get('/campanias/respuesta/{invitacion}', [RespuestasController::class, 'egresado'])->whereNumber('invitacion')->middleware($interna('seguimiento.consultar'))->name('campanias.respuesta');
        Route::post('/campanias', [CampaniasController::class, 'store'])->middleware($interna('campanias.administrar'))->name('campanias.store');
        Route::put('/campanias/{id}', [CampaniasController::class, 'update'])->middleware($interna('campanias.administrar'))->name('campanias.update');
        Route::post('/campanias/{id}/emitir-invitaciones', [CampaniasController::class, 'emitirInvitaciones'])->middleware($interna('campanias.administrar'))->name('campanias.emitir-invitaciones');
        Route::post('/campanias/{id}/recordar-pendientes', [CampaniasController::class, 'recordarPendientes'])->whereNumber('id')->middleware($interna('campanias.administrar'))->name('campanias.recordar-pendientes');
        Route::post('/campanias/invitacion/{id}/recordatorio', [CampaniasController::class, 'reenviarRecordatorio'])->middleware($interna('campanias.administrar'))->name('campanias.recordatorio');

        // Evaluación institucional de Dirección
        Route::get('/institucional', [InstitucionalController::class, 'index'])->middleware($interna('institucional.consultar'))->name('institucional');
        Route::post('/institucional/informe-logro', [InstitucionalController::class, 'guardarInformeLogro'])->middleware($interna('institucional.registrar'))->name('institucional.informe-logro');
        Route::post('/institucional/informe-competencias', [InstitucionalController::class, 'guardarInformeCompetencias'])->middleware($interna('institucional.registrar'))->name('institucional.informe-competencias');
        Route::post('/institucional/cambio', [InstitucionalController::class, 'guardarCambio'])->middleware($interna('institucional.registrar'))->name('institucional.cambio');
        Route::post('/institucional/plan-accion', [InstitucionalController::class, 'guardarPlanAccion'])->middleware($interna('institucional.registrar'))->name('institucional.guardar-plan');

        // Reportes y formatos institucionales F1 a F11
        Route::get('/reportes/campanias/csv', [ReportesController::class, 'exportarCampaniasCsv'])->middleware($interna('reportes.exportar'))->name('reportes.campanias.csv');
        Route::get('/reportes/campanias/{id}/invitaciones/csv', [ReportesController::class, 'exportarInvitacionesCsv'])->middleware($interna('reportes.exportar'))->name('reportes.invitaciones.csv');
        Route::get('/reportes/formato/{codigo}', [ReportesController::class, 'exportarFormato'])->middleware($interna('reportes.exportar'))->where('codigo', 'F(5|7|8|9|10|11)')->name('reportes.formato');

        // Encuesta empresarial e indicadores
        Route::get('/empresas', [EmpresasController::class, 'index'])->middleware($interna('empresas.resultados'))->name('empresas');
        Route::get('/empresas/respuesta/{invitacion}', [RespuestasController::class, 'empresa'])->whereNumber('invitacion')->middleware($interna('empresas.resultados'))->name('empresas.respuesta');
        Route::post('/empresas/enlaces', [EmpresasController::class, 'generarEnlace'])->middleware($interna('campanias.administrar'))->name('empresas.enlaces');
        Route::post('/empresas/enlaces/lote', [EmpresasController::class, 'generarLote'])->middleware($interna('campanias.administrar'))->name('empresas.enlaces.lote');
        Route::post('/empresas/enlaces/{id}/renovar', [EmpresasController::class, 'renovarEnlace'])->whereNumber('id')->middleware($interna('campanias.administrar'))->name('empresas.enlaces.renovar');
        Route::post('/empresas/enlaces/{id}/enviado', [EmpresasController::class, 'marcarEnviado'])->whereNumber('id')->middleware($interna('campanias.administrar'))->name('empresas.enlaces.enviado');
        Route::get('/indicadores', [IndicadoresController::class, 'index'])->middleware($interna('indicadores.consultar'))->name('indicadores');
        Route::get('/indicadores/exportar', [IndicadoresController::class, 'exportar'])->middleware($interna('reportes.exportar'))->name('indicadores.exportar');

        // Público con token: la empresa responde sin cuenta y sin navegación interna.
        Route::get('/encuesta-empresa/gracias', [EncuestaEmpresaController::class, 'gracias'])->name('encuesta-empresa.gracias');
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('/encuesta-empresa/{token}', [EncuestaEmpresaController::class, 'mostrar'])
                ->where('token', '[A-Za-z0-9]{16,128}')->name('encuesta-empresa');
            // Sin CSRF: no hay sesión que proteger y el token es el secreto. Así, dejar la encuesta abierta horas no da error 419.
            Route::post('/encuesta-empresa/{token}', [EncuestaEmpresaController::class, 'responder'])
                ->where('token', '[A-Za-z0-9]{16,128}')->withoutMiddleware(ValidateCsrfToken::class)->name('encuesta-empresa.responder');
        });

        // Entrada del egresado por el enlace personal de su invitación (sin cuenta ni contraseña).
        Route::middleware('throttle:30,1')->group(function () {
            Route::get('/acceso', [AccesoController::class, 'sinEnlace'])->name('acceso');
            Route::get('/acceso/{token}', [AccesoController::class, 'abrirEnlace'])->where('token', '[A-Za-z0-9_-]{16,128}')->name('acceso.enlace');
        });

        // Área del egresado: contacto, encuesta de seguimiento e historial propios. El expediente sale siempre de la
        // sesión del enlace, nunca de la URL.
        Route::prefix('egresado')->name('egresado.')->group(function () use ($egresado) {
            Route::get('/', [ExpedienteController::class, 'inicio'])->middleware($egresado('expediente.propio'))->name('inicio');

            Route::get('/contacto', [ContactoController::class, 'editar'])->middleware($egresado('expediente.propio'))->name('contacto');
            Route::post('/contacto', [ContactoController::class, 'guardar'])->middleware($egresado('expediente.propio'))->name('contacto.guardar');

            Route::get('/encuesta', [EncuestaController::class, 'mostrar'])->middleware($egresado('campanias.responder'))->name('encuesta');
            Route::post('/encuesta', [EncuestaController::class, 'responder'])->middleware($egresado('campanias.responder'))->name('encuesta.responder');

            Route::get('/historial', [HistorialController::class, 'index'])->middleware($egresado('seguimiento.propio'))->name('historial');
            Route::get('/historial/{medicion}', [HistorialController::class, 'detalle'])->whereNumber('medicion')
                ->middleware($egresado('seguimiento.propio'))->name('historial.detalle');
        });
    });
