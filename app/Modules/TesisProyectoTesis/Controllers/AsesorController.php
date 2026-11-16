<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\RevisarAvanceRequest;
use App\Modules\TesisProyectoTesis\Services\AsesorService;
use App\Modules\TesisProyectoTesis\Services\AvanceService;
use App\Modules\TesisProyectoTesis\Services\RevisionService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AsesorController extends BaseController
{
    public function __construct(
        private readonly SeguimientoService $seguimiento,
        private readonly RevisionService $revisiones,
    ) {}

    public function proyectos(Request $request, AsesorService $asesor): View
    {
        $id = $this->identidad($request);
        $filtro = $request->validate(['etapa' => ['nullable', Rule::in(['Proyecto', 'Tesis'])]]);
        $datos = $asesor->bandeja((int) $id->idDocente, $filtro['etapa'] ?? null);

        return $this->vista($request, 'asesor.proyectos', [...$datos, 'etapa' => $filtro['etapa'] ?? null], 'asesor', 'proyectos', ['proyectos' => $datos['cifras']['porRevisar']]);
    }

    public function proyecto(Request $request, Proyecto $proyecto, AvanceService $avances): View
    {
        $this->identidad($request);

        return $this->vista($request, 'asesor.proyecto', [
            ...$this->seguimiento->ficha($proyecto),
            'entregas' => $this->seguimiento->entregas($proyecto),
            'observaciones' => $avances->observaciones($proyecto),
            'historial' => $this->seguimiento->historial($proyecto),
        ], 'asesor', 'proyectos');
    }

    public function revisar(Request $request, Proyecto $proyecto, Entrega $entrega): View
    {
        $id = $this->identidad($request);

        return $this->vista($request, 'asesor.revisar', [
            ...$this->seguimiento->ficha($proyecto),
            'entrega' => $entrega,
            'revision' => $this->revisiones->revisionAvance($entrega),
            'abiertas' => $this->revisiones->observacionesAbiertasDe($proyecto, (int) $id->idDocente),
            'secciones' => Parametros::SECCIONES_DOCUMENTO,
            'dictamenes' => RevisionService::DICTAMENES_AVANCE,
        ], 'asesor', 'proyectos');
    }

    public function guardarRevision(RevisarAvanceRequest $request, Proyecto $proyecto, Entrega $entrega): RedirectResponse
    {
        $this->revisiones->revisarAvance($proyecto, $entrega, $this->identidad($request), $request->validated());

        return redirect()->route('tesis-proyecto-tesis.asesor.proyecto', $proyecto)
            ->with('status', "Revisión de la entrega v{$entrega->version} enviada a los estudiantes.");
    }

    public function levantar(Request $request, Proyecto $proyecto, Observacion $observacion): RedirectResponse
    {
        $this->revisiones->levantar($observacion, $this->identidad($request), $proyecto);

        return back()->with('status', 'Observación levantada.');
    }
}
