<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AsignacionTutoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AsignacionTutoriaController extends Controller
{
    public function index(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones): View
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $ambitos = $asignaciones->ambitos($persona)->orderBy('escuela.nombre')->orderBy('periodo.codigo')
            ->get(['ambito.id_escuela', 'ambito.id_periodo', 'escuela.nombre as escuela', 'periodo.codigo as periodo']);

        return view('modules.seguimiento-estudiante.asignaciones.ambitos', compact('ambitos'));
    }

    public function mostrar(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones, string $escuela, string $periodo): View
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());

        return view(
            'modules.seguimiento-estudiante.asignaciones.estudiantes',
            $asignaciones->consultar($persona, $idEscuela, $idPeriodo) + compact('idEscuela', 'idPeriodo'),
        );
    }

    public function guardar(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());
        $datos = $request->validate([
            'id_estudiante' => ['required', 'integer', 'min:1'],
            'id_docente' => ['required', 'integer', 'min:1'],
            'id_asignacion' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $asignaciones->asignar($persona, $request->user()->getKey(), $idEscuela, $idPeriodo, $datos);

        return redirect()->route('seguimiento-estudiante.asignaciones.mostrar', [$idEscuela, $idPeriodo])
            ->with('status', 'Asignación de tutoría guardada.');
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
