<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AsignacionTutoriaService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\PlanGeneralTutoriaService;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\PlanTutoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class PlanGeneralTutoriaController extends Controller
{
    public function __construct(
        private readonly SeguimientoEstudianteService $seguimiento,
        private readonly AsignacionTutoriaService $asignaciones,
        private readonly PlanTutoriaService $aportes,
        private readonly PlanGeneralTutoriaService $planes,
    ) {}

    public function index(Request $request): View
    {
        return view('modules.seguimiento-estudiante.plan-general', ['ambitos' => $this->ambitos($request)]);
    }

    public function mostrar(Request $request, string $escuela, string $periodo): View
    {
        $ambito = $this->ambito($request, $escuela, $periodo);

        return view('modules.seguimiento-estudiante.plan-general', $this->planes->consultar($ambito->id_escuela, $ambito->id_periodo) + [
            'ambito' => $ambito,
            'requestId' => (string) Str::uuid(),
        ]);
    }

    public function publicar(Request $request, string $escuela, string $periodo): RedirectResponse
    {
        $actor = $request->user();
        $ambito = $this->ambito($request, $escuela, $periodo);
        abort_unless($ambito->gestion, 404);
        $datos = $request->validate([
            'version' => 'required|integer|min:0',
            'request_id' => 'required|uuid',
            'title' => 'required|string|min:10|max:200',
            'approval_reference' => 'required|string|min:3|max:200',
            'approved_by' => 'required|string|min:5|max:200',
            'approved_on' => 'required|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'valid_from' => 'required|date_format:Y-m-d|after_or_equal:approved_on',
            'valid_until' => 'required|date_format:Y-m-d|after_or_equal:valid_from',
            'approval_confirmed' => 'required|accepted',
            'pdf' => 'required|file|mimetypes:application/pdf|max:5120',
        ]);
        $this->planes->publicar($this->seguimiento->personaVinculada($actor), $actor->getKey(), $ambito->id_escuela, $ambito->id_periodo, $datos);

        return redirect()->route('seguimiento-estudiante.plan-general.mostrar', [$escuela, $periodo])
            ->with('status', 'Plan general registrado con su evidencia de aprobación.');
    }

    public function retirar(Request $request, string $escuela, string $periodo, string $documento): RedirectResponse
    {
        $actor = $request->user();
        $ambito = $this->ambito($request, $escuela, $periodo);
        abort_unless($ambito->gestion, 404);
        $datos = $request->validate(['version' => 'required|integer|min:1', 'reason' => 'required|string|min:10|max:1000']);
        $this->planes->retirar($this->seguimiento->personaVinculada($actor), $actor->getKey(), $ambito->id_escuela, $ambito->id_periodo, $documento, $datos);

        return redirect()->route('seguimiento-estudiante.plan-general.mostrar', [$escuela, $periodo])
            ->with('status', 'Publicación retirada. El PDF se conserva en el historial.');
    }

    public function descargar(Request $request, string $escuela, string $periodo, string $documento): Response
    {
        $ambito = $this->ambito($request, $escuela, $periodo);

        return $this->planes->descargar($ambito->id_escuela, $ambito->id_periodo, $documento);
    }

    private function ambito(Request $request, string $escuela, string $periodo): object
    {
        $ambito = $this->ambitos($request)->first(fn (object $item): bool => (string) $item->id_escuela === $escuela && (string) $item->id_periodo === $periodo);
        abort_if($ambito === null, 404);

        return $ambito;
    }

    private function ambitos(Request $request): Collection
    {
        $actor = $request->user();
        $puedePublicar = $actor->can('seguimiento-estudiante.plan-general.publicar');
        $puedeConsultar = $actor->can('seguimiento-estudiante.tutoria.view');
        abort_unless($puedePublicar || $puedeConsultar, 403);
        $persona = $this->seguimiento->personaVinculada($actor);
        $coordinacion = $puedePublicar ? $this->asignaciones->ambitos($persona)
            ->get(['ambito.id_escuela', 'ambito.id_periodo', 'escuela.nombre as escuela', 'periodo.codigo as periodo'])
            ->each(fn (object $item) => $item->gestion = true) : collect();
        $tutoria = $puedeConsultar ? collect($this->aportes->ambitos($persona))
            ->each(fn (object $item) => $item->gestion = false) : collect();

        return $coordinacion->concat($tutoria)->unique(fn (object $item) => [$item->id_escuela, $item->id_periodo])
            ->sortBy(['escuela', 'periodo']);
    }
}
