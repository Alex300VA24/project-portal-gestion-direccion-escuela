<?php

namespace App\Modules\Cotecu\Controllers;

use App\Modules\Cotecu\Services\CotecuService;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CotecuController extends ModuleController
{
    protected const MODULE = 'COTECU';

    protected const SCHEMA = 'cotecu';

    protected const VIEW = 'modules.cotecu.index';

    public function __construct(private readonly CotecuService $service) {}

    /**
     * Listado general de sílabos (director y secretaría), con selector
     * de semestre.
     */
    public function silabos(Request $request): View
    {
        $periodos = $this->service->periodos();
        $actual = $this->service->periodoActual();
        $seleccion = $periodos->firstWhere('codigo', $request->query('periodo')) ?? $actual;

        return view('modules.cotecu.silabos', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'periodos' => $periodos,
            'periodoSeleccionado' => $seleccion,
        ]);
    }

    /**
     * Sílabos del profesor con selector de semestre y botón de subida.
     */
    public function misSilabos(Request $request): View
    {
        $periodos = $this->service->periodos();
        $actual = $this->service->periodoActual();
        $seleccion = $periodos->firstWhere('codigo', $request->query('periodo')) ?? $actual;

        return view('modules.cotecu.mis-silabos', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'periodos' => $periodos,
            'periodoSeleccionado' => $seleccion,
            'periodoActivo' => $this->service->periodoActual(),
            'hayPeriodoActivo' => $this->service->hayPeriodoActivo(),
        ]);
    }

    /**
     * Planes curriculares: sin selector de semestre (no tienen
     * id_periodo) y con el filtro de visibilidad aplicado por el
     * servicio según los permisos de la cuenta.
     */
    public function planes(): View
    {
        return view('modules.cotecu.planes', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'planes' => $this->service->planes(),
            'puedeSubir' => auth()->user()?->can('cotecu.plan.upload') ?? false,
        ]);
    }
}
