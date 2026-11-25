<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Modules\PreMatricula\Services\PrematriculaException;
use Closure;
use Illuminate\Http\RedirectResponse;

/** Convierte una regla de negocio incumplida en un mensaje para la persona. */
trait RespondeAcciones
{
    private function accion(Closure $operacion, string $exito, ?string $ruta = null): RedirectResponse
    {
        try {
            $operacion();
        } catch (PrematriculaException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return ($ruta === null ? back() : redirect()->route($ruta))->with('status', $exito);
    }
}
