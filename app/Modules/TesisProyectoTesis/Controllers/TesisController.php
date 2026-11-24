<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Shared\Controllers\ModuleController;

/**
 * Punto de entrada de la API del módulo: solo expone el nombre y el esquema
 * (sin datos de proyectos).
 */
final class TesisController extends ModuleController
{
    protected const MODULE = 'Tesis y Proyecto de Tesis';

    protected const SCHEMA = 'tesis';

    protected const VIEW = 'modules.tesis-proyecto-tesis.portada';
}
