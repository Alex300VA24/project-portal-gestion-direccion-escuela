<?php

namespace App\Modules\SeguimientoEstudiante\Models;

use App\Shared\Models\ModuleModel;

final class SolicitudEstudiante extends ModuleModel
{
    protected $table = 'seguimiento_estudiante.solicitud_estudiante';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;
}
