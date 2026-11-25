<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\SolicitudRequisitoDocumento;
use App\Modules\Bienestar\Requests\FiltrarAlertaRequest;
use App\Modules\Bienestar\Requests\RevisarRequisitosRequest;
use App\Modules\Bienestar\Services\CicloEstudianteService;
use App\Modules\Bienestar\Services\SecretariaFiltroService;
use App\Modules\Bienestar\Services\SolicitudTramiteService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\FiltrosConsulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SecretariaBienestarController extends Controller
{
    public function __construct(
        private readonly SecretariaFiltroService $filtroService,
        private readonly SolicitudTramiteService $tramiteService,
        private readonly CicloEstudianteService $ciclos,
    ) {}

    /**
     * Bandeja de entrada de Alertas para Secretaría.
     * Solo expone datos administrativos mínimos: código, nombres, ciclo y datos esenciales de la alerta.
     * Ley 29733: Protege la intimidad y salud del estudiante no mostrando descripciones sensibles ni casos.
     */
    public function alertas(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $alertas = $this->filtroService->obtenerBandejaSecretaria(FiltrosConsulta::texto($request));

        return view('modules.bienestar.secretaria.alertas', [
            'alertas' => $alertas,
            'ciclos' => $this->ciclos->paraEstudiantes($alertas->pluck('id_estudiante')),
            'filtros' => FiltrosConsulta::texto($request),
            'actor' => $actor,
        ]);
    }

    /**
     * Aplica la decisión de la Secretaría sobre una alerta (Pasar a Bienestar o Descartar con motivo).
     * Salvaguarda: las alertas automáticas de riesgo alto no se pueden descartar.
     */
    public function procesarAlerta(FiltrarAlertaRequest $request, AlertaRiesgo $alerta): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $this->filtroService->procesarDecision(
            $alerta,
            $request->input('accion'),
            $request->input('motivo'),
            $actor->idUsuario
        );

        $mensaje = $request->input('accion') === 'pasar_bienestar'
            ? 'Alerta remitida exitosamente al equipo de Bienestar Universitario.'
            : 'Alerta descartada con motivo registrado en bitácora institucional.';

        return redirect()
            ->route('bienestar.secretaria.alertas')
            ->with('status', $mensaje);
    }

    /**
     * Bandeja de solicitudes de apoyo para revisión de la Secretaría.
     */
    public function solicitudes(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $query = SolicitudApoyo::query()
            ->with([
                'estudiante:id_estudiante,codigo_universitario,id_persona',
                'estudiante.persona:id_persona,nombres,apellido_paterno,apellido_materno',
                'tipoSolicitud',
                'documentosRequisitos',
            ])
            ->where(fn ($q) => $q->whereIn('estado', ['enviada', 'recibida', 'observada', 'remitida'])->orWhereNotNull('revisado_por_secretaria'))
            ->orderBy('solicitada_en', 'desc');

        if ($estado = FiltrosConsulta::texto($request)['estado'] ?? null) {
            $query->where('estado', $estado);
        }

        $solicitudes = $query->paginate(15)->withQueryString();

        return view('modules.bienestar.secretaria.solicitudes', [
            'solicitudes' => $solicitudes,
            'ciclos' => $this->ciclos->paraEstudiantes($solicitudes->pluck('id_estudiante')),
            'filtros' => FiltrosConsulta::texto($request),
            'actor' => $actor,
        ]);
    }

    /**
     * Pantalla de revisión requisito por requisito de una solicitud.
     */
    public function revisarSolicitud(Request $request, SolicitudApoyo $solicitud): View
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        abort_unless($solicitud->correspondeASecretaria(), 404);

        $solicitud->load([
            'estudiante.persona',
            'tipoSolicitud.requisitosActivos',
            'documentosRequisitos.requisito',
            'documentosRequisitos.archivo',
        ]);

        return view('modules.bienestar.secretaria.revisar', [
            'solicitud' => $solicitud,
            'ciclo' => $this->ciclos->paraEstudiantes([$solicitud->id_estudiante])[$solicitud->id_estudiante],
            'actor' => $actor,
        ]);
    }

    /**
     * Procesa la revisión documental de la solicitud (Recibir, Observar o Remitir a Bienestar).
     * Al remitir, comprueba la confirmación de foliado externo fuera del sistema.
     */
    public function guardarRevision(RevisarRequisitosRequest $request, SolicitudApoyo $solicitud): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        abort_unless($solicitud->correspondeASecretaria(), 404);
        $accion = $request->input('accion');

        if ($accion === 'recibir') {
            $this->tramiteService->recibirSolicitud($solicitud, $actor->idUsuario);
            $msg = 'Solicitud recibida para inicio de verificación documental.';
        } elseif ($accion === 'observar') {
            $this->tramiteService->observarSolicitud(
                $solicitud,
                $request->input('revisiones', []),
                $request->input('observacion_general'),
                $actor->idUsuario
            );
            $msg = 'Solicitud observada. El estudiante ha sido notificado para subsanar los requisitos observados.';
        } elseif ($accion === 'remitir') {
            $this->tramiteService->remitirABienestar(
                $solicitud,
                $request->boolean('confirma_folio_externo'),
                $request->input('revisiones', []),
                $actor->idUsuario
            );
            $msg = 'Solicitud remitida satisfactoriamente a Bienestar Universitario tras confirmar el foliado externo.';
        } else {
            return back()->withErrors(['accion' => 'Acción no reconocida.']);
        }

        return redirect()->route('bienestar.secretaria.solicitudes')->with('status', $msg);
    }

    /**
     * Descarga de documento privado con nombre UUID previa validación de acceso.
     */
    public function descargarDocumento(Request $request, SolicitudRequisitoDocumento $documento): StreamedResponse
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        abort_unless(SolicitudApoyo::find($documento->id_solicitud)?->correspondeASecretaria(), 404);

        $archivo = $documento->archivo;
        if (! $archivo || ! Storage::disk('local')->exists($archivo->ruta_almacenamiento)) {
            abort(404, 'El archivo solicitado no existe en el almacenamiento seguro.');
        }

        return Storage::disk('local')->download(
            $archivo->ruta_almacenamiento,
            $archivo->nombre_original
        );
    }

    /**
     * Indicadores operativos exclusivos de la Secretaría.
     */
    public function indicadores(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SECRETARIA);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $indicadores = $this->filtroService->obtenerIndicadoresOperativos();

        return view('modules.bienestar.secretaria.indicadores', [
            'indicadores' => $indicadores,
            'actor' => $actor,
        ]);
    }
}
