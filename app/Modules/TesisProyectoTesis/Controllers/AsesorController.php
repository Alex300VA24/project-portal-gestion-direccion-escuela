<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\FiltrarBandejaAsesorRequest;
use App\Modules\TesisProyectoTesis\Requests\RegistrarSimilitudRequest;
use App\Modules\TesisProyectoTesis\Requests\RevisarAvanceRequest;
use App\Modules\TesisProyectoTesis\Requests\VerificarRubricaRequest;
use App\Modules\TesisProyectoTesis\Services\AsesorService;
use App\Modules\TesisProyectoTesis\Services\AvanceService;
use App\Modules\TesisProyectoTesis\Services\RevisionService;
use App\Modules\TesisProyectoTesis\Services\RubricaService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Services\SimilitudService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use App\Modules\TesisProyectoTesis\Support\Parametros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AsesorController extends BaseController
{
    public function __construct(
        private readonly SeguimientoService $seguimiento,
        private readonly RevisionService $revisiones,
    ) {}

    public function proyectos(FiltrarBandejaAsesorRequest $request, AsesorService $asesor): View
    {
        $id = $this->identidad($request);
        $filtro = $request->validated();
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

        return redirect()->route('tesis-proyecto-tesis.asesor.proyectos')
            ->with('status', "Revisión de la entrega v{$entrega->version} de {$proyecto->codigo} enviada a los estudiantes.");
    }

    public function verificar(Request $request, Proyecto $proyecto, RubricaService $rubrica): View
    {
        $id = $this->identidad($request);
        $entrega = $rubrica->entregaObjetivo($proyecto);
        $criterios = $rubrica->criterios($proyecto->etapa);

        return $this->vista($request, 'asesor.verificar', [
            ...$this->seguimiento->ficha($proyecto),
            'entrega' => $entrega,
            'criterios' => $criterios,
            'rubrica' => $rubrica->vigente(),
            'registrada' => $entrega ? $rubrica->revisionDe($entrega, (int) $id->idDocente, 'Verificacion') : null,
            'anterior' => $rubrica->ultimaVerificacion($proyecto, (int) $id->idDocente),
            'abiertas' => $this->revisiones->observacionesAbiertasDe($proyecto, (int) $id->idDocente),
            'habilitado' => $entrega !== null && in_array($proyecto->estado, Estados::DESARROLLO, true),
        ], 'asesor', 'proyectos');
    }

    public function guardarVerificacion(VerificarRubricaRequest $request, Proyecto $proyecto, RubricaService $rubrica): RedirectResponse
    {
        $revision = $rubrica->verificarComoAsesor($proyecto, $this->identidad($request), $request->validated());

        return $revision->conforme
            ? redirect()->route('tesis-proyecto-tesis.asesor.similitud', $proyecto)->with('status', 'Rúbrica conforme. Ahora registra el reporte de Turnitin.')
            : redirect()->route('tesis-proyecto-tesis.asesor.proyectos')->with('status', "Verificación de {$proyecto->codigo} guardada. Las observaciones se enviaron a los estudiantes.");
    }

    public function similitud(Request $request, Proyecto $proyecto, SimilitudService $similitud): View
    {
        $id = $this->identidad($request);

        return $this->vista($request, 'asesor.similitud', [
            ...$this->seguimiento->ficha($proyecto),
            ...$similitud->requisitos($proyecto, (int) $id->idDocente),
            'historialReportes' => $similitud->historial($proyecto),
            'maximo' => Parametros::SIMILITUD_MAXIMA,
        ], 'asesor', 'proyectos');
    }

    public function registrarSimilitud(RegistrarSimilitudRequest $request, Proyecto $proyecto, SimilitudService $similitud): RedirectResponse
    {
        $reporte = $similitud->registrar($proyecto, $this->identidad($request), (float) $request->validated('porcentaje'),
            $request->file('archivo'), $request->validated('indicaciones'), $request->boolean('conformidad'));

        return $reporte->resultado === 'Apto'
            ? redirect()->route('tesis-proyecto-tesis.asesor.proyectos')->with('status', "Conformidad registrada ({$reporte->porcentaje} %). Se avisó al Director y a los estudiantes.")
            : redirect()->route('tesis-proyecto-tesis.asesor.similitud', $proyecto)->with('error', "Similitud {$reporte->porcentaje} %: supera el límite. El proyecto vuelve a los estudiantes para corregir.");
    }

    public function levantar(Request $request, Proyecto $proyecto, Observacion $observacion): RedirectResponse
    {
        $this->revisiones->levantar($observacion, $this->identidad($request), $proyecto);

        return back()->with('status', 'Observación levantada.');
    }
}
