<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
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

    public function finalizar(Request $request, SeguimientoEstudianteService $seguimiento, AsignacionTutoriaService $asignaciones, CausasAsignacionService $causas, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());
        $datos = $request->validate([
            'id_estudiante' => 'required|integer|min:1',
            'id_asignacion' => 'required|integer|min:1',
            'version_causa' => 'required|uuid',
            'causa' => 'required|string|max:40',
            'motivo' => 'required|string|min:10|max:1000',
            'reemplazo' => 'nullable|integer|min:1',
        ]);
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

    public function guardarCatalogo(Request $request, SeguimientoEstudianteService $seguimiento, CausasAsignacionService $causas, string $escuela, string $periodo): RedirectResponse
    {
        [$idEscuela, $idPeriodo] = $this->ids($escuela, $periodo);
        $persona = $seguimiento->personaVinculada($request->user());
        $filas = $request->input('causas');
        if (is_array($filas) && array_is_list($filas) && count($filas) <= 30) {
            $request->merge(['causas' => array_values(array_filter($filas, fn ($fila) => ! is_array($fila)
                || ($fila['codigo'] ?? null) !== null || ($fila['nombre'] ?? null) !== null))]);
        }
        $datos = $request->validate([
            'revision' => 'required|integer|min:0',
            'estado' => 'required|in:BORRADOR,VIGENTE',
            'referencia' => 'required|string|min:5|max:250',
            'motivo' => 'required|string|min:10|max:1000',
            'autoridad' => 'required_if:estado,VIGENTE|nullable|string|min:3|max:150',
            'fecha_aprobacion' => 'required_if:estado,VIGENTE|nullable|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'aprobado' => 'accepted_if:estado,VIGENTE',
            'causas' => 'required|array|list|min:1|max:30',
            'causas.*' => 'required|array:codigo,nombre,efecto,habilitada',
            'causas.*.codigo' => 'required|string|regex:/^[A-Z][A-Z0-9_]{1,39}$/D|distinct',
            'causas.*.nombre' => 'required|string|min:5|max:150',
            'causas.*.efecto' => 'required|in:FINALIZAR,REASIGNAR',
            'causas.*.habilitada' => 'required|boolean',
        ]);
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
