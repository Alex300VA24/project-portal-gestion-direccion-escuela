<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEstudiante\Requests\SolicitudTutoriaRequest;
use App\Modules\SeguimientoEstudiante\Services\DocumentoPdf;
use App\Modules\SeguimientoEstudiante\Services\EvidenciaExpedienteService;
use App\Modules\SeguimientoEstudiante\Services\Nivelacion\IngresoNivelacionService;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use App\Modules\SeguimientoEstudiante\Services\SolicitudTutoriaService;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            ->paginate(15);
        $puedeProgramar = (int) $perfil->id_persona !== $persona
            && $perfil->estado === 'Activo'
            && $request->user()->can('seguimiento-estudiante.tutoria.programar');
        $claveEnvio = (string) Str::uuid();

        $registros = $evidencia->consultar($request->user(), $persona, $perfil);

        return view('modules.seguimiento-estudiante.expediente', $registros + compact('perfil', 'puedeSolicitar', 'periodos', 'solicitudes', 'claveEnvio', 'puedeProgramar', 'persona'));
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

    public function programarTutoria(Request $request, string $estudiante, string $solicitud, string $accion = 'programar'): RedirectResponse
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idSolicitud = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false || $idSolicitud === false, 404);

        $reglas = match ($accion) {
            'programar', 'reprogramar' => [
                'fecha_hora' => ['required', 'date_format:Y-m-d\TH:i'],
                'duracion_minutos' => ['required', 'integer', 'between:10,120'],
                'modalidad' => ['required', 'in:PRESENCIAL,VIRTUAL'],
                'lugar' => ['required', 'string', 'max:250'],
                'nota' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            'cumplir' => [],
            'cancelar', 'mantener' => ['nota' => ['required', 'string', 'min:5', 'max:1000']],
            'registrar', 'corregir' => [
                'asistencia' => ['required', 'in:ASISTIO,NO_ASISTIO,NO_REALIZADA'],
                'actividad' => ['required_if:asistencia,ASISTIO', 'nullable', 'string', 'min:5', 'max:2000'],
                'acuerdos' => ['required_if:asistencia,ASISTIO', 'nullable', 'string', 'max:2000'],
                'observaciones' => ['nullable', 'string', 'max:2000'],
            ],
        };
        if ($accion === 'corregir') {
            $reglas['motivo_correccion'] = ['required', 'string', 'min:10', 'max:1000'];
        }
        $reglas['version'] = [$accion === 'programar' ? 'sometimes' : 'required', 'integer', 'min:1'];
        $datos = $request->validate($reglas);

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

    public function solicitarCambioTutoria(Request $request, string $estudiante, string $solicitud): RedirectResponse
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($estudiante, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idSolicitud = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false || $idSolicitud === false, 404);

        $datos = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'tipo_cambio' => ['required', 'in:REPROGRAMACION,CANCELACION'],
            'motivo_cambio' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $this->solicitudes->solicitarCambio($request->user(), $persona, $id, $idSolicitud, $datos);

        return redirect()->route('seguimiento-estudiante.expediente', $id)
            ->with('status', 'Petición de cambio enviada al tutor.');
    }

    public function constanciaTutoria(Request $request, DocumentoPdf $pdf, string $solicitud): Response
    {
        $persona = $this->service->personaVinculada($request->user());
        $id = filter_var($solicitud, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $perfil = Estudiante::query()->where('id_persona', $persona)
            ->whereExists(DB::table('seguimiento_estudiante.solicitud_estudiante as solicitud')
                ->whereColumn('solicitud.id_estudiante', 'core.estudiante.id_estudiante')
                ->where('solicitud.id_solicitud', $id)->where('solicitud.tipo', 'Tutoria'))
            ->with('persona:id_persona,nombres,apellido_paterno,apellido_materno')
            ->firstOrFail(['id_estudiante', 'id_persona', 'codigo_universitario', 'id_escuela']);
        $atencion = $this->solicitudes->visibles($request->user(), $persona, $perfil)
            ->where('solicitud.id_solicitud', $id)->where('solicitud.estado', 'Atendida')
            ->whereNotNull('cita.fecha_hora')->first();
        abort_if($atencion === null, 404);
        $escuela = DB::table('core.escuela_profesional')->where('id_escuela', $perfil->id_escuela)->value('nombre');
        $tutor = DB::table('core.persona')->where('id_persona', $atencion->id_persona_tutor)
            ->first(['nombres', 'apellido_paterno', 'apellido_materno']);

        return $pdf->descargar('modules.seguimiento-estudiante.tutoria.constancia-pdf', compact('perfil', 'atencion', 'escuela', 'tutor'), 'constancia-tutoria.pdf');
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
