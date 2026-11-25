<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AvisoTutoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AvisoTutoriaController extends Controller
{
    public function index(Request $request, SeguimientoEstudianteService $seguimiento, AvisoTutoriaService $avisos): View
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $bandeja = $avisos->bandeja($request->user()->getKey(), $persona)->orderByDesc('id_aviso')
            ->paginate(20, ['id_aviso', 'mensaje', 'creado_en', 'leido_en']);

        return view('modules.seguimiento-estudiante.avisos-tutoria', compact('bandeja'));
    }

    public function leer(Request $request, SeguimientoEstudianteService $seguimiento, AvisoTutoriaService $avisos, string $aviso): RedirectResponse
    {
        $id = filter_var($aviso, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $persona = $seguimiento->personaVinculada($request->user());
        $avisos->leer($request->user()->getKey(), $persona, $id);

        return redirect()->route('seguimiento-estudiante.avisos.index');
    }
}
