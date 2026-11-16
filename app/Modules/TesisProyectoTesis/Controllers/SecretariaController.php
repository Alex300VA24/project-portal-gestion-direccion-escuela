<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Requests\RegistrarResolucionRequest;
use App\Modules\TesisProyectoTesis\Services\DesignacionService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SecretariaController extends BaseController
{
    public function resoluciones(Request $request, SeguimientoService $seguimiento, IdentidadService $identidad, DesignacionService $designacion): View
    {
        $this->identidad($request);

        $pendientes = $designacion->solicitudesPendientes();
        $actual = $pendientes->firstWhere('uuid', (string) $request->query('solicitud', '')) ?? $pendientes->first();

        return $this->vista($request, 'secretaria.resoluciones', [
            'pendientes' => $pendientes,
            'actual' => $actual,
            'ficha' => $actual ? $seguimiento->ficha($actual->proyecto) : null,
            'sugerido' => $actual?->id_docente_sugerido ? ($identidad->docentes([$actual->id_docente_sugerido])[$actual->id_docente_sugerido] ?? null) : null,
            'docentes' => $identidad->docentesActivos(),
            'recientes' => $designacion->resolucionesRecientes(),
        ], 'secretaria', 'resoluciones', ['resoluciones' => $pendientes->count()]);
    }

    public function registrarResolucion(RegistrarResolucionRequest $request, Solicitud $solicitud, DesignacionService $designacion): RedirectResponse
    {
        $resolucion = $designacion->registrarResolucion($solicitud, $this->identidad($request), $request->validated(), $request->file('archivo'));

        return redirect()->route('tesis-proyecto-tesis.secretaria.resoluciones')
            ->with('status', "Resolución N.° {$resolucion->numero} registrada. Se notificó al asesor, a los estudiantes y al Director.");
    }
}
