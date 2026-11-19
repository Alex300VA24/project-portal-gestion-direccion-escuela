<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\BuscarCoautorRequest;
use App\Modules\TesisProyectoTesis\Requests\RegistrarProyectoRequest;
use App\Modules\TesisProyectoTesis\Requests\ResponderObservacionRequest;
use App\Modules\TesisProyectoTesis\Requests\SubirEntregaRequest;
use App\Modules\TesisProyectoTesis\Services\AvanceService;
use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\ProyectoService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Support\Estados;
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

    /** Accesos del menú: llevan al proyecto vigente del estudiante. */
    public function irAvances(Request $request): RedirectResponse
    {
        return $this->irA($request, 'tesis-proyecto-tesis.alumno.proyecto.avances');
    }

    public function irObservaciones(Request $request): RedirectResponse
    {
        return $this->irA($request, 'tesis-proyecto-tesis.alumno.proyecto.observaciones');
    }

    private function irA(Request $request, string $ruta): RedirectResponse
    {
        $proyecto = $this->proyectos->proyectoDeEstudiante((int) $this->identidad($request)->idEstudiante);

        return $proyecto
            ? redirect()->route($ruta, $proyecto)
            : redirect()->route('tesis-proyecto-tesis.alumno.panel')->with('error', 'Primero registra tu proyecto de tesis.');
    }

    public function avances(Request $request, Proyecto $proyecto, AvanceService $avances): View
    {
        $pendientes = $avances->observaciones($proyecto)->where('estado', 'Pendiente');

        return $this->vista($request, 'alumno.avances', [
            'proyecto' => $proyecto,
            'entregas' => $this->seguimiento->entregas($proyecto),
            'semana' => $this->seguimiento->semanaActual($proyecto),
            'pendientes' => $pendientes,
            'recibe' => in_array($proyecto->estado, Estados::PERMITEN_ENTREGA, true),
        ], 'alumno', 'avances', ['observaciones' => $pendientes->count()]);
    }

    public function subirAvance(SubirEntregaRequest $request, Proyecto $proyecto, AvanceService $avances): RedirectResponse
    {
        $entrega = $avances->subir($proyecto, $this->identidad($request), $request->validated(), $request->file('archivo'));

        return redirect()->route('tesis-proyecto-tesis.alumno.proyecto.avances', $proyecto)
            ->with('status', "Entrega v{$entrega->version} enviada. Tu revisor recibió un aviso.");
    }

    public function observaciones(Request $request, Proyecto $proyecto, AvanceService $avances): View
    {
        $lista = $avances->observaciones($proyecto);

        return $this->vista($request, 'alumno.observaciones', [
            'proyecto' => $proyecto,
            'grupos' => [
                'Pendiente' => $lista->where('estado', 'Pendiente')->values(),
                'Respondida' => $lista->where('estado', 'Respondida')->values(),
                'Levantada' => $lista->where('estado', 'Levantada')->values(),
            ],
            'entregas' => $avances->entregasParaCorreccion($proyecto),
        ], 'alumno', 'observaciones', ['observaciones' => $lista->where('estado', 'Pendiente')->count()]);
    }

    public function responder(ResponderObservacionRequest $request, Observacion $observacion, AvanceService $avances): RedirectResponse
    {
        $o = $avances->responder($observacion, $this->identidad($request), $request->validated('respuesta'), $request->validated('entrega'), $request->file('archivo'));
        $proyecto = $avances->proyectoDeObservacion($observacion);
        $version = $o->entregaCorreccion?->version;
        $destino = redirect()->route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $proyecto);

        if ($version === null) {
            return $destino->with('status', 'Respuesta enviada. El revisor confirmará si la observación queda levantada.')
                ->with('aviso', 'No adjuntaste el documento corregido ni elegiste una versión. Si ya corregiste el documento, súbelo o elige la versión en «Corregida en» para que el revisor pueda verlo.');
        }

        return $destino->with('status', $request->hasFile('archivo')
            ? "Respuesta enviada con el documento corregido (registrado como v{$version}). El revisor confirmará si la observación queda levantada."
            : "Respuesta enviada: corregida en v{$version}. El revisor confirmará si la observación queda levantada.");
    }

    /**
     * Búsqueda del coautor por código: devuelve solo nombre y si está disponible.
     */
    public function coautor(BuscarCoautorRequest $request, IdentidadService $identidad): JsonResponse
    {
        $datos = $request->validated();
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
