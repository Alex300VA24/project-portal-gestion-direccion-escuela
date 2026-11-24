<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class CatEstadoAcademico extends ModuleModel
{
    protected $table = 'seguimiento_egresado.cat_estado_academico';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre', 'activo'];
}
