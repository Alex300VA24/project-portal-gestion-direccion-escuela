<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Exceptions\DocumentoNoDisponible;
use App\Modules\SeguimientoEstudiante\Requests\PlanGeneralTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Responses\DescargaPdf;
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

    public function publicar(PlanGeneralTutoriaRequest $request, string $escuela, string $periodo): RedirectResponse
    {
        try {
            $this->planes->publicar(
                $request->persona(), $request->user()->getKey(), (int) $escuela, (int) $periodo,
                $request->safe()->except(['pdf']), $request->file('pdf')->getContent(),
            );
        } catch (DocumentoNoDisponible $error) {
            abort(503, $error->getMessage());
        }

        return redirect()->route('seguimiento-estudiante.plan-general.mostrar', [$escuela, $periodo])
            ->with('status', 'Plan general registrado con su evidencia de aprobación.');
    }

    public function retirar(PlanGeneralTutoriaRequest $request, string $escuela, string $periodo, string $documento): RedirectResponse
    {
        $this->planes->retirar(
            $request->persona(), $request->user()->getKey(), (int) $escuela, (int) $periodo, $documento, $request->validated(),
        );

        return redirect()->route('seguimiento-estudiante.plan-general.mostrar', [$escuela, $periodo])
            ->with('status', 'Publicación retirada. El PDF se conserva en el historial.');
    }

    public function descargar(Request $request, DescargaPdf $pdf, string $escuela, string $periodo, string $documento): Response
    {
        $ambito = $this->ambito($request, $escuela, $periodo);
        $archivo = $this->planes->documento($ambito->id_escuela, $ambito->id_periodo, $documento);
        abort_if($archivo === null, 404);
        abort_if($archivo['contenido'] === null, 503, 'El PDF registrado no está disponible.');

        return $pdf->adjuntar($archivo['contenido'], 'F4-general-v'.$archivo['version'].'.pdf');
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
