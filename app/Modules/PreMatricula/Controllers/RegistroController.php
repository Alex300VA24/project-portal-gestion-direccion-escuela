<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Models\SolicitudPrematricula;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use Illuminate\View\View;

/** Consulta de prematrículas y sus constancias. No hay aprobación ni rechazo. */
final class RegistroController extends Controller
{
    public function __construct(private readonly ConsultaPrematricula $consulta) {}

    public function index(CoordinacionRequest $request, VentanaPrematricula $ventanas): View
    {
        $periodo = $this->consulta->periodoPorDefecto($request->integer('periodo') ?: null, rescue(fn () => $ventanas->periodoHabilitado(), null, false));

        return view('modules.pre-matricula.coordinacion.registros', [
            'periodos' => $this->consulta->periodos(),
            'periodo' => $periodo,
            'conteos' => $periodo === null ? [] : $this->consulta->conteos($periodo->id_periodo),
            'registros' => $periodo === null ? null : $this->consulta->registros($periodo->id_periodo, $request->validated('estado'), $request->validated('buscar')),
        ]);
    }

    public function show(CoordinacionRequest $request, int $solicitud): View
    {
        $registro = SolicitudPrematricula::query()->with(['estudiante.persona', 'periodo', 'validacion', 'revisionVigente'])->findOrFail($solicitud);

        return view('modules.pre-matricula.coordinacion.registro', [
            'solicitud' => $registro,
            'revisiones' => $this->consulta->revisiones($registro),
        ]);
    }
}
