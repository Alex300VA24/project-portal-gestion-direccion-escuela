<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Requests\CrearVentanaRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class VentanaController extends Controller
{
    public function __construct(private readonly VentanaPrematricula $ventanas) {}

    public function index(CoordinacionRequest $request, ConsultaPrematricula $consulta): View
    {
        $periodos = $consulta->periodos();
        $periodo = $periodos->firstWhere('id_periodo', $request->integer('periodo')) ?? $periodos->first();

        return view('modules.pre-matricula.coordinacion.ventanas', [
            'periodos' => $periodos,
            'periodo' => $periodo,
            'ventanas' => $periodo === null ? collect() : $this->ventanas->delPeriodo($periodo->id_periodo),
            'ahora' => now(),
        ]);
    }

    public function store(CrearVentanaRequest $request, IdentidadAcademica $identidad): RedirectResponse
    {
        $datos = $request->validated();
        $this->ventanas->crear(PeriodoAcademico::query()->findOrFail($datos['id_periodo']), [
            'tipo' => $datos['tipo'],
            'fecha_inicio' => CarbonImmutable::parse($datos['fecha_inicio'])->format('Y-m-d H:i'),
            'fecha_fin' => CarbonImmutable::parse($datos['fecha_fin'])->format('Y-m-d H:i'),
            'motivo' => $datos['motivo'],
        ], $identidad->idUsuario($request->user()));

        return redirect()->route('pre-matricula.coordinacion.ventanas', ['periodo' => $datos['id_periodo']])
            ->with('status', 'Ventana creada. Las ventanas no se editan ni se eliminan: quedan como historial.');
    }
}
