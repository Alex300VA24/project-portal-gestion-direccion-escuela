<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Shared\Controllers\ModuleController;

/** Portada pública del módulo (index y API los resuelve ModuleController). El trabajo real está en /gestion-practicas/panel. */
final class PracticaController extends ModuleController
{
    protected const MODULE = 'Gestión de Prácticas';

    protected const SCHEMA = 'gestion_practicas';

    protected const VIEW = 'modules.gestion-practicas.index';
}
