<?php

namespace App\Modules\Cotecu\Controllers;

use App\Modules\Cotecu\Services\CotecuService;
use App\Shared\Controllers\ModuleController;
use App\Shared\Models\Core\PlanEstudios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'planesEstudio' => $this->service->planesEstudio(),
            'puedeSubir' => auth()->user()?->can('cotecu.plan.upload') ?? false,
        ]);
    }

    /**
     * Registra un plan curricular en estado Pendiente.
     *
     * La subida del archivo está simulada: la vista envía solo los campos
     * del formulario y el servicio guarda una ruta por defecto en
     * storage/app/private/.
     */
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            [
                'id_plan' => ['required', 'integer', Rule::exists(PlanEstudios::class, 'id_plan')],
                'version' => ['required', 'integer', 'min:1'],
            ],
            [
                'id_plan.required' => 'Selecciona un plan de estudios.',
                'id_plan.integer' => 'El plan de estudios seleccionado no es válido.',
                'id_plan.exists' => 'El plan de estudios seleccionado no existe.',
                'version.required' => 'Indica la versión del documento.',
                'version.integer' => 'La versión debe ser un número entero.',
                'version.min' => 'La versión debe ser al menos 1.',
            ],
            [
                'id_plan' => 'plan de estudios',
                'version' => 'versión',
            ],
        );

        $plan = PlanEstudios::query()->findOrFail($datos['id_plan']);

        $this->service->registrarPlanCurricular(
            (int) $plan->id_plan,
            (string) $plan->codigo,
            (string) $plan->nombre,
            (int) $datos['version'],
        );

        return redirect()
            ->route('cotecu.planes.index')
            ->with('exito', 'Plan curricular registrado en estado Pendiente.');
    }
}
