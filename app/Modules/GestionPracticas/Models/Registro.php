<?php

namespace App\Modules\GestionPracticas\Models;

use App\Shared\Models\ModuleModel;

final class Registro extends ModuleModel
{
    protected $table = 'gestion_practicas.practica';

    protected $primaryKey = 'id_practica';

    public $timestamps = false;
}
