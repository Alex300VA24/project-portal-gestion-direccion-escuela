<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatSectorLaboral extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_sector_laboral';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
