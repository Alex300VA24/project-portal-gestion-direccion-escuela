<?php

namespace App\Modules\GestionMatriculas\Controllers;

use App\Modules\GestionMatriculas\Requests\MatriculaRequest;
use App\Modules\GestionMatriculas\Services\MatriculaService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class MatriculaController extends ModuleController
{
    protected const MODULE = 'Gestión de Matrículas';

    protected const SCHEMA = 'gestion_matriculas';

    protected const VIEW = 'modules.gestion-matriculas.index';

    public function store(MatriculaRequest $request, MatriculaService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('gestion-matriculas.index')
            ->with('status', 'Registro procesado.');
    }
}
