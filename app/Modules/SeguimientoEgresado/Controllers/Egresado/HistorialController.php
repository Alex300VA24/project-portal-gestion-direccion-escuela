<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Egresado;

use App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class HistorialController extends EgresadoController
{
    public function __construct(private readonly HistorialService $historial) {}

    public function index(Request $request): View
    {
        $identidad = $this->identidad($request);
        abort_unless((new ExpedientePolicy)->verSeguimiento($identidad, $identidad->egresadoId), 403);

        return view('modules.seguimiento-egresado.egresado.historial', [
            'campanias' => $this->historial->campanias($identidad->egresadoId),
            'contactos' => $this->historial->contactos($identidad->egresadoId),
            'formaciones' => $this->historial->formaciones($identidad->egresadoId),
        ]);
    }

    public function detalle(Request $request, int $medicion): View
    {
        $identidad = $this->identidad($request);
        abort_unless((new ExpedientePolicy)->verSeguimiento($identidad, $identidad->egresadoId), 403);

        // El servicio filtra por el egresado de la sesión: una medición ajena responde 404, igual que una inexistente.
        $detalle = $this->historial->medicion($identidad->egresadoId, $medicion);
        abort_if($detalle === null, 404);

        return view('modules.seguimiento-egresado.egresado.medicion', ['m' => $detalle]);
    }
}
