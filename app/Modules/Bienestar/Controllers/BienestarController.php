<?php

namespace App\Modules\Bienestar\Controllers;

use App\Modules\Bienestar\Requests\BienestarRequest;
use App\Modules\Bienestar\Services\BienestarService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class BienestarController extends ModuleController
{
    protected const MODULE = 'Bienestar';

    protected const SCHEMA = 'bienestar';

    protected const VIEW = 'modules.bienestar.index';

    public function store(BienestarRequest $request, BienestarService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('bienestar.index')
            ->with('status', 'Registro procesado.');
    }
}
