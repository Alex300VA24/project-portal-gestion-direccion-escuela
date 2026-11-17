<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Services\EnlaceNotificacionService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\NavegacionService;
use App\Modules\TesisProyectoTesis\Services\NotificacionService;
use App\Modules\TesisProyectoTesis\Services\PanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

final class InicioController extends BaseController
{
    public const INICIO_POR_ROL = [
        'alumno' => 'tesis-proyecto-tesis.alumno.panel',
        'asesor' => 'tesis-proyecto-tesis.asesor.proyectos',
        'jurado' => 'tesis-proyecto-tesis.jurado.proyectos',
        'director' => 'tesis-proyecto-tesis.director.panel',
        'secretaria' => 'tesis-proyecto-tesis.secretaria.resoluciones',
    ];

    /**
     * Inicio del módulo. Sin sesión muestra solo la portada pública (nombre del módulo y acceso);
     * con sesión redirige al panel del rol o muestra la selección de rol.
     */
    public function index(Request $request, NavegacionService $nav, IdentidadService $identidad, PanelService $paneles): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return view(self::VISTAS.'portada', ['module' => 'Tesis y Proyecto de Tesis', 'schema' => 'tesis']);
        }

        $id = $identidad->actual($user);
        $contextos = array_values(array_filter(
            $nav->contextos($user, $id),
            fn (string $c) => Route::has(self::INICIO_POR_ROL[$c]),
        ));

        if (count($contextos) === 1 && ! $request->boolean('elegir')) {
            return redirect()->route(self::INICIO_POR_ROL[$contextos[0]]);
        }

        return view(self::VISTAS.'inicio', [
            'nombre' => $id?->nombre ?? $user->name,
            'vinculado' => $id !== null,
            'esProfesor' => $user->hasRole('profesor'),
            'roles' => array_map(fn (string $c) => [
                'clave' => $c,
                'etiqueta' => NavegacionService::ROLES[$c],
                'url' => route(self::INICIO_POR_ROL[$c]),
                'resumen' => $paneles->resumenRol($c, $id),
            ], $contextos),
        ]);
    }

    /**
     * Abre un aviso: lo marca como leído y lleva a la pantalla de la acción (calculada en el servidor).
     */
    public function abrirNotificacion(Request $request, NotificacionService $notificaciones, EnlaceNotificacionService $enlaces): RedirectResponse
    {
        $datos = $request->validate(['n' => ['required', 'string', 'max:2000']]);
        $aviso = $notificaciones->abrir($datos['n'], $this->identidad($request)->idUsuario);

        abort_if($aviso === null, 404);

        return redirect()->to($enlaces->destino($request->user(), $aviso['evento'], $aviso['id_proyecto']));
    }

    public function marcarLeidas(Request $request, NotificacionService $notificaciones): RedirectResponse
    {
        $notificaciones->marcarLeidas($this->identidad($request)->idUsuario);

        return back();
    }
}
