<?php

namespace App\Modules\WebInstitucional\Controllers;

use App\Modules\WebInstitucional\Requests\WebInstitucionalRequest;
use App\Modules\WebInstitucional\Services\WebInstitucionalService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class WebInstitucionalController extends ModuleController
{
    protected const MODULE = 'Web Institucional';

    protected const SCHEMA = 'web_institucional';

    protected const VIEW = 'modules.web-institucional.index';

    public function store(WebInstitucionalRequest $request, WebInstitucionalService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('web-institucional.index')
            ->with('status', 'Registro procesado.');
    }
}
