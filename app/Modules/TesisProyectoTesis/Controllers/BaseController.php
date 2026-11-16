<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\NavegacionService;
use App\Modules\TesisProyectoTesis\Support\Identidad;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Base de los controladores del módulo: resuelve la identidad académica y arma
 * el contexto de navegación. Los controladores se mantienen delgados.
 */
abstract class BaseController extends Controller
{
    protected const VISTAS = 'modules.tesis-proyecto-tesis.';

    protected function identidad(Request $request): Identidad
    {
        $id = app(IdentidadService::class)->actual($request->user());

        abort_if($id === null, 403, 'Tu cuenta no está vinculada a un usuario académico. Comunícate con la Secretaría de la Escuela.');

        return $id;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, int>  $insignias
     */
    protected function vista(Request $request, string $vista, array $datos, string $rol, string $activo, array $insignias = []): View
    {
        return view(self::VISTAS.$vista, [
            ...app(NavegacionService::class)->contexto($request->user(), $rol, $activo, $insignias),
            ...$datos,
        ]);
    }
}
