<?php

namespace App\Modules\Calidad\Controllers;

use App\Modules\Calidad\Requests\CalidadRequest;
use App\Modules\Calidad\Services\CalidadReadService;
use App\Modules\Calidad\Services\CalidadService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CalidadController extends ModuleController
{
    protected const MODULE = 'Calidad';

    protected const SCHEMA = 'calidad';

    protected const VIEW = 'modules.calidad.index';

    public function home(CalidadReadService $service): View
    {
        return view('modules.calidad.home', [
            'module' => self::MODULE,
            'schema' => self::SCHEMA,
            ...$service->home(),
        ]);
    }

    public function processMap(CalidadReadService $service): View
    {
        return view('modules.calidad.process-map', [
            'module' => self::MODULE,
            'schema' => self::SCHEMA,
            ...$service->processMap(),
        ]);
    }

    public function indicators(Request $request, CalidadReadService $service): View
    {
        $periodId = $request->integer('periodo') ?: null;

        return view('modules.calidad.indicators', [
            'module' => self::MODULE,
            'schema' => self::SCHEMA,
            ...$service->indicators($periodId),
        ]);
    }

    public function documents(Request $request, CalidadReadService $service): View
    {
        return view('modules.calidad.documents', [
            'module' => self::MODULE,
            'schema' => self::SCHEMA,
            ...$service->documents(
                $request->string('tipo')->toString() ?: 'silabo',
                $request->integer('periodo') ?: null,
                $request->integer('ciclo') ?: null,
            ),
        ]);
    }

    public function store(CalidadRequest $request, CalidadService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('calidad.index')
            ->with('status', 'Registro procesado.');
    }
}
