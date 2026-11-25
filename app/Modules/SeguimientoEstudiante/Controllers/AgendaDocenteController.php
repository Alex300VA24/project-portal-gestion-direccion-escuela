<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Services\Nivelacion\AgendaDocenteService;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AgendaDocenteController extends Controller
{
    public function index(Request $request, SeguimientoEstudianteService $seguimiento, AgendaDocenteService $agenda): View
    {
        $sesiones = $agenda->sesiones($seguimiento->personaVinculada($request->user()));

        return view('modules.seguimiento-estudiante.nivelacion.agenda-docente', compact('sesiones'));
    }
}
