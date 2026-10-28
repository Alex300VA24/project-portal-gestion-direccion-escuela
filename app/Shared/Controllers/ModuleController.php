<?php

namespace App\Shared\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

abstract class ModuleController extends Controller
{
    protected const MODULE = '';

    protected const SCHEMA = '';

    protected const VIEW = '';

    public function index(): View
    {
        return view(static::VIEW, $this->viewData());
    }

    public function create(): View
    {
        return view(static::VIEW, [...$this->viewData(), 'creating' => true]);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json($this->viewData());
    }

    /** @return array{module: string, schema: string} */
    private function viewData(): array
    {
        return ['module' => static::MODULE, 'schema' => static::SCHEMA];
    }
}
