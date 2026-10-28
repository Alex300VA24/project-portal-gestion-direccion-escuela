<?php

namespace App\Modules\Calidad\Controllers;

use App\Modules\Calidad\Requests\CalidadRequest;
use App\Modules\Calidad\Services\CalidadService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class CalidadController extends ModuleController
{
    protected const MODULE = 'Calidad';

    protected const SCHEMA = 'calidad';

    protected const VIEW = 'modules.calidad.index';

    public function store(CalidadRequest $request, CalidadService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('calidad.index')
            ->with('status', 'Registro procesado.');
    }
}
