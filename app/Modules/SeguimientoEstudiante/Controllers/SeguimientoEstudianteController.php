<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Modules\SeguimientoEstudiante\Requests\SeguimientoEstudianteRequest;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class SeguimientoEstudianteController extends ModuleController
{
    protected const MODULE = 'Seguimiento al Estudiante';

    protected const SCHEMA = 'seguimiento_estudiante';

    protected const VIEW = 'modules.seguimiento-estudiante.index';

    public function store(SeguimientoEstudianteRequest $request, SeguimientoEstudianteService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('seguimiento-estudiante.index')
            ->with('status', 'Registro procesado.');
    }
}
