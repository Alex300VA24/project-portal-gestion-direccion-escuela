<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Requests\PlanTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Requests\RevisionPlanTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AsignacionTutoriaService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\PlanTutoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PlanTutoriaController extends Controller
{
    public function index(Request $request, SeguimientoEstudianteService $seguimiento, PlanTutoriaService $planes): View
    {
        return view('modules.seguimiento-estudiante.planes-tutoria', [
            'ambitos' => $planes->ambitos($seguimiento->personaVinculada($request->user())),
        ]);
    }

    public function mostrar(Request $request, SeguimientoEstudianteService $seguimiento, PlanTutoriaService $planes, string $escuela, string $periodo): View
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());

        return view('modules.seguimiento-estudiante.plan-tutoria', $planes->consultar($persona, $idEscuela, $idPeriodo) + [
            'campos' => PlanTutoriaService::FIELDS,
            'requestId' => (string) Str::uuid(),
            'idEscuela' => $idEscuela,
            'idPeriodo' => $idPeriodo,
        ]);
    }

    public function guardar(PlanTutoriaRequest $request, PlanTutoriaService $planes, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $planes->guardar($request->persona(), $request->user()->getKey(), $idEscuela, $idPeriodo, $request->plan());

        return redirect()->route('seguimiento-estudiante.planes.mostrar', [$idEscuela, $idPeriodo])
            ->with('status', 'Aporte tutorial guardado.');
    }

    public function coordinacion(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones): View
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $ambitos = $asignaciones->ambitos($persona)->orderBy('escuela.nombre')->orderBy('periodo.codigo')
            ->get(['ambito.id_escuela', 'ambito.id_periodo', 'escuela.nombre as escuela', 'periodo.codigo as periodo']);

        return view('modules.seguimiento-estudiante.planes-coordinacion', compact('ambitos'));
    }

    public function presentados(Request $request, SeguimientoEstudianteService $seguimiento, PlanTutoriaService $planes, string $escuela, string $periodo): View
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());

        return view('modules.seguimiento-estudiante.planes-coordinacion', $planes->presentados($persona, $idEscuela, $idPeriodo)
            + compact('idEscuela', 'idPeriodo') + ['campos' => PlanTutoriaService::FIELDS]);
    }

    public function revisar(RevisionPlanTutoriaRequest $request, PlanTutoriaService $planes, string $escuela, string $periodo, string $autor): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $request->persona();
        $idAutor = filter_var($autor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($idAutor === false, 404);
        $datos = $request->validated();
        $planes->revisar($persona, $request->user()->getKey(), $idEscuela, $idPeriodo, $idAutor, $datos);

        return redirect()->route('seguimiento-estudiante.planes.presentados', [$idEscuela, $idPeriodo])
            ->with('status', 'Revisión del aporte registrada.');
    }

    private function ids(string $escuela, string $periodo): array
    {
        $opciones = ['options' => ['min_range' => 1]];
        $idEscuela = filter_var($escuela, FILTER_VALIDATE_INT, $opciones);
        $idPeriodo = filter_var($periodo, FILTER_VALIDATE_INT, $opciones);
        abort_if($idEscuela === false || $idPeriodo === false, 404);

        return [$idEscuela, $idPeriodo];
    }
}
