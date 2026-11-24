<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEstudiante\Requests\CambioCitaTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Requests\ResolverCitaTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Requests\SolicitudTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Responses\DescargaPdf;
use App\Modules\SeguimientoEstudiante\Services\EvidenciaExpedienteService;
use App\Modules\SeguimientoEstudiante\Services\Nivelacion\IngresoNivelacionService;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\SolicitudTutoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class SeguimientoEstudianteController extends Controller
{
    public function __construct(
        private readonly SeguimientoEstudianteService $service,
        private readonly ExpedientePolicy $policy,
        private readonly SolicitudTutoriaService $solicitudes,
    ) {}

    public function index(Request $request): View
    {
        $persona = $this->service->personaVinculada($request->user());
        $estudiantes = $this->policy->visibles($request->user(), $persona)
            ->with('persona:id_persona,nombres,apellido_paterno,apellido_materno')
            ->orderBy('codigo_universitario')
            ->paginate(25, ['id_estudiante', 'id_persona', 'codigo_universitario']);

        return view('modules.seguimiento-estudiante.index', compact('estudiantes'));
    }

    public function expediente(Request $request, EvidenciaExpedienteService $evidencia, string $estudiante): View
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        $perfil = $this->policy->visibles($request->user(), $persona)
            ->with('persona:id_persona,nombres,apellido_paterno,apellido_materno')
            ->findOrFail($id, ['id_estudiante', 'id_persona', 'codigo_universitario', 'estado']);

        $puedeSolicitar = $this->policy->puedeSolicitar($request->user(), $persona, $perfil);
        $periodos = $puedeSolicitar
            ? $this->solicitudes->periodosActivos()->orderBy('fecha_inicio')->get(['id_periodo', 'codigo'])
            : collect();
        $solicitudes = $this->solicitudes->visibles($request->user(), $persona, $perfil)
            ->orderByDesc('solicitud.solicitada_en')
            ->orderByDesc('solicitud.id_solicitud')
            ->paginate(15)->withQueryString();
        $historicas = $this->solicitudes->historicas($persona, $perfil);
        $puedeProgramar = (int) $perfil->id_persona !== $persona
            && $perfil->estado === 'Activo'
            && $request->user()->can('seguimiento-estudiante.tutoria.programar');
        $claveEnvio = (string) Str::uuid();

        $registros = $evidencia->consultar($request->user(), $persona, $perfil);

        return view('modules.seguimiento-estudiante.expediente', $registros + compact('perfil', 'puedeSolicitar', 'periodos', 'solicitudes', 'historicas', 'claveEnvio', 'puedeProgramar', 'persona'));
    }

    public function solicitarTutoria(SolicitudTutoriaRequest $request, string $estudiante): RedirectResponse
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        $this->solicitudes->registrar($request->user(), $persona, $id, $request->validated());

        return redirect()->route('seguimiento-estudiante.expediente', $id)
            ->with('status', 'Solicitud de tutoría registrada.');
    }

    public function programarTutoria(ResolverCitaTutoriaRequest $request, string $estudiante, string $solicitud, string $accion = 'programar'): RedirectResponse
    {
        $persona = $request->persona();
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idSolicitud = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false || $idSolicitud === false, 404);

        $datos = $request->validated();

        $this->solicitudes->resolverCita($request->user(), $persona, $id, $idSolicitud, $accion, $datos);

        $mensaje = match ($accion) {
            'programar' => 'Cita de tutoría programada.',
            'reprogramar' => 'Cita de tutoría reprogramada.',
            'mantener' => 'Se mantuvo la solicitud de tutoría.',
            'cancelar' => 'Solicitud de tutoría cancelada.',
            'registrar' => 'Atención de tutoría registrada.',
            'corregir' => 'Atención de tutoría corregida.',
            'cumplir' => 'Compromiso registrado como cumplido.',
        };

        return redirect()->route('seguimiento-estudiante.expediente', $id)->with('status', $mensaje);
    }

    public function solicitarCambioTutoria(CambioCitaTutoriaRequest $request, string $estudiante, string $solicitud): RedirectResponse
    {
        $persona = $request->persona();
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idSolicitud = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false || $idSolicitud === false, 404);

        $datos = $request->validated();
        $this->solicitudes->solicitarCambio($request->user(), $persona, $id, $idSolicitud, $datos);

        return redirect()->route('seguimiento-estudiante.expediente', $id)
            ->with('status', 'Petición de cambio enviada al tutor.');
    }

    public function constanciaTutoria(Request $request, DescargaPdf $pdf, string $solicitud): Response
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $datos = $this->solicitudes->constancia($request->user(), $persona, $id);
        abort_if($datos === null, 404);

        return $pdf->generar('modules.seguimiento-estudiante.tutoria.constancia-pdf', $datos, 'constancia-tutoria.pdf');
    }

    public function ingresoNivelacion(Request $request, IngresoNivelacionService $nivelacion, ?string $estudiante = null): View
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = $estudiante === null ? null : filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return view('modules.seguimiento-estudiante.nivelacion.ingreso', $nivelacion->consultar($persona, $id));
    }

    public function apiIndex(Request $request): JsonResponse
    {
        $this->service->personaVinculada($request->user());

        return response()->json([
            'module' => 'Seguimiento al Estudiante',
            'schema' => 'seguimiento_estudiante',
        ]);
    }
}
