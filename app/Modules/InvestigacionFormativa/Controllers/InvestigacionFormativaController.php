<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Modules\InvestigacionFormativa\Requests\InvestigacionFormativaRequest;
use App\Modules\InvestigacionFormativa\Services\InvestigacionFormativaService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class InvestigacionFormativaController extends ModuleController
{
    protected const MODULE = 'Investigación Formativa';

    protected const SCHEMA = 'investigacion_formativa';

    protected const VIEW = 'modules.investigacion-formativa.index';

    public function store(InvestigacionFormativaRequest $request, InvestigacionFormativaService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('investigacion-formativa.index')
            ->with('status', 'Registro procesado.');
    }
}
