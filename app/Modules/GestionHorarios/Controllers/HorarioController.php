<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Requests\HorarioRequest;
use App\Modules\GestionHorarios\Services\HorarioService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;

final class HorarioController extends ModuleController
{
    protected const MODULE = 'Gestión de Horarios';

    protected const SCHEMA = 'gestion_horarios';

    protected const VIEW = 'modules.gestion-horarios.index';

    public function store(HorarioRequest $request, HorarioService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('gestion-horarios.index')
            ->with('status', 'Registro procesado.');
    }
}
