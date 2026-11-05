<?php

namespace App\Modules\WebInstitucional\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WebInstitucional\Requests\WebInstitucionalRequest;
use App\Modules\WebInstitucional\Services\WebInstitucionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class WebInstitucionalController extends Controller
{
    public function index(): View
    {
        return view('modules.web-institucional.index');
    }

    public function create(): View
    {
        return view('modules.web-institucional.index', ['creating' => true]);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json(['surface' => 'web-institucional']);
    }

    public function store(WebInstitucionalRequest $request, WebInstitucionalService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('web-institucional.index')
            ->with('status', 'Registro procesado.');
    }
}
