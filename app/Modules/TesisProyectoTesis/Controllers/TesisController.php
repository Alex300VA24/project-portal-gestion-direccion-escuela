<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Requests\TesisRequest;
use App\Modules\TesisProyectoTesis\Services\TesisService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class TesisController extends ModuleController
{
    protected const MODULE = 'Tesis y Proyecto de Tesis';

    protected const SCHEMA = 'tesis_proyecto_tesis';

    protected const VIEW = 'modules.tesis-proyecto-tesis.index';

    public function store(TesisRequest $request, TesisService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('tesis-proyecto-tesis.index')
            ->with('status', 'Registro procesado.');
    }
}
