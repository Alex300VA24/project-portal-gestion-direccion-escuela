<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\HistorialEstado;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use Illuminate\Support\Carbon;

/**
 * Cambia el estado del proyecto y lo deja en tesis.historial_estado.
 * Debe llamarse dentro de la transacción del caso de uso.
 */
final class HistorialService
{
    public function cambiarEstado(Proyecto $proyecto, string $nuevo, int $idUsuario, ?string $comentario = null, array $extra = []): void
    {
        $anterior = $proyecto->estado;
        $proyecto->fill(['estado' => $nuevo, ...$extra])->save();

        HistorialEstado::create([
            'id_proyecto' => $proyecto->id_proyecto,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'cambiado_por' => $idUsuario,
            'cambiado_en' => Carbon::now(),
            'observacion' => $comentario,
        ]);
    }

    /** Primer registro del historial (sin estado anterior). */
    public function inicial(Proyecto $proyecto, int $idUsuario, ?string $comentario = null): void
    {
        HistorialEstado::create([
            'id_proyecto' => $proyecto->id_proyecto,
            'estado_anterior' => null,
            'estado_nuevo' => $proyecto->estado,
            'cambiado_por' => $idUsuario,
            'cambiado_en' => Carbon::now(),
            'observacion' => $comentario,
        ]);
    }

    /**
     * Bloquea la fila del proyecto (FOR UPDATE) y la recarga para verificar el estado
     * dentro de la transacción (protección ante doble envío).
     */
    public function bloquear(Proyecto $proyecto): Proyecto
    {
        return Proyecto::query()->whereKey($proyecto->id_proyecto)->lockForUpdate()->firstOrFail();
    }
}
