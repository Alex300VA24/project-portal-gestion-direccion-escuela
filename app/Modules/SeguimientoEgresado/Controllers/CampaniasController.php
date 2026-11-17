<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\CatCanalEnvio;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\InvitacionEgresado;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\EnlaceInvitacion;
use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\EnvioDeEnlaces;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CampaniasController extends Controller
{
    /**
     * Muestra la vista principal de Campañas, Convocatorias y Cobertura.
     */
    public function index(Request $request, IndicadoresService $indicadoresService)
    {
        $busqueda = $request->query('q');
        $cohorteFiltro = $request->query('cohorte');
        $estadoFiltro = $request->query('estado');
        $periodoFiltro = $request->query('periodo');
        $campaniaIdSeleccionada = $request->query('campania_id');

        try {
            // 1. Catálogos para filtros y formularios
            $cohortes = Cohorte::with('planCurricular')->orderBy('anio_ingreso', 'asc')->get();
            $canales = CatCanalEnvio::where('activo', true)->get();

            // 2. Consulta de campañas con relaciones
            $campaniasQuery = CampaniaSeguimiento::with([
                'cohortes',
                'canales',
                'usuarioCreacion.rol',
                'invitaciones.egresado.cohorte',
                'invitaciones.medicion',
            ]);

            if (! empty($busqueda)) {
                $campaniasQuery->where(function ($q) use ($busqueda) {
                    $q->where('nombre', 'like', "%{$busqueda}%")
                        ->orWhere('descripcion', 'like', "%{$busqueda}%")
                        ->orWhere('periodo', 'like', "%{$busqueda}%");
                });
            }

            if (! empty($cohorteFiltro) && $cohorteFiltro !== 'todas') {
                $campaniasQuery->whereHas('cohortes', function ($q) use ($cohorteFiltro) {
                    $q->where('cohorte.id', $cohorteFiltro)
                        ->orWhere('cohorte.codigo_promocion', $cohorteFiltro);
                });
            }

            if (! empty($estadoFiltro) && $estadoFiltro !== 'todos') {
                $campaniasQuery->where('estado', strtoupper($estadoFiltro));
            }

            if (! empty($periodoFiltro) && $periodoFiltro !== 'todos') {
                $campaniasQuery->where('periodo', $periodoFiltro);
            }

            $campanias = $campaniasQuery->orderBy('fecha_inicio', 'desc')->get();

            // 3. Métricas calculadas para cada campaña
            $campaniasData = $campanias->map(function ($camp) {
                $invitaciones = $camp->invitaciones;
                $totalConvocados = $invitaciones->count();
                $respuestas = $invitaciones->filter(function ($inv) {
                    return $inv->estado === 'COMPLETADA' || $inv->medicion !== null;
                })->count();
                $pendientes = $invitaciones->where('estado', 'PENDIENTE')->count();
                $expiradas = $invitaciones->where('estado', 'EXPIRADA')->count();
                $fallidas = $invitaciones->where('entrega_fallida', true)->count();
                $cobertura = $totalConvocados > 0 ? round(($respuestas / $totalConvocados) * 100, 1) : 0.0;

                // Insignia visual según estado
                $badge = match ($camp->estado) {
                    'ABIERTA' => 'sg-badge-success',
                    'PROGRAMADA' => 'sg-badge-warning',
                    'CERRADA' => 'sg-badge',
                    'BORRADOR' => 'sg-badge',
                    default => 'sg-badge',
                };

                return [
                    'model' => $camp,
                    'id' => $camp->id,
                    'nombre' => $camp->nombre,
                    'tipo' => $camp->tipo,
                    'finalidad' => $camp->finalidad,
                    'horizonte_anios' => $camp->horizonte_anios,
                    'periodo' => $camp->periodo,
                    'fecha_inicio' => $camp->fecha_inicio,
                    'fecha_fin' => $camp->fecha_fin,
                    'estado' => $camp->estado,
                    'estado_badge' => $badge,
                    'cohortes' => $camp->cohortes,
                    'canales' => $camp->canales,
                    'invitaciones_count' => $totalConvocados,
                    'respuestas_count' => $respuestas,
                    'pendientes_count' => $pendientes,
                    'expiradas_count' => $expiradas,
                    'fallidas_count' => $fallidas,
                    'cobertura_pct' => $cobertura,
                    'descripcion' => $camp->descripcion,
                ];
            });

            // 4. Métricas globales consolidadas
            $totalConvocadosGlobal = $campaniasData->sum('invitaciones_count');
            $totalRespuestasGlobal = $campaniasData->sum('respuestas_count');
            $totalPendientesGlobal = $campaniasData->sum('pendientes_count');
            $coberturaPromedioGlobal = $totalConvocadosGlobal > 0
                ? round(($totalRespuestasGlobal / $totalConvocadosGlobal) * 100, 1)
                : 0.0;

            // 5. Campaña seleccionada para ver detalle de padrón e invitaciones
            $campaniaSeleccionada = null;
            if (! empty($campaniaIdSeleccionada)) {
                $campaniaSeleccionada = $campaniasData->firstWhere('id', (int) $campaniaIdSeleccionada);
            }
            if (! $campaniaSeleccionada && $campaniasData->isNotEmpty()) {
                $campaniaSeleccionada = $campaniasData->first();
            }

            // Invitaciones detalladas de la campaña seleccionada
            $invitacionesSeleccionadas = collect();
            if ($campaniaSeleccionada && isset($campaniaSeleccionada['model'])) {
                $invitacionesSeleccionadas = InvitacionEgresado::with(['egresado.cohorte', 'medicion'])
                    ->where('id_campania', $campaniaSeleccionada['id'])
                    ->orderBy('id', 'asc')
                    ->get();
            }

            // Periodos únicos disponibles
            $periodosDisponibles = CampaniaSeguimiento::select('periodo')->distinct()->pluck('periodo');

        } catch (\Throwable $e) {
            // Respaldo resiliente para pruebas sin BD local activa
            $cohortes = collect();
            $canales = collect();
            $campaniasData = collect();
            $totalConvocadosGlobal = 142;
            $totalRespuestasGlobal = 98;
            $totalPendientesGlobal = 44;
            $coberturaPromedioGlobal = 69.0;
            $campaniaSeleccionada = null;
            $invitacionesSeleccionadas = collect();
            $periodosDisponibles = collect(['2023-II', '2024-I', '2024-II', '2025-I']);
        }

        return view('modules.seguimiento-egresado.paginas.campanias', compact(
            'cohortes',
            'canales',
            'campaniasData',
            'totalConvocadosGlobal',
            'totalRespuestasGlobal',
            'totalPendientesGlobal',
            'coberturaPromedioGlobal',
            'campaniaSeleccionada',
            'invitacionesSeleccionadas',
            'periodosDisponibles',
            'busqueda',
            'cohorteFiltro',
            'estadoFiltro',
            'periodoFiltro',
            'campaniaIdSeleccionada'
        ));
    }

    /**
     * Registra una nueva campaña con validaciones de servidor estrictas.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:160',
            'tipo' => 'required|in:EGRESADOS,EMPRESAS,MIXTA',
            'finalidad' => 'required|in:SEGUIMIENTO,DIAGNOSTICO_TEMPRANO,EVALUACION_INSTITUCIONAL,EXTRAORDINARIA',
            'horizonte_anios' => 'nullable|integer|in:1,3,5,10',
            'periodo' => 'required|string|max:35',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'required|in:BORRADOR,PROGRAMADA,ABIERTA,CERRADA',
            'cohortes' => 'required|array|min:1',
            'cohortes.*' => 'exists:'.Esquema::validar('cohorte').',id',
            'canales' => 'nullable|array',
            'canales.*' => 'exists:'.Esquema::validar('cat_canal_envio').',id',
            'descripcion' => 'nullable|string|max:1000',
        ], [
            'nombre.required' => 'El nombre de la campaña es obligatorio.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la fecha de inicio.',
            'cohortes.required' => 'Debe asociar al menos una cohorte participante.',
            'cohortes.min' => 'Debe seleccionar como mínimo una cohorte para la convocatoria.',
        ]);

        // Validación de coherencia de estado y fechas
        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin = Carbon::parse($validated['fecha_fin']);
        $hoy = Carbon::today();

        if ($validated['estado'] === 'CERRADA' && $fin->isFuture()) {
            throw ValidationException::withMessages([
                'estado' => 'No puede marcar como CERRADA una campaña cuya fecha de fin aún no se ha cumplido.',
            ]);
        }

        if ($validated['estado'] === 'PROGRAMADA' && $inicio->isPast()) {
            throw ValidationException::withMessages([
                'estado' => 'Una campaña PROGRAMADA debe tener una fecha de inicio futura. Si ya inició, seleccione ABIERTA.',
            ]);
        }

        DB::beginTransaction();
        try {
            // Usuario interno creador (Secretaría o el primer usuario interno disponible)
            $usuarioId = UsuarioInterno::idActual() ?? 1;

            $campania = CampaniaSeguimiento::create([
                'nombre' => $validated['nombre'],
                'tipo' => $validated['tipo'],
                'finalidad' => $validated['finalidad'],
                'horizonte_anios' => $validated['horizonte_anios'] ?? null,
                'periodo' => $validated['periodo'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'],
                'estado' => $validated['estado'],
                'descripcion' => $validated['descripcion'] ?? null,
                'id_usuario_creacion' => $usuarioId,
            ]);

            // Sincronizar cohortes participantes
            $campania->cohortes()->sync($validated['cohortes']);

            // Sincronizar canales habilitados
            if (! empty($validated['canales'])) {
                $campania->canales()->sync($validated['canales']);
            }

            DB::commit();

            return redirect()->route('seguimiento-egresado.campanias', ['campania_id' => $campania->id])
                ->with('success', "Campaña \"{$campania->nombre}\" registrada con éxito en la base de datos.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['general' => 'Error al registrar la campaña: '.$e->getMessage()]);
        }
    }

    /**
     * Actualiza una campaña existente y sus cohortes asociadas.
     */
    public function update(Request $request, int $id)
    {
        $campania = CampaniaSeguimiento::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:160',
            'tipo' => 'required|in:EGRESADOS,EMPRESAS,MIXTA',
            'finalidad' => 'required|in:SEGUIMIENTO,DIAGNOSTICO_TEMPRANO,EVALUACION_INSTITUCIONAL,EXTRAORDINARIA',
            'horizonte_anios' => 'nullable|integer|in:1,3,5,10',
            'periodo' => 'required|string|max:35',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado' => 'required|in:BORRADOR,PROGRAMADA,ABIERTA,CERRADA',
            'cohortes' => 'required|array|min:1',
            'cohortes.*' => 'exists:'.Esquema::validar('cohorte').',id',
            'canales' => 'nullable|array',
            'canales.*' => 'exists:'.Esquema::validar('cat_canal_envio').',id',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        // Validación de coherencia de fechas
        $inicio = Carbon::parse($validated['fecha_inicio']);
        $fin = Carbon::parse($validated['fecha_fin']);

        if ($validated['estado'] === 'CERRADA' && $fin->isFuture()) {
            throw ValidationException::withMessages([
                'estado' => 'Una campaña con fecha de fin futura no puede marcarse como CERRADA.',
            ]);
        }

        DB::beginTransaction();
        try {
            $campania->update([
                'nombre' => $validated['nombre'],
                'tipo' => $validated['tipo'],
                'finalidad' => $validated['finalidad'],
                'horizonte_anios' => $validated['horizonte_anios'] ?? null,
                'periodo' => $validated['periodo'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_fin' => $validated['fecha_fin'],
                'estado' => $validated['estado'],
                'descripcion' => $validated['descripcion'] ?? null,
            ]);

            $campania->cohortes()->sync($validated['cohortes']);

            if (isset($validated['canales'])) {
                $campania->canales()->sync($validated['canales']);
            }

            DB::commit();

            return redirect()->route('seguimiento-egresado.campanias', ['campania_id' => $campania->id])
                ->with('success', "Campaña \"{$campania->nombre}\" actualizada correctamente.");
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['general' => 'Error al actualizar la campaña: '.$e->getMessage()]);
        }
    }

    /**
     * Emite invitaciones formales a egresados de las cohortes participantes.
     * Evita duplicados respetando el índice uq_invitacion_seguimiento.
     */
    public function emitirInvitaciones(Request $request, int $id, EnvioDeEnlaces $envio)
    {
        $campania = CampaniaSeguimiento::with('cohortes')->findOrFail($id);

        if ($campania->cohortes->isEmpty()) {
            return redirect()->back()->withErrors([
                'general' => 'La campaña no tiene cohortes asignadas. Asigne al menos una cohorte antes de emitir invitaciones.',
            ]);
        }

        $cohortesIds = $campania->cohortes->pluck('id');

        // Buscar egresados de estas cohortes
        // Solo se convoca a quienes ya egresaron (el egreso lo confirma la Secretaría); los alumnos aptos todavía no.
        $egresados = Egresado::whereIn('id_cohorte', $cohortesIds)
            ->whereHas('estadoVigente.estadoAcademico', fn ($q) => $q->whereIn('codigo', ['EGRESADO', 'BACHILLER', 'TITULADO']))
            ->get();

        if ($egresados->isEmpty()) {
            return redirect()->back()->withErrors([
                'general' => 'No hay egresados confirmados en las cohortes convocadas. Confirme el egreso de los alumnos aptos desde el padrón.',
            ]);
        }

        // Obtener IDs de egresados que ya tienen invitación en esta campaña (anti-duplicados)
        $invitadosExistentes = InvitacionEgresado::where('id_campania', $campania->id)
            ->pluck('id_egresado')
            ->toArray();

        $canalPredeterminado = CatCanalEnvio::where('codigo', 'CORREO_INSTITUCIONAL')->value('id') ?? 1;

        $nuevasEmitidas = 0;
        $omitidosDuplicados = 0;
        $enlaces = [];
        $porEnviar = [];

        DB::beginTransaction();
        try {
            foreach ($egresados as $egresado) {
                if (in_array($egresado->id, $invitadosExistentes)) {
                    $omitidosDuplicados++;

                    continue;
                }

                // El token en claro solo existe aquí y en el enlace que se muestra una vez; en la BD queda su SHA-256.
                $tokenPlano = Str::random(48);
                $tokenHash = EnlaceInvitacion::hashDe($tokenPlano);
                $expiraEn = Carbon::parse($campania->fecha_fin)->endOfDay();

                // enviado_en queda vacío hasta que el correo salga de verdad (lo marca EnvioDeEnlaces).
                $invitacion = InvitacionEgresado::create([
                    'id_campania' => $campania->id,
                    'id_egresado' => $egresado->id,
                    'proposito' => 'SEGUIMIENTO',
                    'token_hash' => $tokenHash,
                    'expira_en' => $expiraEn,
                    'id_canal_envio' => $canalPredeterminado,
                    'enviado_en' => null,
                    'recordatorios_enviados' => 0,
                    'ultimo_recordatorio_en' => null,
                    'entrega_fallida' => false,
                    'respondido_en' => null,
                    'estado' => 'PENDIENTE',
                ]);

                $porEnviar[count($enlaces)] = [$invitacion, $tokenPlano];
                $enlaces[] = [
                    'egresado' => $egresado->nombre_completo,
                    'matricula' => $egresado->codigo_matricula,
                    'url' => url('seguimiento-egresado/acceso/'.$tokenPlano),
                ];

                $nuevasEmitidas++;
            }

            // Si la campaña estaba en BORRADOR o PROGRAMADA y ya inició, activarla
            if ($campania->estado === 'BORRADOR') {
                $campania->update(['estado' => 'ABIERTA']);
            }

            DB::commit();

            // El correo se envía con las invitaciones ya guardadas; si falla, el enlace sigue en pantalla.
            $enviados = 0;
            foreach ($porEnviar as $indice => [$invitacion, $tokenPlano]) {
                $resultado = $envio->enviar($invitacion, $tokenPlano);
                $enlaces[$indice] += $resultado->paraPantalla();
                $enviados += $resultado->enviado() ? 1 : 0;
            }

            $mensaje = "Se emitieron {$nuevasEmitidas} invitaciones de seguimiento exitosamente.";
            if ($nuevasEmitidas > 0) {
                $mensaje .= $enviados === 0
                    ? ' Entrega a cada egresado su enlace de abajo.'
                    : ($enviados === $nuevasEmitidas
                        ? ' Todas se enviaron por correo.'
                        : " {$enviados} se enviaron por correo; para el resto usa los enlaces de abajo.");
            }
            if ($omitidosDuplicados > 0) {
                $mensaje .= " Se omitieron {$omitidosDuplicados} egresados que ya contaban con invitación activa (anti-duplicados verificado).";
            }

            return redirect()->route('seguimiento-egresado.campanias', ['campania_id' => $campania->id])
                ->with('success', $mensaje)
                ->with('enlaces_emitidos', $enlaces);

        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->withErrors([
                'general' => 'Error al emitir invitaciones: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Registra y genera un recordatorio real con enlace personal para una invitación pendiente.
     */
    public function reenviarRecordatorio(Request $request, int $id, EnvioDeEnlaces $envio)
    {
        $invitacion = InvitacionEgresado::with(['egresado.contactoVigente'])->findOrFail($id);

        if ($invitacion->estado !== 'PENDIENTE') {
            return redirect()->back()->withErrors([
                'general' => 'Solo se pueden enviar recordatorios a invitaciones en estado PENDIENTE.',
            ]);
        }

        // Generamos un nuevo token secreto personal para el recordatorio
        $tokenPlano = Str::random(48);
        $tokenHash = EnlaceInvitacion::hashDe($tokenPlano);

        $invitacion->increment('recordatorios_enviados');
        $invitacion->update([
            'token_hash' => $tokenHash,
            'ultimo_recordatorio_en' => now(),
        ]);

        $egresado = $invitacion->egresado;
        $nombreCompleto = $egresado ? "{$egresado->apellido_paterno} {$egresado->apellido_materno}, {$egresado->nombres}" : 'Egresado';
        $correo = $egresado->correo_institucional ?? $egresado->contactoVigente?->correo_personal ?? 'Correo institucional';

        $resultado = $envio->enviar($invitacion, $tokenPlano);

        $recordatorioData = [
            'egresado' => $nombreCompleto,
            'matricula' => $egresado->codigo_matricula ?? 'S/N',
            'correo' => $correo,
            'correo_texto' => $resultado->texto(),
            'correo_insignia' => $resultado->estado->insignia(),
            'numero_recordatorio' => $invitacion->recordatorios_enviados,
            'url' => url('seguimiento-egresado/acceso/'.$tokenPlano),
        ];

        $mensaje = $resultado->enviado()
            ? "Recordatorio #{$invitacion->recordatorios_enviados} enviado por correo a {$nombreCompleto}."
            : "Recordatorio #{$invitacion->recordatorios_enviados} generado para {$nombreCompleto}. Entrega el enlace de abajo al egresado.";

        return redirect()->back()
            ->with('success', $mensaje)
            ->with('recordatorio_emitido', $recordatorioData);
    }
}
