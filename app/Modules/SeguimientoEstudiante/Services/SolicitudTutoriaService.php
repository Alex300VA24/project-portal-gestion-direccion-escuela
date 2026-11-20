<?php

namespace App\Modules\SeguimientoEstudiante\Services;

use App\Models\User;
use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEstudiante\Services\Tutoria\AvisoTutoriaService;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

final class SolicitudTutoriaService
{
    private const ATENCIONES = 'seguimiento_estudiante.atencion';

    private const CAMBIOS = 'seguimiento_estudiante.solicitud_cambio_cita';

    private const TABLE = 'seguimiento_estudiante.solicitud_estudiante';

    public function __construct(
        private readonly ExpedientePolicy $policy,
        private readonly AvisoTutoriaService $avisos,
    ) {}

    public static function bloquearAgenda(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(20261120, 11)');
    }

    public function periodosActivos(): Builder
    {
        $hoy = now('America/Lima')->toDateString();

        return DB::table('core.periodo_academico')
            ->where('estado', 'Activo')
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy);
    }

    public function visibles(User $user, int $persona, Estudiante $estudiante): Builder
    {
        $cita = DB::table(self::ATENCIONES.' as atencion')
            ->join('seguimiento_estudiante.tutor_asignacion as responsable', 'responsable.id_asignacion', '=', 'atencion.id_asignacion')
            ->join('core.docente as tutor_cita', 'tutor_cita.id_docente', '=', 'responsable.id_docente')
            ->leftJoin('seguimiento_estudiante.atencion_compromiso as compromiso', function ($join): void {
                $join->on('compromiso.id_atencion', '=', 'atencion.id_atencion')->where('compromiso.origen_sesion', true);
            })
            ->whereColumn('atencion.id_solicitud', 'solicitud.id_solicitud')
            ->whereColumn('responsable.id_estudiante', 'solicitud.id_estudiante')
            ->whereColumn('responsable.id_periodo', 'solicitud.id_periodo')
            ->where('solicitud.estado', '<>', 'Pendiente')
            ->whereNotNull('atencion.duracion_minutos')
            ->orderByDesc('atencion.id_atencion')
            ->limit(1)
            ->select('atencion.fecha_hora', 'atencion.modalidad', 'atencion.duracion_minutos', 'atencion.lugar', 'tutor_cita.id_persona as id_persona_tutor',
                'atencion.actividad', 'atencion.acuerdos', 'atencion.observaciones', 'compromiso.id_compromiso',
                'compromiso.cumplido');

        $cambio = DB::table(self::CAMBIOS.' as peticion')
            ->whereColumn('peticion.id_solicitud', 'solicitud.id_solicitud')
            ->select('tipo', 'motivo', 'decision', 'respuesta')
            ->orderByDesc('id_cambio')
            ->limit(1);

        $solicitudes = DB::table(self::TABLE.' as solicitud')
            ->where('solicitud.id_estudiante', $estudiante->getKey())
            ->where('solicitud.tipo', 'Tutoria')
            ->leftJoin('core.periodo_academico as periodo', 'periodo.id_periodo', '=', 'solicitud.id_periodo')
            ->leftJoinLateral($cita, 'cita')
            ->leftJoinLateral($cambio, 'cambio')
            ->select(
                'solicitud.id_solicitud',
                'solicitud.descripcion',
                'solicitud.estado',
                'solicitud.version',
                'solicitud.solicitada_en',
                'periodo.codigo as periodo_codigo',
                'cita.fecha_hora',
                'cita.modalidad',
                'cita.duracion_minutos',
                'cita.lugar',
                'cita.id_persona_tutor',
                'cita.acuerdos',
                'cita.id_compromiso',
                'cita.cumplido',
                'cambio.tipo as cambio_tipo',
                'cambio.motivo as cambio_motivo',
                'cambio.decision as cambio_decision',
                'cambio.respuesta as cambio_respuesta',
            );

        if ((int) $estudiante->id_persona !== $persona) {
            abort_unless($user->can('seguimiento-estudiante.tutoria.view'), 404);
            $solicitudes->whereExists($this->policy->asignacionesVigentes($persona)
                ->whereColumn('asignacion.id_estudiante', 'solicitud.id_estudiante')
                ->whereColumn('asignacion.id_periodo', 'solicitud.id_periodo'));

            if ($user->can('seguimiento-estudiante.tutoria.programar')) {
                $solicitudes->selectRaw(
                    'CASE WHEN cita.id_persona_tutor = ? THEN cita.actividad END AS actividad, '
                    .'CASE WHEN cita.id_persona_tutor = ? THEN cita.observaciones END AS observaciones',
                    [$persona, $persona],
                );
            }
        }

        return $solicitudes;
    }

    public function registrar(User $user, int $persona, int $estudiante, array $datos): void
    {
        DB::transaction(function () use ($user, $persona, $estudiante, $datos): void {
            $perfil = Estudiante::query()->where('id_persona', $persona)->lockForUpdate()->findOrFail($estudiante);
            abort_unless($this->policy->puedeSolicitar($user, $persona, $perfil), 403);

            $claveEnvio = $datos['clave_envio'];
            $anterior = DB::table(self::TABLE)
                ->where('id_estudiante', $estudiante)
                ->where('clave_envio', $claveEnvio)
                ->first(['tipo', 'id_periodo', 'descripcion']);

            if ($anterior !== null) {
                if ($anterior->tipo !== 'Tutoria' || (int) $anterior->id_periodo !== (int) $datos['id_periodo']
                    || $anterior->descripcion !== $datos['motivo']) {
                    throw ValidationException::withMessages(['motivo' => 'El formulario ya fue utilizado. Abre una nueva solicitud.']);
                }

                return;
            }

            if ($this->periodosActivos()->where('id_periodo', $datos['id_periodo'])->sharedLock()->first(['id_periodo']) === null) {
                throw ValidationException::withMessages(['id_periodo' => 'Selecciona un período activo.']);
            }

            DB::table(self::TABLE)->insert([
                'id_estudiante' => $estudiante,
                'id_periodo' => $datos['id_periodo'],
                'tipo' => 'Tutoria',
                'clave_envio' => $claveEnvio,
                'asunto' => 'Solicitud de tutoría',
                'descripcion' => $datos['motivo'],
                'estado' => 'Pendiente',
            ]);
            $this->avisos->avisarTutores($estudiante, (int) $datos['id_periodo'], 'Tienes una nueva solicitud de tutoría.');
        });
    }

    public function solicitarCambio(User $user, int $persona, int $estudiante, int $solicitudId, array $datos): void
    {
        DB::transaction(function () use ($user, $persona, $estudiante, $solicitudId, $datos): void {
            self::bloquearAgenda();
            $perfil = Estudiante::query()->where('id_persona', $persona)->sharedLock()->findOrFail($estudiante);
            abort_unless($this->policy->puedeSolicitar($user, $persona, $perfil), 403);
            $solicitud = $this->solicitudBloqueada($estudiante, $solicitudId);
            $this->comprobarVersion($solicitud, $datos);

            $fecha = $solicitud->estado === 'Programada'
                ? DB::table(self::ATENCIONES)->where('id_solicitud', $solicitudId)
                    ->whereNotNull('duracion_minutos')->orderByDesc('id_atencion')->value('fecha_hora')
                : null;
            if (! in_array($solicitud->estado, ['Pendiente', 'Programada'], true)
                || ($fecha !== null && Carbon::parse($fecha)->lte(now()))) {
                throw ValidationException::withMessages(['tipo_cambio' => 'La petición debe realizarse antes de la atención.']);
            }

            if (DB::table(self::CAMBIOS)->where('id_solicitud', $solicitudId)->whereNull('decision')->exists()) {
                throw ValidationException::withMessages(['tipo_cambio' => 'Ya hay una petición pendiente de revisión.']);
            }

            DB::table(self::CAMBIOS)->insert([
                'id_solicitud' => $solicitudId,
                'solicitado_por' => $user->getKey(),
                'tipo' => $datos['tipo_cambio'],
                'motivo' => $datos['motivo_cambio'],
            ]);
            DB::table(self::TABLE)->where('id_solicitud', $solicitudId)->increment('version');
            $this->avisos->avisarTutores($estudiante, (int) $solicitud->id_periodo, 'Tienes una petición de cambio de cita.');
        });
    }

    public function resolverCita(User $user, int $persona, int $estudiante, int $solicitudId, string $accion, array $datos): void
    {
        DB::transaction(function () use ($user, $persona, $estudiante, $solicitudId, $accion, $datos): void {
            // Las mutaciones de agenda comparten el mismo bloqueo y orden de adquisición.
            self::bloquearAgenda();
            [$solicitud, $perfil, $asignacion] = $this->contextoTutor($persona, $estudiante, $solicitudId);

            $this->comprobarVersion($solicitud, $datos);

            $estados = match ($accion) {
                'programar' => ['Pendiente'],
                'reprogramar', 'registrar' => ['Programada'],
                'cancelar', 'mantener' => ['Pendiente', 'Programada'],
                'cumplir' => ['Atendida'],
                'corregir' => ['Atendida', 'Inasistencia', 'No realizada'],
            };

            if (! in_array($solicitud->estado, $estados, true)) {
                throw ValidationException::withMessages(['fecha_hora' => 'El estado de la solicitud no permite esta acción.']);
            }

            $cita = null;
            if ($solicitud->estado !== 'Pendiente') {
                $cita = DB::table(self::ATENCIONES)
                    ->where('id_solicitud', $solicitudId)
                    ->where('id_asignacion', $asignacion->id_asignacion)
                    ->whereNotNull('duracion_minutos')
                    ->orderByDesc('id_atencion')
                    ->lockForUpdate()
                    ->first();
                abort_if($cita === null, 404);
            }

            $estado = match ($accion) {
                'programar', 'reprogramar' => $this->guardarCita($user, $solicitud, $asignacion, $cita, $datos),
                'mantener' => $solicitud->estado,
                'cumplir' => $this->cumplirCompromiso($user, $cita),
                'cancelar' => $this->cancelarCita($user, $solicitud, $cita, $datos['nota']),
                'registrar', 'corregir' => $this->guardarSesion($user, $persona, $perfil, $solicitud, $cita, $datos),
            };

            $this->resolverCambio($user, $solicitudId, $accion, $datos);
            DB::table(self::TABLE)->where('id_solicitud', $solicitudId)
                ->update(['estado' => $estado, 'version' => $solicitud->version + 1]);

            $mensaje = match ($accion) {
                'programar' => 'Se programó tu cita de tutoría.',
                'reprogramar' => 'Se reprogramó tu cita de tutoría.',
                'cancelar' => 'Se canceló tu solicitud de tutoría.',
                'mantener' => 'Tu tutor respondió a tu petición de cambio de cita.',
                'cumplir' => 'Tu tutor registró el cumplimiento de un compromiso.',
                default => 'Se actualizó el resultado de tu atención de tutoría.',
            };
            $this->avisos->avisar($perfil->id_persona, $mensaje);
        });
    }

    private function contextoTutor(int $persona, int $estudiante, int $solicitudId): array
    {
        $solicitud = $this->solicitudBloqueada($estudiante, $solicitudId);
        $perfil = Estudiante::query()->where('estado', 'Activo')->sharedLock()->findOrFail($estudiante);
        abort_if((int) $perfil->id_persona === $persona, 404);

        $asignacion = $this->policy->asignacionesVigentes($persona)
            ->where('asignacion.id_estudiante', $estudiante)
            ->where('asignacion.id_periodo', $solicitud->id_periodo)
            ->select('asignacion.id_asignacion', 'asignacion.id_docente')
            ->sharedLock()
            ->first();
        abort_if($asignacion === null, 404);

        return [$solicitud, $perfil, $asignacion];
    }

    private function solicitudBloqueada(int $estudiante, int $solicitudId): stdClass
    {
        $solicitud = DB::table(self::TABLE)
            ->where('id_solicitud', $solicitudId)
            ->where('id_estudiante', $estudiante)
            ->where('tipo', 'Tutoria')
            ->lockForUpdate()
            ->first();
        abort_if($solicitud === null, 404);

        return $solicitud;
    }

    private function comprobarVersion(stdClass $solicitud, array $datos): void
    {
        if (isset($datos['version']) && (int) $datos['version'] !== (int) $solicitud->version) {
            throw ValidationException::withMessages(['version' => 'La solicitud cambió. Recarga el expediente.']);
        }
    }

    private function resolverCambio(User $user, int $solicitudId, string $accion, array $datos): void
    {
        $consulta = DB::table(self::CAMBIOS)->where('id_solicitud', $solicitudId)->whereNull('decision');
        $cambio = (clone $consulta)->first(['tipo']);
        if ($cambio === null) {
            if ($accion === 'mantener') {
                throw ValidationException::withMessages(['nota' => 'No hay una petición de cambio pendiente.']);
            }

            return;
        }

        if (! isset($datos['version'])) {
            throw ValidationException::withMessages(['version' => 'Recarga el expediente antes de responder a una petición de cambio.']);
        }

        $aprobada = match ($accion) {
            'programar', 'reprogramar' => $cambio->tipo === 'REPROGRAMACION',
            'cancelar' => $cambio->tipo === 'CANCELACION',
            default => false,
        };

        $consulta->update([
            'decision' => $aprobada ? 'Aprobada' : 'Rechazada',
            'respuesta' => match (true) {
                $accion === 'mantener' => $datos['nota'],
                $aprobada => 'Se aplicó el cambio solicitado.',
                default => 'Se actualizó la cita sin aplicar el cambio solicitado.',
            },
            'respondido_por' => $user->getKey(),
            'respondida_en' => now(),
        ]);
    }

    private function guardarCita(User $user, stdClass $solicitud, stdClass $asignacion, ?stdClass $cita, array $datos): string
    {
        $inicio = Carbon::createFromFormat('!Y-m-d\TH:i', $datos['fecha_hora'], 'America/Lima');
        if ($inicio->lte(now('America/Lima'))) {
            throw ValidationException::withMessages(['fecha_hora' => 'Elige una fecha futura acordada.']);
        }

        $fecha = $inicio->toIso8601String();
        $fin = $inicio->copy()->addMinutes((int) $datos['duracion_minutos'])->toIso8601String();
        $ocupado = DB::table(self::ATENCIONES.' as atencion')
            ->join('seguimiento_estudiante.tutor_asignacion as responsable', 'responsable.id_asignacion', '=', 'atencion.id_asignacion')
            ->leftJoin(self::TABLE.' as solicitud', 'solicitud.id_solicitud', '=', 'atencion.id_solicitud')
            ->where(function (Builder $agenda) use ($solicitud, $asignacion): void {
                $agenda->where('responsable.id_estudiante', $solicitud->id_estudiante)
                    ->orWhere('responsable.id_docente', $asignacion->id_docente);
            })
            ->where(function (Builder $vigentes): void {
                $vigentes->whereNull('solicitud.estado')
                    ->orWhereNotIn('solicitud.estado', ['Cancelada', 'Rechazada']);
            })
            ->whereNotExists(DB::table('seguimiento_estudiante.atencion_evento as evento')
                ->whereColumn('evento.id_atencion', 'atencion.id_atencion')->where('evento.tipo', 'cancelacion'))
            ->when($cita !== null, fn (Builder $agenda) => $agenda->where('atencion.id_atencion', '<>', $cita->id_atencion))
            ->where('atencion.fecha_hora', '<', $fin)
            ->whereRaw("atencion.fecha_hora + COALESCE(atencion.duracion_minutos, 30) * INTERVAL '1 minute' > ?", [$fecha])
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages(['fecha_hora' => 'La fecha coincide con otra cita del docente o del estudiante.']);
        }

        $detalle = [
            'modalidad' => $datos['modalidad'],
            'duracion_minutos' => $datos['duracion_minutos'],
            'lugar' => $datos['lugar'],
        ];
        if ($cita === null) {
            $tipo = DB::table('seguimiento_estudiante.tipo_atencion')->where('nombre', 'Tutoría')->value('id_tipo_atencion');
            if ($tipo === null) {
                throw ValidationException::withMessages(['fecha_hora' => 'Solicita a la administración que habilite el tipo de atención Tutoría.']);
            }

            $atencion = DB::table(self::ATENCIONES)->insertGetId($detalle + [
                'id_asignacion' => $asignacion->id_asignacion,
                'id_tipo_atencion' => $tipo,
                'id_solicitud' => $solicitud->id_solicitud,
                'fecha_hora' => $fecha,
            ], 'id_atencion');
        } else {
            $atencion = $cita->id_atencion;
            DB::table(self::ATENCIONES)->where('id_atencion', $atencion)
                ->update($detalle + ['fecha_hora' => $fecha]);
        }

        DB::table('seguimiento_estudiante.atencion_evento')->insert($detalle + [
            'id_atencion' => $atencion,
            'id_usuario' => $user->getKey(),
            'tipo' => $cita === null ? 'programacion' : 'reprogramacion',
            'fecha_programada' => $fecha,
            'motivo' => $datos['nota'],
        ]);

        return 'Programada';
    }

    private function cancelarCita(User $user, stdClass $solicitud, ?stdClass $cita, string $nota): string
    {
        DB::table('seguimiento_estudiante.atencion_evento')->insert([
            'id_atencion' => $cita?->id_atencion,
            'id_solicitud' => $cita === null ? $solicitud->id_solicitud : null,
            'id_usuario' => $user->getKey(),
            'tipo' => 'cancelacion',
            'fecha_programada' => $cita?->fecha_hora,
            'modalidad' => $cita?->modalidad,
            'duracion_minutos' => $cita?->duracion_minutos,
            'lugar' => $cita?->lugar,
            'motivo' => $nota,
        ]);

        return 'Cancelada';
    }

    private function cumplirCompromiso(User $user, stdClass $cita): string
    {
        $actualizado = DB::table('seguimiento_estudiante.atencion_compromiso')
            ->where('id_atencion', $cita->id_atencion)->where('origen_sesion', true)->where('cumplido', false)
            ->update(['cumplido' => true, 'cumplido_en' => now(), 'cumplido_por' => $user->getKey()]);
        if ($actualizado === 0) {
            throw ValidationException::withMessages(['version' => 'La atención ya no tiene un compromiso pendiente.']);
        }

        return 'Atendida';
    }

    private function guardarSesion(User $user, int $persona, Estudiante $perfil, stdClass $solicitud, stdClass $cita, array $datos): string
    {
        if (Carbon::parse($cita->fecha_hora)->gt(now())) {
            throw ValidationException::withMessages(['asistencia' => 'La cita aún no ha ocurrido.']);
        }

        $estado = match ($datos['asistencia']) {
            'ASISTIO' => 'Atendida',
            'NO_ASISTIO' => 'Inasistencia',
            'NO_REALIZADA' => 'No realizada',
        };
        $asistio = $estado === 'Atendida';
        $detalle = [
            'actividad' => $asistio ? $datos['actividad'] : null,
            'acuerdos' => $asistio ? $datos['acuerdos'] : null,
            'observaciones' => $datos['observaciones'] ?? null,
        ];
        $atencion = $cita->id_atencion;
        DB::table(self::ATENCIONES)->where('id_atencion', $atencion)->update($detalle + ['resultado' => $estado]);
        DB::table('seguimiento_estudiante.atencion_sesion_revision')->insert($detalle + [
            'id_atencion' => $atencion,
            'version' => $solicitud->version + 1,
            'id_usuario' => $user->getKey(),
            'estado' => $estado,
            'motivo_correccion' => $datos['motivo_correccion'] ?? null,
        ]);

        DB::table('seguimiento_estudiante.atencion_participante')->upsert([
            ['id_atencion' => $atencion, 'id_persona' => $perfil->id_persona, 'papel' => 'Estudiante', 'asistio' => $estado === 'No realizada' ? null : $asistio],
            ['id_atencion' => $atencion, 'id_persona' => $persona, 'papel' => 'Tutor', 'asistio' => $asistio ? true : null],
        ], ['id_atencion', 'id_persona', 'papel'], ['asistio']);

        $compromiso = DB::table('seguimiento_estudiante.atencion_compromiso')
            ->where('id_atencion', $atencion)->where('origen_sesion', true);
        $anterior = (clone $compromiso)->lockForUpdate()->first(['descripcion', 'cumplido']);
        if ($anterior?->cumplido && $anterior->descripcion !== $detalle['acuerdos']) {
            throw ValidationException::withMessages(['acuerdos' => 'El compromiso ya fue cumplido y debe conservarse.']);
        }

        if (! $asistio) {
            $compromiso->delete();
        } elseif ($anterior !== null) {
            $compromiso->update(['descripcion' => $datos['acuerdos']]);
        } else {
            DB::table('seguimiento_estudiante.atencion_compromiso')->insert([
                'id_atencion' => $atencion,
                'descripcion' => $datos['acuerdos'],
                'origen_sesion' => true,
            ]);
        }

        return $estado;
    }
}
