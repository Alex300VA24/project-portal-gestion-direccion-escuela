<?php

namespace App\Modules\PreMatricula\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Serializa las operaciones que cambian solicitudes, ventanas o resultados
 * oficiales, para que dos acciones simultáneas no dejen datos inconsistentes.
 */
final class BloqueoAcademico
{
    private const CLAVE = 82640102;

    /**
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function transaccion(Closure $operacion): mixed
    {
        return DB::transaction(function () use ($operacion): mixed {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(?)', [self::CLAVE]);
            }

            return $operacion();
        }, 3);
    }
}
