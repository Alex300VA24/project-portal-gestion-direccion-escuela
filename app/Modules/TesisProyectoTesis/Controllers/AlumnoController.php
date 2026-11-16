<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Requests\RegistrarProyectoRequest;
use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\ProyectoService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AlumnoController extends BaseController
{
    public function __construct(
        private readonly ProyectoService $proyectos,
        private readonly SeguimientoService $seguimiento,
    ) {}

    public function panel(Request $request): View
    {
        $id = $this->identidad($request);
        $proyecto = $this->proyectos->proyectoDeEstudiante((int) $id->idEstudiante);

        if ($proyecto === null) {
            return $this->vista($request, 'alumno.panel', ['proyecto' => null], 'alumno', 'inicio');
        }

        $pendientes = $this->seguimiento->observacionesPendientes($proyecto);

        return $this->vista($request, 'alumno.panel', [
            ...$this->seguimiento->ficha($proyecto),
            'pasos' => $this->seguimiento->pasos($proyecto),
            'historial' => $this->seguimiento->historial($proyecto),
            'entregas' => $this->seguimiento->entregas($proyecto, limite: 5),
            'pendientes' => $pendientes,
            'semana' => $this->seguimiento->semanaActual($proyecto),
            'sustentaciones' => $proyecto->sustentaciones()->get(['numero_intento', 'fecha_hora', 'id_ambiente', 'estado', 'resultado', 'modo_resultado']),
        ], 'alumno', 'inicio', ['observaciones' => $pendientes]);
    }

    public function proyecto(Request $request): View|RedirectResponse
    {
        $id = $this->identidad($request);
        $proyecto = $this->proyectos->proyectoDeEstudiante((int) $id->idEstudiante);

        if ($proyecto === null) {
            return redirect()->route('tesis-proyecto-tesis.alumno.proyecto.registrar');
        }

        $proyecto->load(['colaboradores', 'actividades', 'recursos', 'partidas']);

        return $this->vista($request, 'alumno.proyecto', [
            ...$this->seguimiento->ficha($proyecto),
        ], 'alumno', 'proyecto', ['observaciones' => $this->seguimiento->observacionesPendientes($proyecto)]);
    }

    public function registrar(Request $request, CatalogoService $catalogo): View|RedirectResponse
    {
        $id = $this->identidad($request);

        if ($this->proyectos->tieneProyectoActivo((int) $id->idEstudiante)) {
            return redirect()->route('tesis-proyecto-tesis.alumno.panel')->with('error', 'Ya tienes un proyecto de tesis activo.');
        }

        return $this->vista($request, 'alumno.registrar', [
            'lineas' => $catalogo->lineas(),
            'yo' => app(IdentidadService::class)->estudiantes([(int) $id->idEstudiante])[(int) $id->idEstudiante] ?? null,
            'parametros' => [
                'enfoques' => Parametros::ENFOQUES, 'tipos' => Parametros::TIPOS_INVESTIGACION, 'disenos' => Parametros::DISENOS,
                'financiamientos' => Parametros::FINANCIAMIENTOS, 'recursos' => Parametros::TIPOS_RECURSO,
            ],
        ], 'alumno', 'proyecto');
    }

    public function guardar(RegistrarProyectoRequest $request): RedirectResponse
    {
        $proyecto = $this->proyectos->registrar($this->identidad($request), $request->validated(), $request->file('documento'));

        return redirect()->route('tesis-proyecto-tesis.alumno.panel')
            ->with('status', "Proyecto {$proyecto->codigo} registrado. El Director solicitará tu asesor al Decanato.");
    }

    /**
     * Búsqueda del coautor por código: devuelve solo nombre y si está disponible.
     */
    public function coautor(Request $request, IdentidadService $identidad): JsonResponse
    {
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/']]);
        $yo = $this->identidad($request);
        $e = $identidad->estudiantePorCodigo($datos['codigo']);

        if ($e === null || $e['id'] === $yo->idEstudiante) {
            return response()->json(['encontrado' => false, 'mensaje' => 'No se encontró un estudiante activo con ese código.'], 404);
        }

        $ocupado = $this->proyectos->tieneProyectoActivo($e['id']);

        return response()->json([
            'encontrado' => true,
            'nombre' => $e['nombre'],
            'codigo' => $e['codigo'],
            'disponible' => ! $ocupado,
            'mensaje' => $ocupado ? 'Este estudiante ya tiene un proyecto activo.' : 'Disponible para integrar el proyecto.',
        ]);
    }
}
