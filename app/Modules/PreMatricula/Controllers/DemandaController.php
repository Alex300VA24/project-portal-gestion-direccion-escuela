<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\DemandaAcademica;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class DemandaController extends Controller
{
    public function __construct(
        private readonly DemandaAcademica $demanda,
        private readonly ConsultaPrematricula $consulta,
    ) {}

    public function index(CoordinacionRequest $request, VentanaPrematricula $ventanas): View
    {
        $periodo = $this->consulta->periodoPorDefecto($request->integer('periodo') ?: null, rescue(fn () => $ventanas->periodoHabilitado(), null, false));
        $ciclo = $request->integer('ciclo') ?: null;

        return view('modules.pre-matricula.coordinacion.demanda', [
            'periodos' => $this->consulta->periodos(),
            'periodo' => $periodo,
            'ciclo' => $ciclo,
            'buscar' => $request->validated('buscar'),
            'resumen' => $periodo === null ? null : $this->demanda->resumen($periodo->id_periodo),
            'cursos' => $periodo === null ? collect() : $this->demanda->porCurso($periodo->id_periodo, $ciclo, $request->validated('buscar')),
        ]);
    }

    public function interesados(CoordinacionRequest $request, int $periodo, int $cursoPlan): View
    {
        $registro = PeriodoAcademico::query()->findOrFail($periodo);
        $curso = $this->demanda->porCurso($periodo)->firstWhere('id_curso_plan', $cursoPlan);
        abort_if($curso === null, 404);

        return view('modules.pre-matricula.coordinacion.interesados', [
            'periodo' => $registro,
            'curso' => $curso,
            'estudiantes' => $this->demanda->interesados($periodo, $cursoPlan),
        ]);
    }

    public function exportar(CoordinacionRequest $request, int $periodo): Response
    {
        $registro = PeriodoAcademico::query()->findOrFail($periodo);

        return response($this->demanda->csv($periodo), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="demanda-'.$registro->codigo.'.csv"',
        ]);
    }
}
