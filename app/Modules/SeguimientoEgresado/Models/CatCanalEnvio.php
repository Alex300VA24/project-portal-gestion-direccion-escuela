<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatCanalEnvio extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_canal_envio';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
