<?php

namespace App\Modules\Cotecu\Controllers;

use App\Modules\Cotecu\Models\Revision;
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
     * El selector del formulario carga solo sus secciones del periodo
     * activo: nadie puede subir un sílabo de un curso ajeno.
     */
    public function misSilabos(Request $request): View
    {
        $periodos = $this->service->periodos();
        $actual = $this->service->periodoActual();
        $seleccion = $periodos->firstWhere('codigo', $request->query('periodo')) ?? $actual;
        $periodoActivo = $this->service->periodoActivo();

        return view('modules.cotecu.mis-silabos', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'periodos' => $periodos,
            'periodoSeleccionado' => $seleccion,
            'periodoActivo' => $periodoActivo,
            'hayPeriodoActivo' => $periodoActivo !== null,
            'silabos' => $this->service->misSilabos($seleccion?->id_periodo),
            'secciones' => $this->service->seccionesDelProfesor($periodoActivo?->id_periodo),
        ]);
    }

    /**
     * Registra un sílabo del profesor en estado Pendiente.
     *
     * El curso debe ser una de sus secciones en el periodo activo (la
     * misma lista que alimenta el selector) y la subida del archivo
     * está simulada: solo se guarda una ruta por defecto en
     * storage/app/private/.
     */
    public function storeSilabo(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            [
                'id_curso_plan' => ['required', 'integer'],
                'version' => ['required', 'integer', 'min:1'],
            ],
            [
                'id_curso_plan.required' => 'Selecciona el curso del sílabo.',
                'id_curso_plan.integer' => 'El curso seleccionado no es válido.',
                'version.required' => 'Indica la versión del documento.',
                'version.integer' => 'La versión debe ser un número entero.',
                'version.min' => 'La versión debe ser al menos 1.',
            ],
            [
                'id_curso_plan' => 'curso',
                'version' => 'versión',
            ],
        );

        $idProfesor = $this->service->idUsuarioActual();
        $periodoActivo = $this->service->periodoActivo();

        $esSuSeccion = $periodoActivo !== null
            && $idProfesor !== null
            && $this->service
                ->seccionesDelProfesor((int) $periodoActivo->id_periodo)
                ->contains('id_curso_plan', (int) $datos['id_curso_plan']);

        if (! $esSuSeccion) {
            return redirect()
                ->route('cotecu.mis-silabos')
                ->withErrors(['id_curso_plan' => 'Selecciona uno de tus cursos asignados en el periodo activo.']);
        }

        $this->service->registrarSilabo(
            (int) $datos['id_curso_plan'],
            (int) $periodoActivo->id_periodo,
            $idProfesor,
            (int) $datos['version'],
        );

        return redirect()
            ->route('cotecu.mis-silabos')
            ->with('exito', 'Sílabo registrado en estado Pendiente.');
    }

    /**
     * Cola de revisión de sílabos para el director de escuela: al
     * abrirla se crean (si hace falta) los registros de revisión de los
     * sílabos Pendiente y se listan con su semestre, profesor y estado.
     */
    public function revisionesSilabos(): View
    {
        return view('modules.cotecu.revisar-silabos', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'periodoActual' => $this->service->periodoActual(),
            'cola' => $this->service->colaRevisionSilabos(),
        ]);
    }

    /**
     * Pantalla de marcado de una revisión de sílabo: el sílabo a la
     * izquierda (lector de PDF por integrar) y la lista de cotejo a la
     * derecha, con checkbox y observación por ítem.
     *
     * Una revisión ya cerrada se reabre en solo lectura: mismos ítems
     * con su marcado guardado y sin botón de guardado.
     */
    public function revisarSilabo(Revision $revision): View
    {
        $detalle = $this->service->detalleRevision((int) $revision->id);

        return view('modules.cotecu.revisar-silabo', [
            'module' => static::MODULE,
            'schema' => static::SCHEMA,
            'revision' => $detalle ?? $revision,
            'items' => $this->service->itemsDeRevision($revision),
            'resultados' => $this->service->resultadosDeRevision((int) $revision->id),
            'cerrada' => $revision->estado_final !== 'Pendiente',
        ]);
    }

    /**
     * Guarda el marcado y cierra la revisión: todos los ítems cumplen →
     * Validado; al menos uno sin marcar → Observado. El estado final se
     * aplica también al sílabo, que así sale de la cola.
     *
     * Un ítem sin marcar exige su observación; un ítem marcado nunca
     * la guarda (se limpia en el servicio).
     */
    public function storeRevision(Request $request, Revision $revision): RedirectResponse
    {
        if ($revision->estado_final !== 'Pendiente') {
            return redirect()
                ->route('cotecu.silabos.revisiones')
                ->withErrors(['general' => 'Esta revisión ya fue cerrada.']);
        }

        $items = $this->service->itemsDeRevision($revision);

        if ($items->isEmpty()) {
            return redirect()
                ->route('cotecu.silabos.revisiones')
                ->withErrors(['general' => 'Esta revisión no tiene ítems en su lista de cotejo.']);
        }

        $reglas = [];
        $mensajes = [];

        foreach ($items as $item) {
            $incumple = ! $request->boolean("items.{$item->id}.cumple");

            $reglas["items.{$item->id}.cumple"] = ['nullable', 'boolean'];
            $reglas["items.{$item->id}.observacion"] = [
                'nullable',
                'string',
                'max:1500',
                Rule::requiredIf($incumple),
            ];
            $mensajes["items.{$item->id}.observacion.required"] = 'Indica la observación del ítem que incumple.';
            $mensajes["items.{$item->id}.observacion.max"] = 'La observación no puede superar los 1500 caracteres.';
        }

        $request->validate($reglas, $mensajes);

        $marcas = [];

        foreach ($items as $item) {
            $observacion = $request->input("items.{$item->id}.observacion");

            $marcas[(int) $item->id] = [
                'cumple' => $request->boolean("items.{$item->id}.cumple"),
                'observacion' => is_string($observacion) ? trim($observacion) : null,
            ];
        }

        $estadoFinal = $this->service->cerrarRevision($revision, $items, $marcas);

        return redirect()
            ->route('cotecu.silabos.revisiones')
            ->with('exito', $estadoFinal === 'Validado'
                ? 'Revisión cerrada: el sílabo fue Validado.'
                : 'Revisión cerrada: el sílabo fue Observado.');
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
