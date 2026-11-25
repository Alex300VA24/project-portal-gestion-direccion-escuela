<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Services\ConsultaPrematricula;
use App\Modules\PreMatricula\Services\DemandaAcademica;
use App\Modules\PreMatricula\Services\PrematriculaException;
use App\Modules\PreMatricula\Services\VentanaPrematricula;
use Illuminate\View\View;

final class CoordinacionController extends Controller
{
    public function __invoke(CoordinacionRequest $request, VentanaPrematricula $ventanas, ConsultaPrematricula $consulta, DemandaAcademica $demanda): View
    {
        try {
            $habilitado = $ventanas->periodoHabilitado();
            $aviso = null;
        } catch (PrematriculaException $error) {
            $habilitado = null;
            $aviso = $error->getMessage();
        }

        $periodo = $consulta->periodoPorDefecto($request->integer('periodo') ?: null, $habilitado);

        return view('modules.pre-matricula.coordinacion.inicio', [
            'periodo' => $periodo,
            'periodos' => $consulta->periodos(),
            'aviso' => $aviso,
            'ventana' => $periodo === null ? null : $ventanas->activa($periodo->id_periodo),
            'ventanas' => $periodo === null ? collect() : $ventanas->delPeriodo($periodo->id_periodo),
            'conteos' => $periodo === null ? [] : $consulta->conteos($periodo->id_periodo),
            'resumen' => $periodo === null ? null : $demanda->resumen($periodo->id_periodo),
            'top' => $periodo === null ? collect() : $demanda->porCurso($periodo->id_periodo)->take(5),
        ]);
    }
}
