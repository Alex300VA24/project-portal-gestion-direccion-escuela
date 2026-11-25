<?php

namespace App\Modules\Bienestar\Services;

use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\HistorialEstadoCaso;
use App\Shared\Services\ModuleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CasoBienestarService extends ModuleService
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function abrir(array $datos, string $observacion, int $idUsuario): CasoBienestar
    {
        $caso = CasoBienestar::create($datos + ['estado' => 'En_Atencion', 'abierto_en' => now()]);
        $this->historial($caso, null, 'En_Atencion', $observacion, $idUsuario);

        return $caso;
    }

    public function programarCita(CasoBienestar $caso, string $inicio, string $modalidad, int $idUsuario): Cita
    {
        $this->exigirAbierto($caso);
        $desde = Carbon::parse($inicio);
        $cita = Cita::create([
            'id_caso' => $caso->id_caso, 'inicio' => $desde, 'fin' => (clone $desde)->addHour(),
            'modalidad' => $modalidad, 'estado' => 'Programada', 'id_usuario_atencion' => $idUsuario,
            'notas_atencion' => 'Cita de seguimiento y orientación psicopedagógica programada por Bienestar',
        ]);
        $this->notificaciones->citaProgramada($caso->id_estudiante, $cita->id_cita);

        return $cita;
    }

    public function cambiarEstadoCita(Cita $cita, string $estado, ?string $notas): void
    {
        if ($cita->estado !== 'Programada') {
            throw ValidationException::withMessages(['estado' => 'Esta cita ya fue registrada.']);
        }
        // Las horas de cita se guardan tal como se ingresan, en hora de Perú
        if ($estado !== 'Cancelada' && $cita->inicio->format('Y-m-d H:i') > now('America/Lima')->format('Y-m-d H:i')) {
            throw ValidationException::withMessages(['estado' => 'La cita aún no ocurre: por ahora solo puedes cancelarla.']);
        }
        $cita->update(['estado' => $estado] + ($notas ? ['notas_atencion' => $notas] : []));
        $this->notificaciones->citaProgramada($cita->caso->id_estudiante, $cita->id_cita);
    }

    /** Cierra el caso y da por atendidas sus alertas. */
    public function cerrar(CasoBienestar $caso, string $observacion, int $idUsuario): void
    {
        DB::transaction(function () use ($caso, $observacion, $idUsuario) {
            $caso = CasoBienestar::query()->lockForUpdate()->findOrFail($caso->id_caso);
            $this->exigirAbierto($caso);
            if ($caso->citas()->where('estado', 'Programada')->exists()) {
                throw ValidationException::withMessages(['observacion' => 'Registra el resultado de las citas programadas antes de cerrar el caso.']);
            }
            $anterior = $caso->estado;
            $caso->update(['estado' => 'Cerrado', 'cerrado_en' => now()]);
            $caso->alertas()->whereIn('estado', ['remitida_bienestar', 'en_atencion'])->get()
                ->each->update(['estado' => 'atendida', 'fecha_resolucion' => now(), 'atendido_por' => $idUsuario]);
            $this->historial($caso, $anterior, 'Cerrado', $observacion, $idUsuario);
        });
    }

    public function exigirAbierto(CasoBienestar $caso): void
    {
        if ($caso->estado === 'Cerrado') {
            throw ValidationException::withMessages(['caso' => 'El caso está cerrado y ya no admite cambios.']);
        }
    }

    private function historial(CasoBienestar $caso, ?string $antes, string $despues, string $observacion, int $idUsuario): void
    {
        HistorialEstadoCaso::create([
            'id_caso' => $caso->id_caso, 'estado_anterior' => $antes, 'estado_nuevo' => $despues,
            'cambiado_por' => $idUsuario, 'cambiado_en' => now(), 'observacion' => $observacion,
        ]);
    }
}
