<?php

namespace App\Modules\SeguimientoEstudiante\Jobs;

use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
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

    public function handle(ExpedientePolicy $expedientes): void
    {
        DB::transaction(function () use ($expedientes): void {
            SolicitudTutoriaService::bloquearAgenda();
            $ahora = now('America/Lima');
            $fin = $ahora->copy()->addDays($this->dias + 1)->startOfDay();
            $ultimaCita = DB::table('seguimiento_estudiante.atencion as atencion')
                ->whereColumn('atencion.id_solicitud', 'solicitud.id_solicitud')
                ->whereNotNull('atencion.duracion_minutos')
                ->orderByDesc('atencion.id_atencion')->limit(1)
                ->select('atencion.id_atencion', 'atencion.id_asignacion', 'atencion.fecha_hora');
            $avisos = DB::table('seguimiento_estudiante.solicitud_estudiante as solicitud')
                ->join('core.estudiante as estudiante', 'estudiante.id_estudiante', '=', 'solicitud.id_estudiante')
                ->join('seguimiento_estudiante.identidad_cuenta as cuenta', 'cuenta.id_persona', '=', 'estudiante.id_persona')
                ->joinLateral($ultimaCita, 'cita')
                ->where('solicitud.tipo', 'Tutoria')->where('solicitud.estado', 'Programada')
                ->where('estudiante.estado', 'Activo')
                ->where('cita.fecha_hora', '>=', $ahora->toIso8601String())
                ->where('cita.fecha_hora', '<', $fin->toIso8601String())
                ->whereExists($expedientes->tutoriasVigentes()
                    ->whereColumn('asignacion.id_asignacion', 'cita.id_asignacion')
                    ->whereColumn('asignacion.id_estudiante', 'solicitud.id_estudiante')
                    ->whereColumn('asignacion.id_periodo', 'solicitud.id_periodo'))
                ->whereNotExists(DB::table('seguimiento_estudiante.atencion_evento as evento')
                    ->whereColumn('evento.id_atencion', 'cita.id_atencion')->where('evento.tipo', 'cancelacion'))
                ->select('cuenta.id_usuario', 'estudiante.id_persona')
                ->selectRaw('? AS mensaje', ['Tienes una cita de Tutoría próxima. Consulta la fecha y el ambiente en tu expediente.'])
                ->selectRaw("'cita|' || cita.id_atencion || '|' || EXTRACT(EPOCH FROM cita.fecha_hora) AS clave_evento");

            DB::table('seguimiento_estudiante.tutoria_aviso')
                ->insertOrIgnoreUsing(['id_usuario', 'id_persona', 'mensaje', 'clave_evento'], $avisos);
        });
    }
}
