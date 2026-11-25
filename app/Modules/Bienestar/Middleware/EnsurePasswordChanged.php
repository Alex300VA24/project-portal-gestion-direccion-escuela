<?php

namespace App\Modules\Bienestar\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user) {
            $debeCambiar = DB::table('seguridad.usuario')
                ->where('correo_institucional', $user->email)
                ->value('debe_cambiar_contrasena');

            if ($debeCambiar) {
                // Rutas permitidas mientras debe cambiar contraseña
                if ($request->routeIs('profile.*') ||
                    $request->routeIs('password.*') ||
                    $request->routeIs('logout') ||
                    $request->is('logout')) {
                    return $next($request);
                }

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Debe cambiar su contraseña institucional antes de realizar esta operación.',
                    ], 403);
                }

                return redirect()->route('profile.edit')
                    ->with('warning', 'Por motivos de seguridad institucional (primer ingreso), es obligatorio cambiar su contraseña para activar su cuenta.');
            }
        }

        return $next($request);
    }
}
