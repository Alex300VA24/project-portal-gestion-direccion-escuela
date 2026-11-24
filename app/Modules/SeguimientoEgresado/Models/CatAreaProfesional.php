<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatAreaProfesional extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_area_profesional';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
