<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use App\Modules\PreMatricula\Requests\AlumnoRequest;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\HistorialAcademicoConsulta;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use Illuminate\View\View;

/** Historial académico para la coordinación y para el propio estudiante, como en master. */
final class HistorialAcademicoController extends Controller
{
    public function __construct(private readonly HistorialAcademicoConsulta $historial) {}

    public function index(CoordinacionRequest $request, ConsultaPrematricula $consulta): View
    {
        $idPeriodo = $request->integer('periodo') ?: null;

        return view('modules.pre-matricula.coordinacion.historial', [
            'buscar' => (string) $request->validated('buscar'),
            'periodos' => $consulta->periodos(),
            'idPeriodo' => $idPeriodo,
            'historial' => $this->historial->listar($request->validated('buscar'), $idPeriodo),
        ]);
    }

    public function importacion(CoordinacionRequest $request, ImportacionHistorial $importacion): View
    {
        return view('modules.pre-matricula.coordinacion.importacion', [
            'importacion' => $importacion->load('autor'),
            'cambios' => $this->historial->cambios($importacion),
        ]);
    }

    public function alumno(AlumnoRequest $request, IdentidadAcademica $identidad): View
    {
        $estudiante = $identidad->estudiante($request->user());
        if ($estudiante === null) {
            return view('modules.pre-matricula.alumno.sin-vinculo');
        }

        return view('modules.pre-matricula.alumno.historial-academico', [
            'estudiante' => $estudiante,
            'historial' => $this->historial->delEstudiante($estudiante->id_estudiante),
        ]);
    }
}
