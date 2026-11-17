<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Accesos;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Entrada del egresado: el enlace personal de la invitación lleva directo a su expediente, sin pedir matrícula,
 * documento ni contraseña (quien tiene el enlace entra). Si el sistema que unifique el módulo aporta su propia
 * identidad, basta con no cargar esta ruta: las páginas leen la identidad de SesionAcceso.
 */
final class AccesoController extends Controller
{
    /** Pantalla a la que se envía a quien intenta entrar a su área sin haber abierto su enlace. */
    public function sinEnlace(): View
    {
        return view('modules.seguimiento-egresado.acceso.enlace', ['noValido' => false]);
    }

    public function abrirEnlace(Request $request, EnlaceInvitacion $enlaces, string $token): RedirectResponse|Response
    {
        $resultado = $enlaces->abrir($token);

        if (! $resultado->esPermitido()) {
            // La misma respuesta para un enlace desconocido, anulado o vencido: no revela cuál es el caso.
            return response()->view('modules.seguimiento-egresado.acceso.enlace', ['noValido' => true], 404);
        }

        SesionAcceso::desde($request)->iniciarEgresado($resultado);

        return redirect()->route('seguimiento-egresado.egresado.inicio');
    }
}
