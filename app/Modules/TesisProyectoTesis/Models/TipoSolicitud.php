<?php

namespace App\Modules\TesisProyectoTesis\Models;

use App\Shared\Models\ModuleModel;

final class TipoSolicitud extends ModuleModel
{
    protected $table = 'tesis.tipo_solicitud';

    protected $primaryKey = 'id_tipo_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
    ];

    protected function casts(): array
    {
        return [];
    }
}
