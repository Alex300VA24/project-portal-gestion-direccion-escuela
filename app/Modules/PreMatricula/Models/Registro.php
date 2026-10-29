<?php

namespace App\Modules\PreMatricula\Models;

use App\Shared\Models\ModuleModel;

final class Registro extends ModuleModel
{
    protected $table = 'prematricula.solicitud_prematricula';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;
}
