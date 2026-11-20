<?php

namespace App\Modules\SeguimientoEgresado\Middleware;

use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Support\Accesos\Capacidades;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige al menos una de las capacidades indicadas (OR) a alguna de las identidades de la petición: el personal que
 * inició sesión en el portal (Secretaría, Dirección) o el egresado que abrió su enlace personal.
 * Uso en rutas: RequiereCapacidad::class.':padron.consultar,padron.gestionar'
 *
 * Sin identidad: en rutas del egresado se envía a la pantalla de entrada (se entra con el enlace personal); en las
 * demás responde 401, o 403 si hay un usuario del portal sin rol de Secretaría o Dirección.
 * Con identidad pero sin la capacidad: 403.
 * Las rutas internas combinan este middleware con `auth`, que manda a quien no inició sesión al acceso del portal.
 */
final class RequiereCapacidad
{
    public function handle(Request $request, Closure $next, string ...$capacidades): Response
    {
        foreach ($capacidades as $capacidad) {
            if (! Capacidades::existe($capacidad)) {
                throw new \LogicException("La capacidad «{$capacidad}» no existe en la matriz de permisos.");
            }
        }

        $sesion = SesionAcceso::desde($request);
        $identidades = $sesion->todas();

        foreach ($identidades as $identidad) {
            foreach ($capacidades as $capacidad) {
                if ($identidad->puede($capacidad)) {
                    return $next($request);
                }
            }
        }

        // Una ruta solo del egresado, sin haber abierto su enlace: se explica cómo entrar en lugar de negar el acceso.
        if ($this->soloDelEgresado($capacidades) && $sesion->egresado() === null) {
            return $this->sinSesion($request, $capacidades);
        }

        if ($identidades === []) {
            return $this->sinSesion($request, $capacidades);
        }

        abort(403, 'No tienes permiso para acceder a este recurso.');
    }

    /** @param  array<int, string>  $capacidades */
    private function soloDelEgresado(array $capacidades): bool
    {
        return collect($capacidades)->every(fn (string $c) => Capacidades::MATRIZ[$c] === [Perfil::Egresado]);
    }

    /**
     * Sin identidad: se envía a la pantalla de entrada solo cuando la ruta es del egresado; las páginas internas
     * responden 401 (sin usuario) o 403 (usuario del portal sin rol en el módulo).
     *
     * @param  array<int, string>  $capacidades
     */
    private function sinSesion(Request $request, array $capacidades): Response
    {
        $esDelEgresado = collect($capacidades)->contains(fn (string $c) => in_array(Perfil::Egresado, Capacidades::MATRIZ[$c], true));

        if ($esDelEgresado && ! $request->expectsJson() && Route::has('seguimiento-egresado.acceso')) {
            return redirect()->guest(route('seguimiento-egresado.acceso'));
        }

        if ($request->user() !== null) {
            abort(403, 'Tu cuenta no tiene un rol con acceso a Seguimiento al Egresado.');
        }

        abort(401, 'Se requiere iniciar sesión.');
    }
}
