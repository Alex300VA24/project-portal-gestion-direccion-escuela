<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatNivelLogro extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_nivel_logro';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
