<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;

/** Tabla puente (id_empresa, id_usuario): vincula una cuenta con la empresa que representa. */
final class EmpresaUsuario extends ModuleModel
{
    protected $table = 'gestion_practicas.empresa_usuario';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['es_principal' => 'boolean', 'asignado_en' => 'datetime'];
    }
}
