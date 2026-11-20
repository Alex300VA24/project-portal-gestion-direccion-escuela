<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Egresado;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Support\Accesos\Identidad;
use Illuminate\Http\Request;

/** Base de los controladores del egresado: el expediente consultado es siempre el de la sesión. */
abstract class EgresadoController extends Controller
{
    protected function identidad(Request $request): Identidad
    {
        $identidad = SesionAcceso::desde($request)->egresado();

        abort_unless($identidad !== null && $identidad->egresadoId !== null, 403, 'No tienes permiso para acceder a este recurso.');

        return $identidad;
    }
}
