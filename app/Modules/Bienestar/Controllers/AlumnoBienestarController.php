<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Requests\StoreSolicitudConRequisitosRequest;
use App\Modules\Bienestar\Requests\SubsanarRequisitosRequest;
use App\Modules\Bienestar\Services\SolicitudTramiteService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class AlumnoBienestarController extends Controller
{
    public function __construct(
        private readonly SolicitudTramiteService $tramiteService,
    ) {}

    public function solicitudes(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);

        $solicitudes = SolicitudApoyo::query()
            ->where('id_estudiante', $actor->idEstudiante)
            ->with(['tipoSolicitud', 'documentosRequisitos.requisito'])
            ->orderBy('solicitada_en', 'desc')
            ->paginate(10);

        return view('modules.bienestar.alumno.solicitudes.index', [
            'solicitudes' => $solicitudes,
            'tramites' => $this->tramitesDisponibles(),
            'actor' => $actor,
        ]);
    }

    public function crearSolicitud(): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        return redirect()->route('bienestar.alumno.solicitudes.index', ['abrir' => 'm-solicitud']);
    }

    /** Solo lo que necesita el formulario: nunca rutas internas de archivos. */
    private function tramitesDisponibles(): Collection
    {
        return TipoSolicitud::activos()->with('requisitosActivos')->orderBy('nombre')->get()
            ->map(fn ($t) => $t->only(['id_tipo_solicitud', 'nombre', 'descripcion']) + [
                'requisitos' => $t->requisitosActivos->map(fn ($r) => $r->only(['id_requisito', 'nombre', 'descripcion', 'es_obligatorio', 'formatos_permitidos', 'tamano_maximo_mb']) + [
                    'plantilla' => $r->id_archivo_plantilla ? route('bienestar.requisitos.plantilla', $r) : null,
                ])->values(),
            ])->values();
    }

    public function guardarSolicitud(StoreSolicitudConRequisitosRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);

        $this->tramiteService->registrarSolicitud(
            $actor->idEstudiante,
            $request->integer('id_tipo_solicitud'),
            $request->input('motivo'),
            $request->file('archivos', []),
            $actor->idUsuario
        );

        return redirect()
            ->route('bienestar.alumno.solicitudes.index')
            ->with('status', 'Su solicitud ha sido enviada con éxito. Será revisada por la Secretaría antes de su pase a Bienestar.');
    }

    /** Seguimiento por etapas de una solicitud propia. */
    public function mostrarSolicitud(Request $request, SolicitudApoyo $solicitud): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);
        abort_unless($solicitud->id_estudiante === $actor->idEstudiante, 404);

        $solicitud->load(['tipoSolicitud', 'historial', 'documentosRequisitos.requisito', 'documentosRequisitos.archivo']);

        return view('modules.bienestar.alumno.solicitudes.show', ['solicitud' => $solicitud]);
    }

    public function subsanarSolicitud(Request $request, SolicitudApoyo $solicitud): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);

        if ($solicitud->id_estudiante !== $actor->idEstudiante) {
            abort(404, 'Solicitud no encontrada.');
        }

        if ($solicitud->estado !== 'observada') {
            abort(403, 'Esta solicitud no se encuentra en estado observada.');
        }

        return redirect()->route('bienestar.alumno.solicitudes.show', [$solicitud, 'abrir' => 'm-subsanar-'.$solicitud->id_solicitud]);
    }

    public function guardarSubsanacion(SubsanarRequisitosRequest $request, SolicitudApoyo $solicitud): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);

        if ($solicitud->id_estudiante !== $actor->idEstudiante) {
            abort(404, 'Solicitud no encontrada.');
        }

        $this->tramiteService->subsanarRequisitos(
            $solicitud,
            $request->file('archivos', []),
            $actor->idUsuario
        );

        return redirect()
            ->route('bienestar.alumno.solicitudes.index')
            ->with('status', 'Documentación observada subsanada correctamente. La Secretaría reanudará la revisión.');
    }

    public function citas(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureEstudiante($actor);

        $citas = Cita::query()
            ->whereHas('caso', function ($q) use ($actor) {
                $q->where('id_estudiante', $actor->idEstudiante);
            })
            ->with(['caso.tipoCaso'])
            ->orderBy('inicio', 'desc')
            ->paginate(10);

        return view('modules.bienestar.alumno.citas.index', [
            'citas' => $citas,
            'actor' => $actor,
        ]);
    }
}
