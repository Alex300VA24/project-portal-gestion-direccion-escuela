<?php

namespace App\Modules\Cotecu\Controllers;

use App\Modules\Cotecu\Requests\CotecuRequest;
use App\Modules\Cotecu\Services\CotecuService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class CotecuController extends ModuleController
{
    protected const MODULE = 'COTECU';

    protected const SCHEMA = 'cotecu';

    protected const VIEW = 'modules.cotecu.index';

    public function store(CotecuRequest $request, CotecuService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('cotecu.index')
            ->with('status', 'Registro procesado.');
    }
}
