<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Modules\GestionPracticas\Requests\PracticaRequest;
use App\Modules\GestionPracticas\Services\PracticaService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class PracticaController extends ModuleController
{
    protected const MODULE = 'Gestión de Prácticas';

    protected const SCHEMA = 'gestion_practicas';

    protected const VIEW = 'modules.gestion-practicas.index';

    public function store(PracticaRequest $request, PracticaService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('gestion-practicas.index')
            ->with('status', 'Registro procesado.');
    }
}
