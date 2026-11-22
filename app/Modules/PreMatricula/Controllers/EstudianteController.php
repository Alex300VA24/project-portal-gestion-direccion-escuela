<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use App\Shared\Models\Core\Estudiante;
use Illuminate\View\View;

/** Situación académica de un estudiante para orientar su prematrícula. */
final class EstudianteController extends Controller
{
    public function __construct(private readonly ConsultaPrematricula $consulta) {}

    public function index(CoordinacionRequest $request): View
    {
        $buscar = trim((string) $request->validated('buscar'));

        return view('modules.pre-matricula.coordinacion.estudiantes', [
            'buscar' => $buscar,
            'estudiantes' => $buscar === '' ? collect() : $this->consulta->buscarEstudiantes($buscar),
        ]);
    }

    public function show(CoordinacionRequest $request, string $codigo, VentanaPrematricula $ventanas): View
    {
        $estudiante = Estudiante::query()->with('persona')->where('codigo_universitario', $codigo)->firstOrFail();
        $periodo = $this->consulta->periodoPorDefecto($request->integer('periodo') ?: null, rescue(fn () => $ventanas->periodoHabilitado(), null, false));

        return view('modules.pre-matricula.coordinacion.estudiante', [
            'estudiante' => $estudiante,
            'periodo' => $periodo,
            'periodos' => $this->consulta->periodos(),
            ...$this->consulta->situacion($estudiante, $periodo),
        ]);
    }
}
