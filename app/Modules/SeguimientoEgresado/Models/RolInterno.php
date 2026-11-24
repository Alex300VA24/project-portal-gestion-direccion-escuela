<?php

namespace App\Modules\SeguimientoEgresado\Models;

use App\Shared\Models\ModuleModel;

class RolInterno extends ModuleModel
{
    protected $table = 'seguimiento_egresado.rol_interno';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nombre'];

    public function usuarios()
    {
        return $this->hasMany(UsuarioInterno::class, 'id_rol');
    }
}
