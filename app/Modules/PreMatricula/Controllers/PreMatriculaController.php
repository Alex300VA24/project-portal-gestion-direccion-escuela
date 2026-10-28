<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Modules\PreMatricula\Requests\PreMatriculaRequest;
use App\Modules\PreMatricula\Services\PreMatriculaService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class PreMatriculaController extends ModuleController
{
    protected const MODULE = 'Pre Matrícula';

    protected const SCHEMA = 'pre_matricula';

    protected const VIEW = 'modules.pre-matricula.index';

    public function store(PreMatriculaRequest $request, PreMatriculaService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('pre-matricula.index')
            ->with('status', 'Registro procesado.');
    }
}
