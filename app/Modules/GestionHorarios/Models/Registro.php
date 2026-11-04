<?php

namespace App\Modules\GestionHorarios\Models;

use App\Shared\Models\ModuleModel;

final class Registro extends ModuleModel
{
    protected $table = 'gestion_horarios.horario';

    protected $primaryKey = 'id_horario';

    public $timestamps = false;
}
