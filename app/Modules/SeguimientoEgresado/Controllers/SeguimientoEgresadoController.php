<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\AvisosDeInicio;
use App\Shared\Controllers\ModuleController;
use Illuminate\View\View;

final class SeguimientoEgresadoController extends ModuleController
{
    protected const MODULE = 'Seguimiento al Egresado';

    protected const SCHEMA = 'seguimiento_egresado';

    protected const VIEW = 'modules.seguimiento-egresado.inicio';

    /** Portada: el personal ve además qué campañas están por cerrar y cuántas respuestas faltan. */
    public function index(): View
    {
        $personal = (bool) SesionAcceso::desde(request())->actual()?->puede('campanias.consultar');

        return view(static::VIEW, [
            'avisos' => $personal ? app(AvisosDeInicio::class)->obtener() : null,
            'puedeAdministrar' => $personal && (bool) SesionAcceso::desde(request())->actual()?->puede('campanias.administrar'),
        ]);
    }
}
