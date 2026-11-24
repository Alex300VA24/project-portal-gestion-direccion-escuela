<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatSituacionLaboral extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_situacion_laboral';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
