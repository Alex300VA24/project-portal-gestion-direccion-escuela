<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;

final class Registro extends ModuleModel
{
    protected $table = 'tesis.proyecto_tesis';

    protected $primaryKey = 'id_proyecto';

    public $timestamps = false;
}
