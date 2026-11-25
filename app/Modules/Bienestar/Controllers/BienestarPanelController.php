<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\AlertaBitacora;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\Beneficio;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Derivacion;
use App\Modules\Bienestar\Models\Especialista;
use App\Modules\Bienestar\Models\SeguimientoDerivacion;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\SolicitudRequisitoDocumento;
use App\Modules\Bienestar\Models\TipoCaso;
use App\Modules\Bienestar\Requests\AtenderAlertaRequest;
use App\Modules\Bienestar\Requests\EvaluarSolicitudApoyoRequest;
use App\Modules\Bienestar\Requests\RegistrarResultadoRequest;
use App\Modules\Bienestar\Requests\StoreAlertaManualRequest;
use App\Modules\Bienestar\Requests\StoreDerivacionRequest;
use App\Modules\Bienestar\Services\CasoBienestarService;
use App\Modules\Bienestar\Services\SolicitudTramiteService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\FiltrosConsulta;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BienestarPanelController extends Controller
{
    public function __construct(private readonly CasoBienestarService $casos) {}

    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $filtros = FiltrosConsulta::texto($request);
        $tab = $filtros['tab'] ?? 'activas';

        $query = AlertaRiesgo::query()
            ->with(['estudiante.persona', 'docente.persona', 'caso', 'bitacoras.usuario'])
            ->orderByRaw("CASE WHEN prioridad = 'alta' OR prioridad = 'urgente' THEN 1 WHEN prioridad = 'media' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        if ($tab === 'descartadas') {
            $query->where('estado', 'descartada');
        } else {
            // Por defecto, Bienestar solo ve lo que Secretaría le pasó (remitida_bienestar, en_atencion, atendida)
            $query->whereIn('estado', ['remitida_bienestar', 'en_atencion', 'atendida']);
        }

        if (! empty($filtros['origen'])) {
            $query->where('origen', $filtros['origen']);
        }

        if (! empty($filtros['prioridad'])) {
            $query->where('prioridad', $filtros['prioridad']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = trim($filtros['buscar']);
            $query->where(function ($q) use ($buscar) {
                $q->whereHas('estudiante', function ($eq) use ($buscar) {
                    $eq->where('codigo_universitario', 'like', "%{$buscar}%")
                        ->orWhereHas('persona', function ($pq) use ($buscar) {
                            $pq->where('nombres', 'ilike', "%{$buscar}%")
                                ->orWhere('apellido_paterno', 'ilike', "%{$buscar}%")
                                ->orWhere('apellido_materno', 'ilike', "%{$buscar}%");
                        });
                });
            });
        }

        $alertas = $query->paginate(15)->withQueryString();

        $totales = [
            'remitidas' => AlertaRiesgo::whereIn('estado', ['remitida_bienestar', 'en_atencion'])->count(),
            'alta_prioridad' => AlertaRiesgo::whereIn('prioridad', ['alta', 'urgente'])->whereIn('estado', ['remitida_bienestar', 'en_atencion'])->count(),
            'descartadas' => AlertaRiesgo::where('estado', 'descartada')->count(),
            'casos_abiertos' => CasoBienestar::where('estado', '!=', 'Cerrado')->count(),
        ];

        return view('modules.bienestar.panel.index', [
            'alertas' => $alertas,
            'totales' => $totales,
            'filtros' => $filtros,
            'tab' => $tab,
        ]);
    }

    public function reabrirAlerta(Request $request, AlertaRiesgo $alerta, \App\Modules\Bienestar\Services\SecretariaFiltroService $filtroService): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $filtroService->reabrirAlerta($alerta, $request->input('motivo'), $actor->idUsuario);

        return back()->with('status', 'Alerta reabierta exitosamente e incorporada a las alertas de Bienestar.');
    }

    public function atender(Request $request, AlertaRiesgo $alerta): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        // Lo que Secretaría aún no pasó no existe para Bienestar
        abort_if($alerta->estado === 'en_revision', 404);

        $alerta->load(['estudiante.persona', 'docente.persona', 'bitacoras.usuario', 'caso.historial', 'caso.citas', 'caso.derivaciones.especialistaDestino']);
        $tiposCaso = TipoCaso::all();

        return view('modules.bienestar.panel.atender', [
            'alerta' => $alerta,
            'tiposCaso' => $tiposCaso,
            'especialistas' => Especialista::activos()->with('especialidad')->get(),
        ]);
    }

    public function guardarAtencion(AtenderAlertaRequest $request, AlertaRiesgo $alerta): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $accion = $request->input('accion');

        DB::transaction(function () use ($request, $alerta, $actor, $accion) {
            // Se bloquea la fila para que dos envíos simultáneos no dupliquen el caso
            $alerta = AlertaRiesgo::query()->lockForUpdate()->findOrFail($alerta->id_alerta);

            if (! $alerta->puedeAtenderse()) {
                throw ValidationException::withMessages([
                    'accion' => 'Solo se pueden atender alertas remitidas por Secretaría que no hayan sido cerradas.',
                ]);
            }

            if ($accion === 'abrir_caso' && $alerta->id_caso !== null) {
                throw ValidationException::withMessages([
                    'accion' => 'Esta alerta ya tiene un caso abierto.',
                ]);
            }

            $alerta->atendido_por = $actor->idUsuario;

            if ($accion === 'abrir_caso') {
                $idTipoCaso = (int) $request->input('id_tipo_caso');
                $descripcion = (string) ($request->input('descripcion_caso') ?: 'Caso abierto a partir de alerta '.$alerta->regla);

                $caso = $this->casos->abrir([
                    'id_estudiante' => $alerta->id_estudiante,
                    'id_tipo_caso' => $idTipoCaso,
                    'descripcion' => $descripcion,
                ], 'Apertura de caso desde panel de riesgo', $actor->idUsuario);

                $alerta->id_caso = $caso->id_caso;
                $alerta->estado = 'en_atencion';
                $alerta->save();

            } elseif ($accion === 'programar_cita') {
                $caso = $alerta->caso;
                if (! $caso) {
                    $tipoAcademico = TipoCaso::firstOrCreate(['nombre' => 'Riesgo Academico'], ['descripcion' => 'Casos de riesgo'])->id_tipo_caso;
                    $caso = $this->casos->abrir([
                        'id_estudiante' => $alerta->id_estudiante,
                        'id_tipo_caso' => $tipoAcademico,
                        'descripcion' => 'Caso generado para programación directa de cita de orientación',
                    ], 'Apertura de caso al programar una cita', $actor->idUsuario);
                    $alerta->id_caso = $caso->id_caso;
                }

                $this->casos->programarCita($caso, $request->input('inicio_cita'), $request->input('modalidad', 'Presencial'), $actor->idUsuario);

                $alerta->estado = 'en_atencion';
                $alerta->save();

            } elseif ($accion === 'descartar') {
                $alerta->estado = 'descartada';
                $alerta->fecha_resolucion = now();
                $detalles = $alerta->detalles ?? [];
                $detalles['motivo_descarte'] = $request->input('motivo_descarte');
                $alerta->detalles = $detalles;
                $alerta->save();

                AlertaBitacora::create([
                    'id_alerta' => $alerta->id_alerta,
                    'id_usuario' => $actor->idUsuario,
                    'accion' => 'descartado',
                    'motivo' => $request->input('motivo_descarte'),
                    'creado_en' => now(),
                ]);
            }
        });

        return ($accion === 'descartar'
            ? redirect()->route('bienestar.panel.index')
            : redirect()->route('bienestar.panel.atender', $alerta))
            ->with('status', 'Alerta procesada con éxito.');
    }

    public function formularioDerivacion(CasoBienestar $caso): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        return redirect()->route('bienestar.panel.casos.show', [$caso, 'abrir' => 'm-derivar']);
    }

    public function guardarDerivacion(StoreDerivacionRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $this->casos->exigirAbierto(CasoBienestar::findOrFail($request->integer('id_caso')));

        $derivacion = DB::transaction(function () use ($request, $actor) {
            $derivacion = Derivacion::create([
                'id_caso' => $request->integer('id_caso'),
                'id_especialista_destino' => $request->filled('id_especialista_destino') ? $request->integer('id_especialista_destino') : null,
                'entidad_externa' => $request->input('entidad_externa'),
                'motivo' => $request->input('motivo'),
                'estado' => 'Pendiente',
                'derivado_en' => now(),
                'consentimiento_estudiante' => true,
                'fecha_consentimiento' => now(),
            ]);

            SeguimientoDerivacion::create([
                'id_derivacion' => $derivacion->id_derivacion,
                'fecha' => now()->toDateString(),
                'descripcion' => 'Emisión de derivación con consentimiento informado del estudiante',
                'registrado_por_usuario' => $actor->idUsuario,
            ]);

            return $derivacion;
        });

        return redirect()
            ->route('bienestar.panel.imprimir-derivacion', $derivacion)
            ->with('status', 'Derivación registrada satisfactoriamente.');
    }

    public function imprimirDerivacion(Derivacion $derivacion): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        if (! $derivacion->consentimiento_estudiante) {
            abort(403, 'No es posible generar la ficha de derivación: no cuenta con el consentimiento firmado del estudiante.');
        }

        $derivacion->load(['caso.estudiante.persona', 'especialistaDestino.especialidad', 'seguimientos']);

        return view('modules.bienestar.panel.imprimir_derivacion', [
            'derivacion' => $derivacion,
        ]);
    }

    public function registrarResultado(RegistrarResultadoRequest $request, Derivacion $derivacion): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        DB::transaction(function () use ($request, $derivacion, $actor) {
            $derivacion->update([
                'resultado_especialista' => $request->input('resultado_especialista'),
                'estado' => $request->input('estado_derivacion'),
            ]);

            SeguimientoDerivacion::create([
                'id_derivacion' => $derivacion->id_derivacion,
                'fecha' => now()->toDateString(),
                'descripcion' => 'Registro de contrarreferencia / informe de especialista',
                'registrado_por_usuario' => $actor->idUsuario,
            ]);
        });

        // Los casos nacidos de una solicitud no tienen alerta asociada
        $alerta = $derivacion->caso->alertas()->first();

        return ($alerta
            ? redirect()->route('bienestar.panel.atender', $alerta)
            : redirect()->route('bienestar.panel.casos.show', $derivacion->id_caso))
            ->with('status', 'Informe del especialista registrado.');
    }

    public function createAlertaManual(): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        return redirect()->route('bienestar.panel.index', ['abrir' => 'm-alerta']);
    }

    public function storeAlertaManual(StoreAlertaManualRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $estudiante = Estudiante::where('codigo_universitario', $request->codigo_estudiante)->firstOrFail();

        AlertaRiesgo::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'periodo' => FiltrosConsulta::periodoActivo(),
            'curso' => $request->input('curso', 'GLOBAL') ?: 'GLOBAL',
            'regla' => 'manual_bienestar',
            'origen' => 'manual_bienestar',
            'prioridad' => $request->prioridad,
            'estado' => 'remitida_bienestar',
            'motivo_reporte' => $request->motivo_reporte,
            'categoria_motivo' => $request->input('categoria_motivo'),
            'descripcion_reporte' => $request->descripcion_reporte,
            'fecha_deteccion' => now(),
            'atendido_por' => $actor->idUsuario,
        ]);

        return redirect()
            ->route('bienestar.panel.index')
            ->with('status', 'Alerta manual de bienestar registrada correctamente.');
    }

    public function solicitudes(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $filtros = FiltrosConsulta::texto($request);

        // Bienestar solo ve lo que Secretaría le remitió (mismo criterio que SolicitudApoyoPolicy)
        $query = SolicitudApoyo::query()
            ->whereRaw("lower(estado) in ('remitida', 'aprobada', 'rechazada')")
            ->with(['estudiante.persona', 'beneficio', 'tipoSolicitud'])
            ->orderBy('solicitada_en', 'desc');

        if (! empty($filtros['estado'])) {
            $query->whereRaw('lower(estado) = ?', [strtolower($filtros['estado'])]);
        }

        if (! empty($filtros['id_beneficio'])) {
            $query->where('id_beneficio', (int) $filtros['id_beneficio']);
        }

        $solicitudes = $query->paginate(15)->withQueryString();
        $beneficios = Beneficio::all();

        return view('modules.bienestar.panel.solicitudes.index', [
            'solicitudes' => $solicitudes,
            'beneficios' => $beneficios,
            'filtros' => $filtros,
        ]);
    }

    /** Expediente de la solicitud para dictaminar: documentos y revisión de Secretaría. */
    public function mostrarSolicitud(SolicitudApoyo $solicitud): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);
        abort_unless(in_array($solicitud->estado, ['remitida', 'aprobada', 'rechazada'], true), 404);

        $solicitud->load(['estudiante.persona', 'tipoSolicitud', 'historial', 'documentosRequisitos.requisito', 'documentosRequisitos.archivo']);

        return view('modules.bienestar.panel.solicitudes.show', ['solicitud' => $solicitud]);
    }

    public function descargarDocumento(SolicitudRequisitoDocumento $documento): StreamedResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);
        $solicitud = SolicitudApoyo::findOrFail($documento->id_solicitud);
        $archivo = $documento->archivo;
        abort_unless(in_array($solicitud->estado, ['remitida', 'aprobada', 'rechazada'], true)
            && $archivo && Storage::disk('local')->exists($archivo->ruta_almacenamiento), 404);

        return Storage::disk('local')->download($archivo->ruta_almacenamiento, basename($archivo->nombre_original));
    }

    public function evaluarSolicitud(EvaluarSolicitudApoyoRequest $request, SolicitudApoyo $solicitud, SolicitudTramiteService $tramite): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $nuevoEstado = $request->input('estado');
        $tramite->dictaminarSolicitud($solicitud, $nuevoEstado, $request->input('observacion'), $actor->idUsuario, $this->casos);

        return redirect()
            ->route('bienestar.panel.solicitudes.index')
            ->with('status', "La solicitud #{$solicitud->id_solicitud} ha sido {$nuevoEstado} correctamente.");
    }
}
