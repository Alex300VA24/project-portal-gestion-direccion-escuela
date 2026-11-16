<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\VerificarRubricaRequest;
use App\Modules\TesisProyectoTesis\Services\JuradoService;
use App\Modules\TesisProyectoTesis\Services\RevisionService;
use App\Modules\TesisProyectoTesis\Services\RubricaService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class JuradoController extends BaseController
{
    public function __construct(private readonly JuradoService $jurado) {}

    public function proyectos(Request $request): View
    {
        $id = $this->identidad($request);
        $filas = $this->jurado->bandeja((int) $id->idDocente);

        return $this->vista($request, 'jurado.proyectos', ['filas' => $filas], 'jurado', 'proyectos',
            ['proyectos' => $filas->whereNotNull('modo')->count()]);
    }

    public function verificar(Request $request, Proyecto $proyecto, RubricaService $rubrica, SeguimientoService $seguimiento, RevisionService $revisiones): View
    {
        $id = $this->identidad($request);
        $modo = $this->jurado->modo($proyecto, (int) $id->idDocente);
        $entrega = $modo === 'Intermedia' ? $this->jurado->ultimaEntrega($proyecto) : $rubrica->entregaObjetivo($proyecto);

        return $this->vista($request, 'jurado.verificar', [
            ...$seguimiento->ficha($proyecto),
            'modo' => $modo,
            'entrega' => $entrega,
            'criterios' => $rubrica->criterios($proyecto->etapa),
            'rubrica' => $rubrica->vigente(),
            'miembros' => $this->jurado->avanceMiembros($proyecto),
            'idDocente' => (int) $id->idDocente,
            'ultima' => $rubrica->ultimaVerificacion($proyecto, (int) $id->idDocente),
            'abiertas' => $revisiones->observacionesAbiertasDe($proyecto, (int) $id->idDocente),
        ], 'jurado', 'proyectos');
    }

    public function guardar(VerificarRubricaRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $revision = $this->jurado->verificar($proyecto, $this->identidad($request), $request->validated());

        $mensaje = match (true) {
            $revision->referencial => 'Revisión intermedia registrada. Los estudiantes verán tus observaciones como referencia.',
            $revision->conforme => 'Conformidad registrada.',
            default => 'Verificación guardada. Tus observaciones se enviaron a los estudiantes.',
        };

        return redirect()->route('tesis-proyecto-tesis.jurado.verificar', $proyecto)->with('status', $mensaje);
    }

    public function levantar(Request $request, Proyecto $proyecto, Observacion $observacion, RevisionService $revisiones): RedirectResponse
    {
        $revisiones->levantar($observacion, $this->identidad($request), $proyecto);

        return back()->with('status', 'Observación levantada.');
    }
}
