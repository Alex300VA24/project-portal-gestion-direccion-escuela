<?php

namespace App\Modules\SeguimientoEgresado\Middleware;

use App\Modules\SeguimientoEgresado\Services\EstadoDeCampanias;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Abre y cierra las campañas según sus fechas antes de atender cualquier pantalla o enlace del módulo. */
final class SincronizaEstadoDeCampanias
{
    public function __construct(private readonly EstadoDeCampanias $estados) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->estados->sincronizar();

        return $next($request);
    }
}
