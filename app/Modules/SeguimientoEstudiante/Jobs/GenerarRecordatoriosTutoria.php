<?php

namespace App\Modules\SeguimientoEstudiante\Jobs;

use App\Modules\SeguimientoEstudiante\Services\SolicitudTutoriaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class GenerarRecordatoriosTutoria implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public array $backoff = [10, 30];

    public function __construct(public readonly int $dias) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            SolicitudTutoriaService::bloquearAgenda();
            $ahora = now('America/Lima');
            $fin = $ahora->copy()->addDays($this->dias + 1)->startOfDay();
            DB::affectingStatement(<<<'SQL'
                INSERT INTO seguimiento_estudiante.tutoria_aviso (id_usuario, id_persona, mensaje, clave_evento)
                SELECT cuenta.id_usuario, estudiante.id_persona,
                       'Tienes una cita de Tutoría próxima. Consulta la fecha y el ambiente en tu expediente.',
                       'cita|' || cita.id_atencion || '|' || EXTRACT(EPOCH FROM cita.fecha_hora)
                  FROM seguimiento_estudiante.solicitud_estudiante AS solicitud
                  JOIN core.estudiante AS estudiante ON estudiante.id_estudiante = solicitud.id_estudiante
                  JOIN seguimiento_estudiante.identidad_cuenta AS cuenta ON cuenta.id_persona = estudiante.id_persona
                  JOIN LATERAL (
                      SELECT atencion.id_atencion, atencion.id_asignacion, atencion.fecha_hora
                        FROM seguimiento_estudiante.atencion AS atencion
                       WHERE atencion.id_solicitud = solicitud.id_solicitud AND atencion.duracion_minutos IS NOT NULL
                       ORDER BY atencion.id_atencion DESC
                       LIMIT 1
                  ) AS cita ON true
                  JOIN seguimiento_estudiante.tutor_asignacion AS asignacion ON asignacion.id_asignacion = cita.id_asignacion
                  JOIN core.docente AS docente ON docente.id_docente = asignacion.id_docente
                  JOIN core.periodo_academico AS periodo ON periodo.id_periodo = asignacion.id_periodo
                 WHERE solicitud.tipo = 'Tutoria' AND solicitud.estado = 'Programada'
                   AND estudiante.estado = 'Activo' AND docente.estado = 'Activo'
                   AND cita.fecha_hora >= ? AND cita.fecha_hora < ?
                   AND asignacion.tipo = 'Tutoria' AND asignacion.estado = 'Activa'
                   AND asignacion.id_estudiante = solicitud.id_estudiante AND asignacion.id_periodo = solicitud.id_periodo
                   AND periodo.estado = 'Activo' AND ? BETWEEN periodo.fecha_inicio AND periodo.fecha_fin
                   AND COALESCE(asignacion.fecha_inicio, asignacion.fecha_asignacion) <= ?
                   AND (asignacion.fecha_fin IS NULL OR asignacion.fecha_fin >= ?)
                   AND NOT EXISTS (
                       SELECT 1 FROM seguimiento_estudiante.atencion_evento AS evento
                        WHERE evento.id_atencion = cita.id_atencion AND evento.tipo = 'cancelacion'
                   )
                 ON CONFLICT (id_usuario, id_persona, clave_evento) DO NOTHING
                SQL, [$ahora->toIso8601String(), $fin->toIso8601String(), $ahora->toDateString(), $ahora->toDateString(), $ahora->toDateString()]);
        });
    }
}
