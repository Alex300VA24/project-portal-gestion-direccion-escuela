<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Modules\GestionPracticas\Support\Acceso;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Portada pública del módulo (index y API los resuelve ModuleController). El trabajo real está en /gestion-practicas/panel. */
final class PracticaController extends ModuleController
{
    protected const MODULE = 'Gestión de Prácticas';

    protected const SCHEMA = 'gestion_practicas';

    protected const VIEW = 'modules.gestion-practicas.index';

    /**
     * Quien ya inició sesión y tiene acceso al módulo entra directo al panel, sin pasar por la portada.
     * La portada sigue siendo pública para quien no ha iniciado sesión o no tiene permiso.
     */
    public function portada(): View|RedirectResponse
    {
        return request()->user()?->can(Acceso::VER)
            ? redirect()->route('gestion-practicas.panel')
            : $this->index();
    }
}
