<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Requests\GuardarPeriodoRequest;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use App\Modules\PreMatricula\Services\PeriodosAcademicos;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Períodos académicos: crear, cambiar de estado, editar y eliminar, como en master. */
final class PeriodoController extends Controller
{
    use RespondeAcciones;

    public function __construct(private readonly PeriodosAcademicos $periodos) {}

    public function index(CoordinacionRequest $request): View
    {
        return view('modules.pre-matricula.coordinacion.periodos', [
            'periodos' => $this->periodos->listar(),
            'estados' => PeriodosAcademicos::ESTADOS,
        ]);
    }

    public function store(GuardarPeriodoRequest $request, IdentidadAcademica $identidad): RedirectResponse
    {
        $periodo = $this->periodos->guardar($request->validated(), $identidad->idUsuario($request->user()));

        return redirect()->route('pre-matricula.coordinacion.periodos')
            ->with('status', $periodo->estado === PeriodosAcademicos::PREMATRICULA
                ? "Período {$periodo->codigo} creado y prematrícula abierta."
                : "Período {$periodo->codigo} creado.");
    }

    public function edit(CoordinacionRequest $request, PeriodoAcademico $periodo): View
    {
        return view('modules.pre-matricula.coordinacion.periodo', [
            'periodo' => $periodo,
            'estados' => PeriodosAcademicos::ESTADOS,
            'referenciado' => $this->periodos->referenciado($periodo),
        ]);
    }

    public function update(GuardarPeriodoRequest $request, PeriodoAcademico $periodo, IdentidadAcademica $identidad): RedirectResponse
    {
        $this->periodos->guardar($request->validated(), $identidad->idUsuario($request->user()), $periodo);

        return redirect()->route('pre-matricula.coordinacion.periodos')->with('status', 'Período actualizado.');
    }

    public function destroy(CoordinacionRequest $request, PeriodoAcademico $periodo): RedirectResponse
    {
        return $this->accion(fn () => $this->periodos->eliminar($periodo), 'Período eliminado.', 'pre-matricula.coordinacion.periodos');
    }
}
