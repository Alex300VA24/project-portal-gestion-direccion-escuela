<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Modules\SeguimientoEgresado\Requests\SeguimientoEgresadoRequest;
use App\Modules\SeguimientoEgresado\Services\SeguimientoEgresadoService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class SeguimientoEgresadoController extends ModuleController
{
    protected const MODULE = 'Seguimiento al Egresado';

    protected const SCHEMA = 'seguimiento_egresado';

    protected const VIEW = 'modules.seguimiento-egresado.index';

    public function store(SeguimientoEgresadoRequest $request, SeguimientoEgresadoService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('seguimiento-egresado.index')
            ->with('status', 'Registro procesado.');
    }
}
