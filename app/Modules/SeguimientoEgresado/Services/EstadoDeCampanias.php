<?php

namespace App\Modules\SeguimientoEgresado\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Una campaña se abre y se cierra sola según sus fechas: antes de su inicio está PROGRAMADA, entre el inicio y el fin
 * (incluidos) está ABIERTA y después del fin está CERRADA. El estado se guarda en la base porque varias consultas lo
 * usan; esta clase lo mantiene al día cada vez que alguien entra al módulo (son actualizaciones que casi nunca tocan filas).
 */
final class EstadoDeCampanias
{
    private const TABLA = 'seguimiento_egresado.campania_seguimiento';

    public static function paraFechas(string|Carbon $inicio, string|Carbon $fin, ?Carbon $hoy = null): string
    {
        $hoy = ($hoy ?? Carbon::today())->toDateString();
        $inicio = Carbon::parse($inicio)->toDateString();
        $fin = Carbon::parse($fin)->toDateString();

        return $hoy < $inicio ? 'PROGRAMADA' : ($hoy > $fin ? 'CERRADA' : 'ABIERTA');
    }

    public function sincronizar(?Carbon $hoy = null): void
    {
        $hoy = ($hoy ?? Carbon::today())->toDateString();

        try {
            DB::table(self::TABLA)->where('fecha_inicio', '>', $hoy)->where('estado', '<>', 'PROGRAMADA')->update(['estado' => 'PROGRAMADA']);
            DB::table(self::TABLA)->where('fecha_inicio', '<=', $hoy)->where('fecha_fin', '>=', $hoy)->where('estado', '<>', 'ABIERTA')->update(['estado' => 'ABIERTA']);
            DB::table(self::TABLA)->where('fecha_fin', '<', $hoy)->where('estado', '<>', 'CERRADA')->update(['estado' => 'CERRADA']);
        } catch (Throwable) {
            // Sin la tabla (por ejemplo, base sin migrar) no hay nada que mantener al día.
        }
    }
}
