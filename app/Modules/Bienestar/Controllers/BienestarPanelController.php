<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Models\Beneficio;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\Derivacion;
use App\Modules\Bienestar\Models\Especialista;
use App\Modules\Bienestar\Models\HistorialEstadoCaso;
use App\Modules\Bienestar\Models\SeguimientoDerivacion;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Models\TipoCaso;
use App\Modules\Bienestar\Requests\AtenderAlertaRequest;
use App\Modules\Bienestar\Requests\EvaluarSolicitudApoyoRequest;
use App\Modules\Bienestar\Requests\RegistrarResultadoRequest;
use App\Modules\Bienestar\Requests\StoreAlertaManualRequest;
use App\Modules\Bienestar\Requests\StoreDerivacionRequest;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Shared\Models\Core\Estudiante;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class BienestarPanelController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $query = AlertaRiesgo::query()
            ->with(['estudiante.persona', 'docente.persona', 'caso'])
            ->orderByRaw("CASE WHEN prioridad = 'alta' THEN 1 WHEN prioridad = 'media' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        if ($request->filled('origen')) {
            $query->where('origen', $request->string('origen'));
        }

        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->string('prioridad'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->input('buscar'));
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
            'pendientes' => AlertaRiesgo::where('estado', 'pendiente')->count(),
            'alta_prioridad' => AlertaRiesgo::where('prioridad', 'alta')->whereIn('estado', ['pendiente', 'en_atencion'])->count(),
            'en_atencion' => AlertaRiesgo::where('estado', 'en_atencion')->count(),
            'casos_abiertos' => CasoBienestar::where('estado', '!=', 'Cerrado')->count(),
        ];

        return view('modules.bienestar.panel.index', [
            'alertas' => $alertas,
            'totales' => $totales,
            'filtros' => $request->all(),
        ]);
    }

    public function atender(Request $request, AlertaRiesgo $alerta): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $alerta->load(['estudiante.persona', 'docente.persona', 'caso.historial', 'caso.citas', 'caso.derivaciones']);
        $tiposCaso = TipoCaso::all();

        return view('modules.bienestar.panel.atender', [
            'alerta' => $alerta,
            'tiposCaso' => $tiposCaso,
        ]);
    }

    public function guardarAtencion(AtenderAlertaRequest $request, AlertaRiesgo $alerta): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $accion = $request->input('accion');

        DB::transaction(function () use ($request, $alerta, $actor, $accion) {
            $alerta->atendido_por = $actor->idUsuario;

            if ($accion === 'abrir_caso') {
                $idTipoCaso = (int) $request->input('id_tipo_caso');
                $descripcion = (string) ($request->input('descripcion_caso') ?: 'Caso abierto a partir de alerta '.$alerta->regla);

                $caso = CasoBienestar::create([
                    'id_estudiante' => $alerta->id_estudiante,
                    'id_tipo_caso' => $idTipoCaso,
                    'descripcion' => $descripcion,
                    'estado' => 'En_Atencion',
                    'abierto_en' => now(),
                ]);

                HistorialEstadoCaso::create([
                    'id_caso' => $caso->id_caso,
                    'estado_anterior' => null,
                    'estado_nuevo' => 'En_Atencion',
                    'cambiado_por' => $actor->idUsuario,
                    'cambiado_en' => now(),
                    'observacion' => 'Apertura de caso desde panel de riesgo',
                ]);

                $alerta->id_caso = $caso->id_caso;
                $alerta->estado = 'en_atencion';
                $alerta->save();

            } elseif ($accion === 'programar_cita') {
                $caso = $alerta->caso;
                if (! $caso) {
                    $tipoAcademico = TipoCaso::firstOrCreate(['nombre' => 'Riesgo Academico'], ['descripcion' => 'Casos de riesgo'])->id_tipo_caso;
                    $caso = CasoBienestar::create([
                        'id_estudiante' => $alerta->id_estudiante,
                        'id_tipo_caso' => $tipoAcademico,
                        'descripcion' => 'Caso generado para programación directa de cita de orientación',
                        'estado' => 'En_Atencion',
                        'abierto_en' => now(),
                    ]);
                    $alerta->id_caso = $caso->id_caso;
                }

                $inicio = Carbon::parse($request->input('inicio_cita'));
                $fin = (clone $inicio)->addHour();

                Cita::create([
                    'id_caso' => $caso->id_caso,
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'modalidad' => $request->input('modalidad', 'Presencial'),
                    'estado' => 'Programada',
                    'notas_atencion' => 'Cita de seguimiento y orientación psicopedagógica programada por Bienestar',
                    'id_usuario_atencion' => $actor->idUsuario,
                ]);

                $alerta->estado = 'en_atencion';
                $alerta->save();

            } elseif ($accion === 'descartar') {
                $alerta->estado = 'descartada';
                $alerta->fecha_resolucion = now();
                $detalles = $alerta->detalles ?? [];
                $detalles['motivo_descarte'] = $request->input('motivo_descarte');
                $alerta->detalles = $detalles;
                $alerta->save();
            }
        });

        return redirect()
            ->route('bienestar.panel.index')
            ->with('status', 'Alerta procesada con éxito.');
    }

    public function formularioDerivacion(CasoBienestar $caso): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $caso->load(['estudiante.persona', 'tipoCaso']);
        $especialistas = Especialista::activos()->with('especialidad')->get();

        return view('modules.bienestar.panel.derivar', [
            'caso' => $caso,
            'especialistas' => $especialistas,
        ]);
    }

    public function guardarDerivacion(StoreDerivacionRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());

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

        return redirect()
            ->route('bienestar.panel.atender', $derivacion->caso->alertas()->first() ?? 1)
            ->with('status', 'Informe del especialista registrado.');
    }

    public function createAlertaManual(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        return view('modules.bienestar.panel.crear_alerta_manual');
    }

    public function storeAlertaManual(StoreAlertaManualRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $estudiante = Estudiante::where('codigo_universitario', $request->codigo_estudiante)->firstOrFail();

        AlertaRiesgo::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'periodo' => '2026-I',
            'curso' => $request->input('curso', 'GLOBAL') ?: 'GLOBAL',
            'regla' => 'manual_bienestar',
            'origen' => 'manual_bienestar',
            'prioridad' => $request->prioridad,
            'estado' => 'pendiente',
            'motivo_reporte' => $request->motivo_reporte,
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

        $query = SolicitudApoyo::query()
            ->with(['estudiante.persona', 'beneficio'])
            ->orderBy('solicitada_en', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('id_beneficio')) {
            $query->where('id_beneficio', $request->integer('id_beneficio'));
        }

        $solicitudes = $query->paginate(15)->withQueryString();
        $beneficios = Beneficio::all();

        return view('modules.bienestar.panel.solicitudes.index', [
            'solicitudes' => $solicitudes,
            'beneficios' => $beneficios,
            'filtros' => $request->all(),
        ]);
    }

    public function evaluarSolicitud(EvaluarSolicitudApoyoRequest $request, SolicitudApoyo $solicitud): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        $nuevoEstado = $request->input('estado');

        DB::transaction(function () use ($solicitud, $nuevoEstado) {
            $solicitud->update([
                'estado' => $nuevoEstado,
                'evaluado_por' => null,
                'resuelta_en' => now(),
            ]);

            if ($nuevoEstado === 'Aprobada') {
                $tipoSocio = TipoCaso::firstOrCreate(
                    ['nombre' => 'Apoyo Socioeconomico'],
                    ['descripcion' => 'Evaluacion socioeconomica y beneficios de bienestar']
                );

                CasoBienestar::firstOrCreate(
                    ['id_solicitud_origen' => $solicitud->id_solicitud],
                    [
                        'id_estudiante' => $solicitud->id_estudiante,
                        'id_tipo_caso' => $tipoSocio->id_tipo_caso,
                        'descripcion' => 'Caso abierto tras la aprobación de la solicitud de '.($solicitud->beneficio?->nombre ?? 'beneficio'),
                        'estado' => 'En_Atencion',
                        'abierto_en' => now(),
                    ]
                );
            }
        });

        return redirect()
            ->route('bienestar.panel.solicitudes.index')
            ->with('status', "La solicitud #{$solicitud->id_solicitud} ha sido {$nuevoEstado} correctamente.");
    }
}
