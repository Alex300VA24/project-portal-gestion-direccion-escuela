<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Requests\AsignacionTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Requests\CatalogoCausasTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AsignacionTutoriaService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\CausasAsignacionService;
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

    public function mostrar(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones, CausasAsignacionService $causas, string $escuela, string $periodo): View
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());

        $ambito = $asignaciones->consultar($persona, $idEscuela, $idPeriodo);
        $catalogo = $causas->consultar($idEscuela, $idPeriodo);
        $vigente = $catalogo['versiones'][$catalogo['activa']] ?? null;

        return view('modules.seguimiento-estudiante.asignaciones.estudiantes', $ambito + compact('idEscuela', 'idPeriodo', 'vigente'));
    }

    public function guardar(AsignacionTutoriaRequest $request, AsignacionTutoriaService $asignaciones, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $request->persona();
        $datos = $request->validated();
        $asignaciones->asignar($persona, $request->user()->getKey(), $idEscuela, $idPeriodo, $datos);

        return redirect()->route('seguimiento-estudiante.asignaciones.mostrar', [$idEscuela, $idPeriodo])
            ->with('status', 'Asignación de tutoría guardada.');
    }

    public function finalizar(AsignacionTutoriaRequest $request, AsignacionTutoriaService $asignaciones, CausasAsignacionService $causas, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $request->persona();
        $datos = $request->validated();
        $asignaciones->finalizar($persona, $request->user()->getKey(), $idEscuela, $idPeriodo, $datos, $causas);

        return redirect()->route('seguimiento-estudiante.asignaciones.mostrar', [$idEscuela, $idPeriodo])
            ->with('status', 'Cierre de la asignación registrado. Las solicitudes abiertas siguen pendientes.');
    }

    public function catalogo(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones, CausasAsignacionService $causas, string $escuela, string $periodo): View
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());
        $ambito = $asignaciones->ambitos($persona)->where('ambito.id_escuela', $idEscuela)->where('ambito.id_periodo', $idPeriodo)
            ->first(['escuela.nombre as escuela', 'periodo.codigo as periodo']);
        abort_if($ambito === null, 404);

        $catalogo = $causas->consultar($idEscuela, $idPeriodo);
        $versiones = collect($catalogo['versiones'])->sortByDesc('revision');
        $filas = $versiones->first()['causas'] ?? [];
        if (count($filas) < 30) {
            $filas[] = ['codigo' => '', 'nombre' => '', 'efecto' => 'FINALIZAR', 'habilitada' => true];
        }

        return view('modules.seguimiento-estudiante.asignaciones.causas', compact('ambito', 'idEscuela', 'idPeriodo', 'catalogo', 'versiones', 'filas'));
    }

    public function guardarCatalogo(CatalogoCausasTutoriaRequest $request, CausasAsignacionService $causas, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $request->persona();
        $datos = $request->validated();
        $causas->guardar($persona, $request->user()->getKey(), $idEscuela, $idPeriodo, $datos);

        return redirect()->route('seguimiento-estudiante.asignaciones.causas', [$idEscuela, $idPeriodo])
            ->with('status', 'Versión del catálogo registrada.');
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
