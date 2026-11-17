<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class InvestigacionFormativaController extends Controller
{
    /**
     * @var array<string, array{label: string, route: string, description: string}>
     */
    private const WORKSPACES = [
        'director_escuela' => [
            'label' => 'Dirección de escuela',
            'route' => 'investigacion-formativa.director.index',
            'description' => 'Habilita cursos, revisa grupos y consulta resultados.',
        ],
        'profesor' => [
            'label' => 'Docencia',
            'route' => 'investigacion-formativa.docente.cursos.index',
            'description' => 'Configura competencias, rúbricas y cronograma de tus secciones.',
        ],
        'alumno' => [
            'label' => 'Estudiante',
            'route' => 'investigacion-formativa.alumno.cursos.index',
            'description' => 'Forma tu grupo, presenta avances y responde la encuesta.',
        ],
    ];

    public function index(Request $request, AccesoInvestigacionService $acceso): View|RedirectResponse
    {
        try {
            $acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }

        $workspaces = [];

        foreach (self::WORKSPACES as $role => $workspace) {
            if ($request->user()->hasRole($role)) {
                $workspaces[] = $workspace;
            }
        }

        if ($workspaces === []) {
            abort(403, 'No tiene un rol habilitado en Investigación Formativa.');
        }

        if (count($workspaces) === 1) {
            return redirect()->route($workspaces[0]['route']);
        }

        return view('modules.investigacion-formativa.index', [
            'module' => 'Investigación Formativa',
            'workspaces' => $workspaces,
        ]);
    }

    public function apiIndex(): JsonResponse
    {
        return response()->json(['module' => 'Investigación Formativa']);
    }
}
