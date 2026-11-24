<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\RespuestasParaPersonal;
use Illuminate\View\View;

/**
 * Contenido de la ventana «Ver respuesta»: se pide al pulsar el botón de una fila, para no cargar todas las respuestas con la lista.
 * Las rutas llevan su propio permiso (Secretaría para las de egresados, personal con acceso a la encuesta empresarial para las de empresas).
 */
final class RespuestasController extends Controller
{
    public function egresado(int $invitacion, RespuestasParaPersonal $respuestas): View
    {
        $datos = $respuestas->deEgresado($invitacion);
        abort_if($datos === null, 404);

        return view('modules.seguimiento-egresado.partials.respuesta-egresado', $datos);
    }

    public function empresa(int $invitacion, RespuestasParaPersonal $respuestas): View
    {
        $datos = $respuestas->deEmpresa($invitacion);
        abort_if($datos === null, 404);

        return view('modules.seguimiento-egresado.partials.respuesta-empresa', ['r' => $datos]);
    }
}
